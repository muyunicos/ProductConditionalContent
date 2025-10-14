<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderizado y aplicación de reglas de contenido en el frontend.
 *
 * Responsabilidad SOLA: Aplicar reglas y variantes a descripciones (NO campos, NO shortcodes).
 * - Aplica reglas a la descripción larga o corta del producto usando filtros.
 * - Soporta variantes condicionales, reglas reutilizables, y recursividad limitada.
 * - NO contiene lógica de administración ni helpers compartidos.
 * - La lógica de shortcodes como [campo-cond] va en class-shortcodes.php.
 */
final class GDM_Rules_Frontend
{
    private static $instance = null;
    private $processed_rules = [];
    private const MAX_RECURSION_DEPTH = 5;
    private static $rule_cache = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Filtros para aplicar reglas a la descripción larga y corta
        add_filter('the_content', [$this, 'run_engine_for_long_desc'], 20);
        add_filter('woocommerce_short_description', [$this, 'run_engine_for_short_desc'], 20);
    }

    public function run_engine_for_long_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'larga');
    }

    public function run_engine_for_short_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'corta');
    }

    /**
     * Punto de entrada principal: aplica las reglas al contenido original.
     */
    private function apply_rules($initial_content, $type) {
        global $product;
        if (!is_a($product, 'WC_Product')) return $initial_content;

        $all_rules = get_posts([
            'post_type'      => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'habilitada'], // ✅ CORREGIDO: Ahora busca reglas habilitadas
            'meta_key'       => '_gdm_prioridad',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'cache_results'  => true,
        ]);
        if (empty($all_rules)) return $initial_content;

        // Filtrar reglas aplicables según el producto y tipo de descripción
        $matching_rules = array_filter($all_rules, function($rule) use ($product, $type) {
            return $this->is_rule_applicable($rule->ID, $product, $type);
        });
        if (empty($matching_rules)) return $initial_content;

        // Forzar reglas prioritarias si existen
        $forced_rules = array_filter($matching_rules, function($rule) {
            return (bool) get_post_meta($rule->ID, '_gdm_forzar_aplicacion', true);
        });
        $rules_to_process = !empty($forced_rules) ? $forced_rules : $matching_rules;

        $current_content = $initial_content;

        foreach ($rules_to_process as $rule) {
            $rule_data = $this->get_rule_data($rule->ID);
            if (!$rule_data) continue;

            if ($rule_data['ubicacion'] === 'solo_vacia') {
                if (!empty(trim(strip_tags($initial_content)))) continue;
                $rule_data['ubicacion'] = 'reemplaza';
            }

            $this->processed_rules = [];
            $generated_content = $this->process_rule_content($rule_data, $product);

            switch ($rule_data['ubicacion']) {
                case 'antes':
                    $current_content = $generated_content . $current_content;
                    break;
                case 'despues':
                    $current_content = $current_content . $generated_content;
                    break;
                case 'reemplaza':
                default:
                    $current_content = $generated_content;
                    break;
            }

            if ($rule_data['regla_final']) break;
        }

        return do_shortcode(wpautop($current_content));
    }

    /**
     * Verifica si una regla es aplicable a este producto y tipo de descripción.
     */
    private function is_rule_applicable($rule_id, $product, $type) {
        $data = $this->get_rule_data($rule_id);
        if (!$data) return false;

        // Si la regla es SOLO reutilizable, no aplicar automáticamente
        if (in_array('reutilizable', $data['aplicar_a']) && count($data['aplicar_a']) === 1) return false;
        // Verificar tipo de descripción (larga/corta)
        if (empty($data['aplicar_a']) || !in_array($type, $data['aplicar_a'], true)) return false;

        // Validar ámbito (categorías y tags)
        return $this->is_rule_in_scope($data, $product);
    }

    /**
     * Verifica si una regla está en el ámbito del producto (categoría/tag).
     */
    private function is_rule_in_scope($data, $product) {
        if (empty($data['ambito'])) return true;

        foreach ($data['ambito'] as $scope) {
            if ($scope['tipo'] === 'categoria' && !empty($scope['id'])) {
                if (has_term($scope['id'], 'product_cat', $product->get_id())) return true;
            } elseif ($scope['tipo'] === 'tag' && !empty($scope['id'])) {
                if (has_term($scope['id'], 'product_tag', $product->get_id())) return true;
            }
        }
        return false;
    }

    private function get_rule_data($rule_id) {
        if (isset(self::$rule_cache[$rule_id])) {
            return self::$rule_cache[$rule_id];
        }
        $data = [
            'ubicacion'           => get_post_meta($rule_id, '_gdm_ubicacion_desc', true) ?: 'reemplaza',
            'regla_final'         => (bool) get_post_meta($rule_id, '_gdm_regla_final', true),
            'aplicar_a'           => get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [],
            'ambito'              => get_post_meta($rule_id, '_gdm_ambito', true) ?: [],
            'descripcion'         => get_post_meta($rule_id, '_gdm_descripcion', true),
            'variantes'           => get_post_meta($rule_id, '_gdm_variantes', true) ?: [],
        ];
        self::$rule_cache[$rule_id] = $data;
        return $data;
    }

    /**
     * Procesa el contenido de una regla, aplicando variantes condicionales.
     */
    private function process_rule_content($rule_data, $product) {
        $content = $rule_data['descripcion'] ?? '';
        $variant_text = '';

        if (!empty($rule_data['variantes']) && is_array($rule_data['variantes'])) {
            foreach ($rule_data['variantes'] as $variant) {
                $condition_met = false;
                switch ($variant['cond_type']) {
                    case 'tag':
                        if (!empty($variant['cond_key'])) {
                            $condition_met = has_term($variant['cond_key'], 'product_tag', $product->get_id());
                        }
                        break;
                    case 'meta':
                        if (!empty($variant['cond_key'])) {
                            $meta_value = get_post_meta($product->get_id(), $variant['cond_key'], true);
                            if (!empty($variant['cond_value'])) {
                                $condition_met = ($meta_value == $variant['cond_value']);
                            } else {
                                $condition_met = !empty($meta_value);
                            }
                        }
                        break;
                }
                if ($condition_met) {
                    $variant_text = $variant['texto'] ?? '';
                    break;
                }
            }
        }

        $content = $this->replace_placeholders($content, $product);
        $variant_text = $this->replace_placeholders($variant_text, $product);

        $final_content = $content;
        if (!empty($variant_text)) {
            $final_content = str_replace('[variante]', $variant_text, $content);
            if (strpos($final_content, '[variante]') === false) {
                $final_content .= ' ' . $variant_text;
            }
        } else {
            $final_content = str_replace('[variante]', '', $content);
        }

        return $this->process_nested_rules($final_content, $product);
    }

    private function replace_placeholders($text, $product) {
        $placeholders = [
            '[nombre]' => $product->get_name(),
            '[precio]' => wc_price($product->get_price()),
            '[sku]' => $product->get_sku(),
        ];
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    private function process_nested_rules($content, $product, $depth = 0) {
        if ($depth >= self::MAX_RECURSION_DEPTH || !preg_match_all('/\[rule-(\d+)\]/', $content, $matches)) {
            return $content;
        }

        foreach ($matches[1] as $nested_rule_id) {
            $nested_rule_id = intval($nested_rule_id);
            if (in_array($nested_rule_id, $this->processed_rules, true)) continue;

            $this->processed_rules[] = $nested_rule_id;
            $nested_data = $this->get_rule_data($nested_rule_id);

            if (!$nested_data || !in_array('reutilizable', $nested_data['aplicar_a'])) continue;

            $nested_content = $this->process_rule_content($nested_data, $product);
            $nested_content = $this->process_nested_rules($nested_content, $product, $depth + 1);

            $content = str_replace("[rule-{$nested_rule_id}]", $nested_content, $content);
        }
        return $content;
    }
}