<?php
/**
 * Plugin Name:       Motor de Reglas de Contenido MuyUnicos
 * Description:       Aplica contenido dinámico a productos basado en un sistema de reglas avanzado.
 * Version:           3.5
 * Author:            Muy Únicos
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rule_Engine_Plugin
{
    private static $instance;
    private $processed_rules = [];

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        define('GDM_VERSION', '3.6');
        define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));
        
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('save_post_descripcion_regla', [$this, 'save_rule_data']);
        add_filter('the_content', [$this, 'run_engine_for_long_desc'], 20);
        add_filter('woocommerce_short_description', [$this, 'run_engine_for_short_desc'], 20);
    }
    
    public function register_cpt() {
        $labels = ['name' => 'Reglas de Contenido', 'singular_name' => 'Regla de Contenido', 'menu_name' => 'Reglas de Contenido', 'add_new_item' => 'Añadir Nueva Regla', 'add_new' => 'Añadir Nueva', 'edit_item' => 'Editar Regla'];
        $args = ['label' => 'Regla de Contenido', 'labels' => $labels, 'supports' => ['title'], 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 20, 'menu_icon' => 'dashicons-networking', 'capability_type' => 'post'];
        register_post_type('descripcion_regla', $args);
    }

    public function add_meta_boxes() {
        add_meta_box('gdm_rule_settings_box', 'Configuración de la Regla', [$this, 'render_meta_box_html'], 'descripcion_regla', 'normal', 'high');
    }

    public function enqueue_admin_scripts($hook) {
        if (('post.php' == $hook || 'post-new.php' == $hook) && get_post_type() === 'descripcion_regla') {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('gdm-admin-script', GDM_PLUGIN_URL . 'assets/admin-script.js', ['jquery', 'jquery-ui-sortable'], GDM_VERSION, true);
            wp_enqueue_style('gdm-admin-style', GDM_PLUGIN_URL . 'assets/admin-style.css', [], GDM_VERSION);
        }
    }

   public function render_meta_box_html($post) {
        wp_nonce_field('gdm_save_rule_data', 'gdm_nonce');
        $data = $this->get_rule_data($post->ID);
        ?>
        <div class="gdm-container">
            <div class="gdm-column">
                <fieldset class="gdm-fieldset">
                    <legend>Configuración General</legend>
                    <p><strong>ID de Regla:</strong> <code><?php echo $post->ID; ?></code></p>
                    <label for="gdm_prioridad"><strong>Prioridad:</strong></label>
                    <input type="number" id="gdm_prioridad" name="gdm_prioridad" value="<?php echo esc_attr($data['prioridad']); ?>" class="small-text" />
                    <p class="description">Número más bajo = Mayor prioridad.</p>
                    <hr>
                    <label><strong>Aplicar a:</strong></label>
                    <fieldset>
                        <label><input type="checkbox" name="gdm_aplicar_a[]" value="larga" <?php checked(in_array('larga', $data['aplicar_a'])); ?>> Descripción Larga</label><br>
                        <label><input type="checkbox" name="gdm_aplicar_a[]" value="corta" <?php checked(in_array('corta', $data['aplicar_a'])); ?>> Descripción Corta</label>
                    </fieldset>
                    <p class="description">Si no se marca nada, la regla solo funcionará con el comodín <code>[rule-id]</code>.</p>
                </fieldset>

                 <fieldset class="gdm-fieldset">
                    <legend>Opciones de Fusión</legend>
                    <strong>Ubicación:</strong>
                    <select name="gdm_ubicacion">
                        <option value="reemplaza" <?php selected($data['ubicacion'], 'reemplaza'); ?>>Reemplaza la descripción manual</option>
                        <option value="antes" <?php selected($data['ubicacion'], 'antes'); ?>>Añadir antes de la descripción manual</option>
                        <option value="despues" <?php selected($data['ubicacion'], 'despues'); ?>>Añadir después de la descripción manual</option>
                        <option value="solo_vacia" <?php selected($data['ubicacion'], 'solo_vacia'); ?>>Aplicar sólo si la descripción está vacía</option>
                    </select>
                    <hr>
                    <label><input type="checkbox" name="gdm_forzar_aplicacion" value="1" <?php checked($data['forzar_aplicacion'], '1'); ?>> <strong>Forzar Aplicación:</strong> Esta regla ignora la opción "Regla Final" de otras reglas de menor prioridad.</label><br>
                    <label><input type="checkbox" name="gdm_regla_final" value="1" <?php checked($data['regla_final'], '1'); ?>> <strong>Regla Final:</strong> Detiene el procesamiento de otras reglas con menor prioridad.</label>
                </fieldset>
            </div>
            <div class="gdm-column">
                <fieldset class="gdm-fieldset">
                    <legend>Ámbito de Aplicación (Scope)</legend>
                    <strong>Categoría Objetivo:</strong><br>
                    <label><input type="checkbox" id="gdm_todas_categorias" name="gdm_todas_categorias" value="1" <?php checked($data['todas_categorias'], '1'); ?>> Aplicar a Todas las Categorías</label>
                    <div id="gdm_category_list_wrapper" class="gdm-scroll-list">
                        <?php
                        $product_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                        foreach ($product_cats as $cat) {
                            echo '<label><input type="checkbox" name="gdm_categorias_objetivo[]" value="' . esc_attr($cat->term_id) . '" ' . checked(in_array($cat->term_id, $data['categorias_objetivo']), true, false) . '> ' . esc_html($cat->name) . '</label><br>';
                        }
                        ?>
                    </div>
                    <hr>
                    <strong>Tag Objetivo:</strong><br>
                     <label><input type="checkbox" id="gdm_cualquier_tag" name="gdm_cualquier_tag" value="1" <?php checked($data['cualquier_tag'], '1'); ?>> Aplicar con Cualquier Tag (o sin ninguno)</label>
                     <div id="gdm_tag_list_wrapper" class="gdm-scroll-list">
                        <?php
                        $product_tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
                        foreach ($product_tags as $tag) {
                            echo '<label><input type="checkbox" name="gdm_tags_objetivo[]" value="' . esc_attr($tag->term_id) . '" ' . checked(in_array($tag->term_id, $data['tags_objetivo']), true, false) . '> ' . esc_html($tag->name) . '</label><br>';
                        }
                        ?>
                     </div>
                </fieldset>
            </div>
        </div>

        <div class="gdm-full-width-container">
            <fieldset class="gdm-fieldset">
                <legend>Contenido Principal</legend>
                <p class="description">Comodines: <code>[slug-prod]</code>, <code>[var-cond]</code>, <code>[rule-id id="..."]</code></p>
                <?php wp_editor($data['descripcion'], 'gdm_descripcion', ['textarea_name' => 'gdm_descripcion', 'media_buttons' => false, 'textarea_rows' => 10]); ?>
            </fieldset>
            
            <fieldset class="gdm-fieldset">
                <legend>Variantes por Condición</legend>
                <p class="description">Se aplicará la primera condición verdadera. Arrastra las filas para reordenar.</p>
                <table id="gdm-repeater-table" class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th class="sort-col"></th>
                            <th class="cond-type-col">Condición</th>
                            <th class="cond-key-col">Clave / Tag</th>
                            <th class="cond-value-col">Valor (Opcional)</th>
                            <th class="action-col">Acción</th>
                            <th class="text-col">Texto</th>
                            <th class="actions-col"></th>
                        </tr>
                    </thead>
                    <tbody id="gdm-repeater-tbody">
                        <?php
                        if ($data['variantes'] && is_array($data['variantes'])) {
                            foreach ($data['variantes'] as $i => $variante) { $this->render_variant_row($i, $variante); }
                        }
                        ?>
                    </tbody>
                </table>
                <button type="button" class="button" id="gdm-add-repeater-row" style="margin-top:10px;">Añadir Variante</button>
                
                <script type="text/html" id="gdm-repeater-template">
                    <?php $this->render_variant_row('__INDEX__', []); ?>
                </script>

            </fieldset>
        </div>
        <?php
    }

    private function render_variant_row($index, $data) {
        $cond_type = $data['cond_type'] ?? 'tag'; $cond_key = $data['cond_key'] ?? ''; $cond_value = $data['cond_value'] ?? '';
        $action = $data['action'] ?? 'placeholder'; $text = $data['text'] ?? '';
        ?>
        <tr class="gdm-repeater-row">
            <td class="sort-handle"><span class="dashicons dashicons-menu"></span></td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_type]" class="gdm-cond-type">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>>Producto tiene Tag</option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>>Campo Personalizado</option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>>Por Defecto</option>
                </select>
            </td>
            <td class="gdm-cond-key-cell">
                <input type="text" class="gdm-cond-key" name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_key]" value="<?php echo esc_attr($cond_key); ?>" placeholder="slug-del-tag" style="width: 100%;">
            </td>
            <td class="gdm-cond-value-cell">
                <input type="text" class="gdm-cond-value" name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_value]" value="<?php echo esc_attr($cond_value); ?>" placeholder="valor" style="width: 100%;">
            </td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][action]">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>Reemplaza [var-cond]</option>
                    <option value="reemplaza_todo" <?php selected($action, 'reemplaza_todo'); ?>>Reemplaza Descripción Completa</option>
                </select>
            </td>
            <td><textarea name="gdm_variantes[<?php echo esc_attr($index); ?>][text]" rows="2" style="width: 100%;"><?php echo esc_textarea($text); ?></textarea></td>
            <td class="actions-cell"><button type="button" class="button gdm-remove-repeater-row">Eliminar</button></td>
        </tr>
        <?php
    }


    private function render_variant_row($index, $data) {
        $cond_type = $data['cond_type'] ?? 'tag'; $cond_key = $data['cond_key'] ?? ''; $cond_value = $data['cond_value'] ?? '';
        $action = $data['action'] ?? 'placeholder'; $text = $data['text'] ?? '';
        ?>
        <tr class="gdm-repeater-row">
            <td class="sort-handle"><span class="dashicons dashicons-menu"></span></td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_type]" class="gdm-cond-type">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>>Producto tiene Tag</option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>>Campo Personalizado</option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>>Por Defecto</option>
                </select>
            </td>
            <td class="gdm-cond-key-cell">
                <input type="text" class="gdm-cond-key" name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_key]" value="<?php echo esc_attr($cond_key); ?>" placeholder="slug-del-tag" style="width: 100%;">
            </td>
            <td class="gdm-cond-value-cell">

                <input type="text" class="gdm-cond-value" name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_value]" value="<?php echo esc_attr($cond_value); ?>" placeholder="valor" style="width: 100%;">

            </td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][action]">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>Reemplaza [var-cond]</option>
                    <option value="reemplaza_todo" <?php selected($action, 'reemplaza_todo'); ?>>Reemplaza Descripción Completa</option>
                </select>
            </td>
            <td><textarea name="gdm_variantes[<?php echo esc_attr($index); ?>][text]" rows="2" style="width: 100%;"><?php echo esc_textarea($text); ?></textarea></td>
            <td class="actions-cell"><button type="button" class="button gdm-remove-repeater-row">Eliminar</button></td>
        </tr>
        <?php
    }

   public function save_rule_data($post_id) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data') || !current_user_can('edit_post', $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $fields_to_save = [
            '_gdm_prioridad'          => isset($_POST['gdm_prioridad']) ? intval($_POST['gdm_prioridad']) : 10,
            '_gdm_ubicacion'          => isset($_POST['gdm_ubicacion']) ? sanitize_text_field($_POST['gdm_ubicacion']) : 'reemplaza',
            '_gdm_todas_categorias'   => isset($_POST['gdm_todas_categorias']) ? '1' : '0',
            '_gdm_cualquier_tag'      => isset($_POST['gdm_cualquier_tag']) ? '1' : '0',
            '_gdm_regla_final'        => isset($_POST['gdm_regla_final']) ? '1' : '0',
            '_gdm_forzar_aplicacion'  => isset($_POST['gdm_forzar_aplicacion']) ? '1' : '0',
            '_gdm_descripcion'        => isset($_POST['gdm_descripcion']) ? wp_kses_post($_POST['gdm_descripcion']) : ''
        ];
        foreach ($fields_to_save as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        $aplicar_a = isset($_POST['gdm_aplicar_a']) ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) : [];
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        
        $categorias = isset($_POST['gdm_categorias_objetivo']) ? array_map('intval', $_POST['gdm_categorias_objetivo']) : [];
        update_post_meta($post_id, '_gdm_categorias_objetivo', $categorias);
        
        $tags = isset($_POST['gdm_tags_objetivo']) ? array_map('intval', $_POST['gdm_tags_objetivo']) : [];
        update_post_meta($post_id, '_gdm_tags_objetivo', $tags);


        $variantes_sanitizadas = [];
        if (isset($_POST['gdm_variantes']) && is_array($_POST['gdm_variantes'])) {
            foreach ($_POST['gdm_variantes'] as $variante) {
                if ($variante['cond_type'] !== 'default' && empty($variante['cond_key'])) continue;
                $variantes_sanitizadas[] = [
                    'cond_type'    => sanitize_text_field($variante['cond_type']),
                    'cond_key'     => sanitize_text_field($variante['cond_key']),
                    'cond_value'   => sanitize_text_field($variante['cond_value']),
                    'action'       => sanitize_text_field($variante['action']),
                    'text'         => wp_kses_post($variante['text']),
                ];
            }
        }
        update_post_meta($post_id, '_gdm_variantes', $variantes_sanitizadas);
    }
    
    
    // =========================================================================
    // 3. MOTOR DEL FRONTEND
    // =========================================================================

    public function run_engine_for_long_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'larga');
    }

    public function run_engine_for_short_desc($content) {
        if (!is_singular('product')) return $content;
        return $this->apply_rules($content, 'corta');
    }

    private function get_rule_data($rule_id) {
        static $cache = [];
        if (isset($cache[$rule_id])) return $cache[$rule_id];

        $data = [
            'prioridad'           => get_post_meta($rule_id, '_gdm_prioridad', true) ?: 10,
            'aplicar_a'           => get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [],
            'todas_categorias'    => get_post_meta($rule_id, '_gdm_todas_categorias', true),
            'categorias_objetivo' => get_post_meta($rule_id, '_gdm_categorias_objetivo', true) ?: [],
            'cualquier_tag'       => get_post_meta($rule_id, '_gdm_cualquier_tag', true),
            'tags_objetivo'       => get_post_meta($rule_id, '_gdm_tags_objetivo', true) ?: [],
            'ubicacion'           => get_post_meta($rule_id, '_gdm_ubicacion', true) ?: 'reemplaza',
            'regla_final'         => get_post_meta($rule_id, '_gdm_regla_final', true),
            'forzar_aplicacion'   => get_post_meta($rule_id, '_gdm_forzar_aplicacion', true),
            'descripcion'         => get_post_meta($rule_id, '_gdm_descripcion', true),
            'variantes'           => get_post_meta($rule_id, '_gdm_variantes', true) ?: [],
        ];
        $cache[$rule_id] = $data;
        return $data;
    }
    
    private function process_shortcodes($content, $product, $variant_text) {
        $content = str_replace('[slug-prod]', ucwords(str_replace('-', ' ', $product->get_slug())), $content);
        $content = str_replace('[var-cond]', $variant_text, $content);
        $content = preg_replace_callback('/\[rule-id id="(\d+)"\]/', function($matches) use ($product) {
            $rule_id = intval($matches[1]);
            if (in_array($rule_id, $this->processed_rules) || count($this->processed_rules) > 5) return '';
            
            $this->processed_rules[] = $rule_id;
            $rule_data = $this->get_rule_data($rule_id);
            
            if (!$rule_data) return '';
            
            return $this->process_rule_content($rule_data, $product);
        }, $content);

        return $content;
    }

    private function apply_rules($initial_content, $type) {
        global $product;
        if (!is_a($product, 'WC_Product')) return $initial_content;

        $all_rules = get_posts(['post_type' => 'descripcion_regla', 'posts_per_page' => -1, 'post_status' => 'publish', 'meta_key' => '_gdm_prioridad', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        $matching_rules = array_filter($all_rules, function($rule) use ($product) {
            return $this->is_rule_in_scope($rule->ID, $product);
        });

        if (empty($matching_rules)) return $initial_content;

        $forced_rules = array_filter($matching_rules, function($rule) {
            return (bool) get_post_meta($rule->ID, '_gdm_forzar_aplicacion', true);
        });

        $rules_to_process = !empty($forced_rules) ? $forced_rules : $matching_rules;
        
        $current_content = $initial_content;

        foreach ($rules_to_process as $rule) {
            $rule_data = $this->get_rule_data($rule->ID);

            if (empty($rule_data['aplicar_a']) || !in_array($type, $rule_data['aplicar_a'])) {
                continue;
            }

            if ($rule_data['ubicacion'] === 'solo_vacia') {
                if (!empty(trim($initial_content))) {
                    continue; 
                }
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
                    $current_content = $generated_content;
                    break;
            }

            if ($rule_data['regla_final']) {
                break; 
            }
        }
        
        return do_shortcode(wpautop($current_content));
    }

    private function is_rule_in_scope($rule_id, $product) {
        $data = $this->get_rule_data($rule_id);
        
        $category_match = $data['todas_categorias'] || count(array_intersect($product->get_category_ids(), $data['categorias_objetivo'])) > 0;
        
        $tag_match = $data['cualquier_tag'] || count(array_intersect($product->get_tag_ids(), $data['tags_objetivo'])) > 0;

        return $category_match && $tag_match;
    }

    private function process_rule_content($rule_data, $product) {
        $content = $rule_data['descripcion'];
        
        $variant_text = '';
        foreach ($rule_data['variantes'] as $variant) {
            $condition_met = false;
            switch ($variant['cond_type']) {
                case 'tag':
                    $condition_met = has_term($variant['cond_key'], 'product_tag', $product->get_id());
                    break;
                case 'meta':
                    $meta_value = get_post_meta($product->get_id(), $variant['cond_key'], true);
                    $condition_met = ($meta_value == $variant['cond_value']);
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
        
        return $this->process_shortcodes($content, $product, $variant_text);
    }
}

GDM_Rule_Engine_Plugin::instance();