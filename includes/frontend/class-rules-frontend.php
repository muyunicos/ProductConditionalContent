<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderizado y aplicación de reglas de contenido en el frontend
 * Sistema modular v6.0 - Compatible con módulos dinámicos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
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
     * Punto de entrada principal: aplica las reglas al contenido original
     */
    private function apply_rules($initial_content, $type) {
        global $product;
        if (!is_a($product, 'WC_Product')) return $initial_content;

        $all_rules = get_posts([
            'post_type'      => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'habilitada'], // ✅ CORREGIDO
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
            // Verificar forzado en módulo descripción
            return (bool) get_post_meta($rule->ID, '_gdm_descripcion_forzar', true);
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
     * Verifica si una regla es aplicable a este producto y tipo de descripción
     */
    private function is_rule_applicable($rule_id, $product, $type) {
        $data = $this->get_rule_data($rule_id);
        if (!$data) return false;

        // Verificar si el módulo descripción está activo
        $aplicar_a = get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [];
        if (!in_array('descripcion', $aplicar_a)) return false;

        // Verificar si es solo reutilizable
        $is_reutilizable = get_post_meta($rule_id, '_gdm_reutilizable', true) === '1';
        if ($is_reutilizable && count($aplicar_a) === 1) return false;

        // Verificar tipo de descripción (larga/corta) desde el módulo
        $tipos = get_post_meta($rule_id, '_gdm_descripcion_tipos', true) ?: ['larga'];
        if (!in_array($type, $tipos)) return false;

        // Validar ámbito (categorías y tags)
        return $this->is_rule_in_scope($data, $product);
    }

    /**
     * Verifica si una regla está en el ámbito del producto
     */
    private function is_rule_in_scope($data, $product) {
        // Todas las categorías
        if (isset($data['todas_categorias']) && $data['todas_categorias'] === '1') {
            return true;
        }

        // Categorías específicas
        if (!empty($data['categorias'])) {
            foreach ($data['categorias'] as $cat_id) {
                if (has_term($cat_id, 'product_cat', $product->get_id())) {
                    return true;
                }
            }
        }

        // Cualquier tag
        if (isset($data['cualquier_tag']) && $data['cualquier_tag'] === '1') {
            $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'ids']);
            if (!empty($tags)) {
                return true;
            }
        }

        // Tags específicos
        if (!empty($data['tags'])) {
            foreach ($data['tags'] as $tag_id) {
                if (has_term($tag_id, 'product_tag', $product->get_id())) {
                    return true;
                }
            }
        }

        // Si no tiene ámbito definido, aplicar a todos
        if (empty($data['categorias']) && empty($data['tags']) && 
            (!isset($data['todas_categorias']) || $data['todas_categorias'] !== '1') &&
            (!isset($data['cualquier_tag']) || $data['cualquier_tag'] !== '1')) {
            return true;
        }

        return false;
    }

    /**
     * Obtener datos de la regla (con módulos)
     */
    private function get_rule_data($rule_id) {
        if (isset(self::$rule_cache[$rule_id])) {
            return self::$rule_cache[$rule_id];
        }

        $data = [
            // Configuración general
            'prioridad' => (int) get_post_meta($rule_id, '_gdm_prioridad', true) ?: 10,
            'todas_categorias' => get_post_meta($rule_id, '_gdm_todas_categorias', true),
            'categorias' => get_post_meta($rule_id, '_gdm_categorias_objetivo', true) ?: [],
            'cualquier_tag' => get_post_meta($rule_id, '_gdm_cualquier_tag', true),
            'tags' => get_post_meta($rule_id, '_gdm_tags_objetivo', true) ?: [],
            
            // Datos del módulo descripción
            'ubicacion' => get_post_meta($rule_id, '_gdm_descripcion_ubicacion', true) ?: 'reemplaza',
            'contenido' => get_post_meta($rule_id, '_gdm_descripcion_contenido', true) ?: '',
            'variantes' => get_post_meta($rule_id, '_gdm_descripcion_variantes', true) ?: [],
            'regla_final' => get_post_meta($rule_id, '_gdm_descripcion_regla_final', true) === '1',
        ];

        self::$rule_cache[$rule_id] = $data;
        return $data;
    }

    /**
     * Procesa el contenido de una regla, aplicando variantes condicionales
     */
    private function process_rule_content($rule_data, $product) {
        $content = $rule_data['contenido'] ?? '';
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
                    case 'default':
                        $condition_met = true; // Siempre se cumple
                        break;
                }
                
                if ($condition_met) {
                    $variant_text = $variant['text'] ?? '';
                    
                    // Si la acción es "reemplaza_todo", retornar directamente
                    if (isset($variant['action']) && $variant['action'] === 'reemplaza_todo') {
                        return $this->replace_placeholders($variant_text, $product);
                    }
                    
                    break; // Usar primera variante que cumpla
                }
            }
        }

        $content = $this->replace_placeholders($content, $product);
        $variant_text = $this->replace_placeholders($variant_text, $product);

        $final_content = $content;
        if (!empty($variant_text)) {
            $final_content = str_replace('[var-cond]', $variant_text, $content);
            if (strpos($final_content, '[var-cond]') === false && strpos($content, '[var-cond]') === false) {
                $final_content .= ' ' . $variant_text;
            }
        } else {
            $final_content = str_replace('[var-cond]', '', $content);
        }

        return $this->process_nested_rules($final_content, $product);
    }

    /**
     * Reemplazar placeholders/comodines
     */
    private function replace_placeholders($text, $product) {
        $placeholders = [
            '[nombre-prod]' => $product->get_name(),
            '[precio-prod]' => wc_price($product->get_price()),
            '[sku-prod]' => $product->get_sku(),
            '[slug-prod]' => $product->get_slug(),
        ];
        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }

    /**
     * Procesar reglas anidadas (rule-id)
     */
    private function process_nested_rules($content, $product, $depth = 0) {
        if ($depth >= self::MAX_RECURSION_DEPTH || !preg_match_all('/\[rule-(\d+)\]/', $content, $matches)) {
            return $content;
        }

        foreach ($matches[1] as $nested_rule_id) {
            $nested_rule_id = intval($nested_rule_id);
            if (in_array($nested_rule_id, $this->processed_rules, true)) continue;

            $this->processed_rules[] = $nested_rule_id;
            $nested_data = $this->get_rule_data($nested_rule_id);

            // Verificar que sea reutilizable
            $is_reutilizable = get_post_meta($nested_rule_id, '_gdm_reutilizable', true) === '1';
            if (!$is_reutilizable) continue;

            $nested_content = $this->process_rule_content($nested_data, $product);
            $nested_content = $this->process_nested_rules($nested_content, $product, $depth + 1);

            $content = str_replace("[rule-{$nested_rule_id}]", $nested_content, $content);
        }
        return $content;
    }
}

// Inicialización automática en frontend
GDM_Rules_Frontend::instance();