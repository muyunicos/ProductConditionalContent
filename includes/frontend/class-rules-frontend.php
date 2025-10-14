<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderizado y aplicación de reglas de contenido en el frontend.
 *
 * - Aplica reglas a la descripción larga o corta del producto usando filtros.
 * - Soporta variantes condicionales, reglas reutilizables, y recursividad limitada.
 * - No debe contener lógica de administración ni helpers compartidos.
 * - La lógica de shortcodes como [campo-cond] debe ir en class-shortcodes.php.
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
            'post_type'      => 'descripcion_regla',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
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
     * Verifica si una regla es aplicable a este producto y tipo de descripción
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
     * Verifica si una regla está en el ámbito del producto (categoría/tag)
     */
    private function is_rule_in_scope($data, $product) {
        $category_match = $data['todas_categorias'] === '1' ||
            count(array_intersect($product->get_category_ids(), $data['categorias_objetivo'])) > 0;
        $tag_match = $data['cualquier_tag'] === '1' ||
            count(array_intersect($product->get_tag_ids(), $data['tags_objetivo'])) > 0;
        return $category_match && $tag_match;
    }

    /**
     * Obtiene y cachea los datos de una regla por ID
     */
    private function get_rule_data($rule_id) {
        if (isset(self::$rule_cache[$rule_id])) return self::$rule_cache[$rule_id];

        $post = get_post($rule_id);
        if (!$post || $post->post_type !== 'descripcion_regla' || $post->post_status !== 'publish') return null;

        $data = [
            'prioridad'           => (int) (get_post_meta($rule_id, '_gdm_prioridad', true) ?: 10),
            'aplicar_a'           => get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [],
            'todas_categorias'    => get_post_meta($rule_id, '_gdm_todas_categorias', true),
            'categorias_objetivo' => array_map('intval', get_post_meta($rule_id, '_gdm_categorias_objetivo', true) ?: []),
            'cualquier_tag'       => get_post_meta($rule_id, '_gdm_cualquier_tag', true),
            'tags_objetivo'       => array_map('intval', get_post_meta($rule_id, '_gdm_tags_objetivo', true) ?: []),
            'ubicacion'           => get_post_meta($rule_id, '_gdm_ubicacion', true) ?: 'reemplaza',
            'regla_final'         => get_post_meta($rule_id, '_gdm_regla_final', true),
            'forzar_aplicacion'   => get_post_meta($rule_id, '_gdm_forzar_aplicacion', true),
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
                    case 'default':
                        $condition_met = true;
                        break;
                }
                if ($condition_met) {
                    if ($variant['action'] === 'reemplaza_todo') {
                        return $this->process_shortcodes($variant['text'], $product, '');
                    }
                    $variant_text = $variant['text'];
                    break;
                }
            }
        }
        return $this->process_shortcodes($content, $product, $variant_text);
    }

    /**
     * Procesa y reemplaza los shortcodes internos en el contenido.
     * - Los shortcodes como [campo-cond] deben ser gestionados en class-shortcodes.php, no aquí.
     */
    private function process_shortcodes($content, $product, $variant_text) {
        if (!is_a($product, 'WC_Product')) return $content;

        $content = str_replace('[slug-prod]', ucwords(str_replace('-', ' ', $product->get_slug())), $content);
        $content = str_replace('[var-cond]', $variant_text, $content);

        // [rule-id id="123"] recursividad limitada
        $content = preg_replace_callback('/\[rule-id id=["\']?(\d+)["\']?\]/', function($matches) use ($product) {
            $rule_id = intval($matches[1]);
            if (in_array($rule_id, $this->processed_rules, true)) return '';
            if (count($this->processed_rules) >= self::MAX_RECURSION_DEPTH) return '';
            $this->processed_rules[] = $rule_id;
            $rule_data = $this->get_rule_data($rule_id);
            if (!$rule_data) return '';
            $result = $this->process_rule_content($rule_data, $product);
            array_pop($this->processed_rules);
            return $result;
        }, $content);

        return $content;
    }
}

// Inicialización automática en frontend
GDM_Rules_Frontend::instance();