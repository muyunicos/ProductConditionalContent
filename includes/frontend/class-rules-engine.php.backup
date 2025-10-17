<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderizado y aplicación de reglas de contenido en el frontend
 * Sistema modular v6.1 - Compatible con módulos dinámicos ampliados
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 */
final class GDM_Rules_Frontend
{
    private static $instance = null;
    private $processed_rules = [];
    private const MAX_RECURSION_DEPTH = 5;
    private static $rule_cache = [];
    private $processed_products = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Filtros para descripción (EXISTENTE)
        add_filter('the_content', [$this, 'run_engine_for_long_desc'], 20);
        add_filter('woocommerce_short_description', [$this, 'run_engine_for_short_desc'], 20);
        
        // ✨ NUEVOS FILTROS PARA MÓDULOS ADICIONALES
        add_filter('woocommerce_product_get_gallery_image_ids', [$this, 'apply_gallery_rules'], 20, 2);
        add_filter('the_title', [$this, 'apply_title_rules'], 20, 2);
        add_filter('woocommerce_product_get_name', [$this, 'apply_product_title_rules'], 20, 2);
        add_filter('woocommerce_product_get_price', [$this, 'apply_price_rules'], 20, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'apply_regular_price_rules'], 20, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'apply_sale_price_rules'], 20, 2);
        add_filter('woocommerce_product_is_featured', [$this, 'apply_featured_status'], 20, 2);
        
        // Hook para procesar reglas al cargar productos
        add_action('template_redirect', [$this, 'process_all_applicable_rules'], 5);
    }

    // =========================================================================
    // MÉTODOS EXISTENTES (DESCRIPCIÓN)
    // =========================================================================

    public function run_engine_for_long_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'larga');
    }

    public function run_engine_for_short_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'corta');
    }

    private function apply_rules($initial_content, $type) {
        global $product;
        if (!is_a($product, 'WC_Product')) return $initial_content;

        $all_rules = get_posts([
            'post_type'      => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'habilitada'],
            'meta_key'       => '_gdm_prioridad',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'cache_results'  => true,
        ]);
        if (empty($all_rules)) return $initial_content;

        $matching_rules = array_filter($all_rules, function($rule) use ($product, $type) {
            return $this->is_rule_applicable($rule->ID, $product, $type);
        });
        if (empty($matching_rules)) return $initial_content;

        $forced_rules = array_filter($matching_rules, function($rule) {
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

    private function is_rule_applicable($rule_id, $product, $type) {
        $data = $this->get_rule_data($rule_id);
        if (!$data) return false;

        $aplicar_a = get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [];
        if (!in_array('descripcion', $aplicar_a)) return false;

        $is_reutilizable = get_post_meta($rule_id, '_gdm_reutilizable', true) === '1';
        if ($is_reutilizable && count($aplicar_a) === 1) return false;

        $tipos = get_post_meta($rule_id, '_gdm_descripcion_tipos', true) ?: ['larga'];
        if (!in_array($type, $tipos)) return false;

        return $this->is_rule_in_condition($data, $product);
    }

    private function is_rule_in_condition($data, $product) {
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
        
        // ✨ NUEVO: Productos específicos
        $productos_enabled = get_post_meta($product->get_id(), '_gdm_productos_especificos_enabled', true);
        if ($productos_enabled === '1') {
            $productos = get_post_meta($product->get_id(), '_gdm_productos_objetivo', true) ?: [];
            if (in_array($product->get_id(), $productos)) {
                return true;
            }
        }
        
        // ✨ NUEVO: Atributos
        $atributos_enabled = get_post_meta($product->get_id(), '_gdm_atributos_enabled', true);
        if ($atributos_enabled === '1') {
            $atributos = get_post_meta($product->get_id(), '_gdm_atributos', true) ?: [];
            foreach ($atributos as $taxonomy => $term_ids) {
                foreach ($term_ids as $term_id) {
                    if (has_term($term_id, $taxonomy, $product->get_id())) {
                        return true;
                    }
                }
            }
        }
        
        // ✨ NUEVO: Stock
        $stock_enabled = get_post_meta($product->get_id(), '_gdm_stock_enabled', true);
        if ($stock_enabled === '1') {
            $stock_status = get_post_meta($product->get_id(), '_gdm_stock_status', true) ?: [];
            if (in_array($product->get_stock_status(), $stock_status)) {
                return true;
            }
        }
        
        // ✨ NUEVO: Precio
        if ($this->check_price_condition($product->get_id(), $product)) {
            return true;
        }
        
        // ✨ NUEVO: Título
        if ($this->check_title_condition($product->get_id(), $product)) {
            return true;
        }

        // Si no tiene ámbito definido, aplicar a todos
        if (empty($data['categorias']) && empty($data['tags']) && 
            (!isset($data['todas_categorias']) || $data['todas_categorias'] !== '1') &&
            (!isset($data['cualquier_tag']) || $data['cualquier_tag'] !== '1')) {
            return true;
        }

        return false;
    }
    
    // ✨ NUEVO: Verificar ámbito de precio
    private function check_price_condition($rule_id, $product) {
        $precio_enabled = get_post_meta($rule_id, '_gdm_precio_enabled', true);
        if ($precio_enabled !== '1') return false;
        
        $condicion = get_post_meta($rule_id, '_gdm_precio_condicion', true);
        $valor = floatval(get_post_meta($rule_id, '_gdm_precio_valor', true));
        $valor2 = floatval(get_post_meta($rule_id, '_gdm_precio_valor2', true));
        $precio_producto = floatval($product->get_price());
        
        switch ($condicion) {
            case 'mayor_que':
                return $precio_producto > $valor;
            case 'menor_que':
                return $precio_producto < $valor;
            case 'igual_a':
                return abs($precio_producto - $valor) < 0.01;
            case 'entre':
                return $precio_producto >= $valor && $precio_producto <= $valor2;
        }
        
        return false;
    }
    
    // ✨ NUEVO: Verificar ámbito de título
    private function check_title_condition($rule_id, $product) {
        $titulo_enabled = get_post_meta($rule_id, '_gdm_titulo_enabled', true);
        if ($titulo_enabled !== '1') return false;
        
        $condicion = get_post_meta($rule_id, '_gdm_titulo_condicion', true);
        $texto = get_post_meta($rule_id, '_gdm_titulo_texto', true);
        $case_sensitive = get_post_meta($rule_id, '_gdm_titulo_case_sensitive', true) === '1';
        $titulo_producto = $product->get_name();
        
        if (!$case_sensitive) {
            $titulo_producto = strtolower($titulo_producto);
            $texto = strtolower($texto);
        }
        
        switch ($condicion) {
            case 'contiene':
                return strpos($titulo_producto, $texto) !== false;
            case 'no_contiene':
                return strpos($titulo_producto, $texto) === false;
            case 'empieza_con':
                return strpos($titulo_producto, $texto) === 0;
            case 'termina_con':
                return substr($titulo_producto, -strlen($texto)) === $texto;
            case 'regex':
                return @preg_match($texto, $product->get_name()) === 1;
        }
        
        return false;
    }

    private function get_rule_data($rule_id) {
        if (isset(self::$rule_cache[$rule_id])) {
            return self::$rule_cache[$rule_id];
        }

        $data = [
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
                            $condition_met = ($meta_value == $variant['cond_value']);
                        }
                        break;
                    case 'default':
                        $condition_met = empty($variant_text);
                        break;
                }

                if ($condition_met) {
                    $variant_text = $this->replace_placeholders($variant['text'], $product);
                    if ($variant['action'] === 'salto') {
                        $variant_text .= '<br>';
                    }
                    break;
                }
            }
        }

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

    private function replace_placeholders($text, $product) {
        $placeholders = [
            '[nombre-prod]' => $product->get_name(),
            '[precio-prod]' => wc_price($product->get_price()),
            '[sku-prod]' => $product->get_sku(),
            '[slug-prod]' => $product->get_slug(),
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

            $is_reutilizable = get_post_meta($nested_rule_id, '_gdm_reutilizable', true) === '1';
            if (!$is_reutilizable) continue;

            $nested_content = $this->process_rule_content($nested_data, $product);
            $nested_content = $this->process_nested_rules($nested_content, $product, $depth + 1);
            $content = str_replace("[rule-{$nested_rule_id}]", $nested_content, $content);
        }
        return $content;
    }
    
    // =========================================================================
    // ✨ NUEVOS MÉTODOS PARA MÓDULOS ADICIONALES
    // =========================================================================
    
    /**
     * Procesar todas las reglas aplicables al producto actual
     */
    public function process_all_applicable_rules() {
        if (!is_singular('product') && !is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }
        
        global $product;
        if (!is_a($product, 'WC_Product')) {
            return;
        }
        
        // Evitar procesar el mismo producto varias veces
        $product_id = $product->get_id();
        if (in_array($product_id, $this->processed_products)) {
            return;
        }
        
        $this->processed_products[] = $product_id;
        
        // Obtener reglas aplicables
        $rules = get_posts([
            'post_type' => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'habilitada'],
            'meta_key' => '_gdm_prioridad',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ]);
        
        foreach ($rules as $rule) {
            $rule_data = $this->get_rule_data($rule->ID);
            
            // Verificar ámbito
            if (!$this->is_rule_in_condition($rule_data, $product)) {
                continue;
            }
            
            // Aplicar módulos activos
            $aplicar_a = get_post_meta($rule->ID, '_gdm_aplicar_a', true) ?: [];
            
            foreach ($aplicar_a as $module_id) {
                $this->apply_module($module_id, $rule->ID, $product);
            }
        }
    }
    
    /**
     * Aplicar módulo específico v7.0
     * Usa el nuevo sistema de _gdm_actions_config
     */
    private function apply_module($module_id, $rule_id, $product) {
        // Obtener configuración completa de acciones
        $all_actions = get_post_meta($rule_id, '_gdm_actions_config', true);

        if (empty($all_actions) || !is_array($all_actions)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("⚠️ GDM Rules Engine: No hay configuración de acciones para regla {$rule_id}");
            }
            return;
        }

        // Verificar si el módulo específico tiene configuración
        if (!isset($all_actions[$module_id])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("⚠️ GDM Rules Engine: Módulo '{$module_id}' no configurado en regla {$rule_id}");
            }
            return;
        }

        $module_config = $all_actions[$module_id];

        // Verificar si está habilitado
        if (empty($module_config['enabled'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("⏭️ GDM Rules Engine: Módulo '{$module_id}' está deshabilitado en regla {$rule_id}");
            }
            return;
        }

        // Verificar si tiene código ejecutable
        if (empty($module_config['code'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("⚠️ GDM Rules Engine: Módulo '{$module_id}' no tiene código ejecutable");
            }
            return;
        }

        // Ejecutar código generado
        $object_id = $product->get_id();
        $context = 'products'; // Contexto actual

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("🚀 GDM Rules Engine: Ejecutando módulo '{$module_id}' para producto {$object_id}");
            }

            $result = eval($module_config['code']);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("✅ GDM Rules Engine: Módulo '{$module_id}' ejecutado correctamente. Resultado: " . var_export($result, true));
            }

            // Limpiar caché del producto después de la ejecución
            wc_delete_product_transients($product->get_id());
            clean_post_cache($product->get_id());

        } catch (Exception $e) {
            error_log(sprintf(
                '❌ GDM Rules Engine: Error al ejecutar módulo "%s" en regla %d: %s',
                $module_id,
                $rule_id,
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Aplicar módulo de galería
     */
    private function apply_gallery_module($rule_id, $product) {
        $imagenes = get_post_meta($rule_id, '_gdm_galeria_imagenes', true) ?: [];
        if (empty($imagenes)) {
            return;
        }
        
        $posicion = get_post_meta($rule_id, '_gdm_galeria_posicion', true) ?: 'final';
        $posicion_numero = get_post_meta($rule_id, '_gdm_galeria_posicion_numero', true) ?: 1;
        
        $gallery_ids = $product->get_gallery_image_ids();
        
        switch ($posicion) {
            case 'inicio':
                $gallery_ids = array_merge($imagenes, $gallery_ids);
                break;
            case 'final':
                $gallery_ids = array_merge($gallery_ids, $imagenes);
                break;
            case 'posicion':
                array_splice($gallery_ids, max(0, $posicion_numero - 1), 0, $imagenes);
                break;
        }
        
        update_post_meta($product->get_id(), '_product_image_gallery', implode(',', $gallery_ids));
    }
    
    /**
     * Aplicar módulo de título
     */
    private function apply_title_module($rule_id, $product) {
        $accion = get_post_meta($rule_id, '_gdm_titulo_accion', true) ?: 'agregar_final';
        $titulo_actual = $product->get_name();
        $nuevo_titulo = $titulo_actual;
        
        switch ($accion) {
            case 'agregar_inicio':
                $texto = get_post_meta($rule_id, '_gdm_titulo_texto_agregar', true);
                $nuevo_titulo = $texto . $titulo_actual;
                break;
                
            case 'agregar_final':
                $texto = get_post_meta($rule_id, '_gdm_titulo_texto_agregar', true);
                $nuevo_titulo = $titulo_actual . $texto;
                break;
                
            case 'reemplazar':
                $buscar = get_post_meta($rule_id, '_gdm_titulo_texto_buscar', true);
                $reemplazar = get_post_meta($rule_id, '_gdm_titulo_texto_reemplazar', true);
                $case_sensitive = get_post_meta($rule_id, '_gdm_titulo_case_sensitive', true) === '1';
                
                if ($case_sensitive) {
                    $nuevo_titulo = str_replace($buscar, $reemplazar, $titulo_actual);
                } else {
                    $nuevo_titulo = str_ireplace($buscar, $reemplazar, $titulo_actual);
                }
                break;
                
            case 'reemplazar_todo':
                $nuevo_titulo = get_post_meta($rule_id, '_gdm_titulo_nuevo_completo', true);
                break;
                
            case 'regex':
                $patron = get_post_meta($rule_id, '_gdm_titulo_regex_patron', true);
                $reemplazo = get_post_meta($rule_id, '_gdm_titulo_regex_reemplazo', true);
                $result = @preg_replace($patron, $reemplazo, $titulo_actual);
                if ($result !== null) {
                    $nuevo_titulo = $result;
                }
                break;
        }
        
        if ($nuevo_titulo !== $titulo_actual) {
            wp_update_post([
                'ID' => $product->get_id(),
                'post_title' => $nuevo_titulo,
            ]);
            // Limpiar cache
            clean_post_cache($product->get_id());
        }
    }
    
    /**
     * Aplicar módulo de precio
     */
    private function apply_price_module($rule_id, $product) {
        $tipo = get_post_meta($rule_id, '_gdm_precio_tipo', true) ?: 'descuento_porcentaje';
        $valor = floatval(get_post_meta($rule_id, '_gdm_precio_valor', true));
        $aplicar_a = get_post_meta($rule_id, '_gdm_precio_aplicar_a', true) ?: 'activo';
        $redondeo = get_post_meta($rule_id, '_gdm_precio_redondeo', true) ?: 'ninguno';
        
        $precio_actual = floatval($product->get_price());
        $nuevo_precio = $precio_actual;
        
        // Calcular nuevo precio
        switch ($tipo) {
            case 'descuento_porcentaje':
                $nuevo_precio = $precio_actual * (1 - $valor / 100);
                break;
            case 'descuento_fijo':
                $nuevo_precio = max(0, $precio_actual - $valor);
                break;
            case 'adicional_porcentaje':
                $nuevo_precio = $precio_actual * (1 + $valor / 100);
                break;
            case 'adicional_fijo':
                $nuevo_precio = $precio_actual + $valor;
                break;
            case 'precio_fijo':
                $nuevo_precio = $valor;
                break;
        }
        
        // Aplicar redondeo
        $nuevo_precio = $this->apply_price_rounding($nuevo_precio, $redondeo);
        
        // Actualizar precio
        if ($aplicar_a === 'regular' || $aplicar_a === 'ambos' || $aplicar_a === 'activo') {
            update_post_meta($product->get_id(), '_regular_price', $nuevo_precio);
        }
        
        if ($aplicar_a === 'sale' || $aplicar_a === 'ambos') {
            update_post_meta($product->get_id(), '_sale_price', $nuevo_precio);
        }
        
        update_post_meta($product->get_id(), '_price', $nuevo_precio);
        
        // Limpiar cache
        wc_delete_product_transients($product->get_id());
    }
    
    /**
     * Aplicar redondeo de precio
     */
    private function apply_price_rounding($precio, $tipo) {
        switch ($tipo) {
            case 'arriba':
                return ceil($precio);
            case 'abajo':
                return floor($precio);
            case 'cercano':
                return round($precio);
            case 'terminacion_99':
                return floor($precio) + 0.99;
            case 'terminacion_95':
                return floor($precio) + 0.95;
            case 'terminacion_00':
                return round($precio);
            default:
                return $precio;
        }
    }
    
    /**
     * Aplicar módulo de destacado
     */
    private function apply_featured_module($rule_id, $product) {
        $accion = get_post_meta($rule_id, '_gdm_destacado_accion', true) ?: 'marcar';
        $is_featured = $product->get_featured();
        
        $nuevo_estado = $is_featured;
        
        switch ($accion) {
            case 'marcar':
                $nuevo_estado = true;
                break;
            case 'desmarcar':
                $nuevo_estado = false;
                break;
            case 'alternar':
                $nuevo_estado = !$is_featured;
                break;
        }
        
        if ($nuevo_estado !== $is_featured) {
            $product->set_featured($nuevo_estado);
            $product->save();
        }
    }
    
    // Filtros de WooCommerce (hooks)
    
    public function apply_gallery_rules($gallery_ids, $product) {
        // Ya procesado en process_all_applicable_rules
        return $gallery_ids;
    }
    
    public function apply_title_rules($title, $post_id) {
        // Ya procesado en process_all_applicable_rules
        return $title;
    }
    
    public function apply_product_title_rules($title, $product) {
        // Ya procesado en process_all_applicable_rules
        return $title;
    }
    
    public function apply_price_rules($price, $product) {
        // Ya procesado en process_all_applicable_rules
        return $price;
    }
    
    public function apply_regular_price_rules($price, $product) {
        // Ya procesado en process_all_applicable_rules
        return $price;
    }
    
    public function apply_sale_price_rules($price, $product) {
        // Ya procesado en process_all_applicable_rules
        return $price;
    }
    
    public function apply_featured_status($is_featured, $product) {
        // Ya procesado en process_all_applicable_rules
        return $is_featured;
    }
}

// Inicialización automática en frontend
GDM_Rules_Frontend::instance();