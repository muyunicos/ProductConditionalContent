<?php
/**
 * ARCHIVO: includes/class-gdm-admin.php
 * Administración con UX mejorada
 */
if (!defined('ABSPATH')) exit;

final class GDM_Admin
{
    private static $instance;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('save_post_descripcion_regla', [$this, 'save_rule_data'], 10, 2);
        // AJAX para obtener lista de reglas reutilizables
        add_action('wp_ajax_gdm_get_reusable_rules', [$this, 'ajax_get_reusable_rules']);
    }
    
    public function register_cpt() {
        $labels = [
            'name' => 'Reglas de Contenido',
            'singular_name' => 'Regla de Contenido',
            'menu_name' => 'Reglas de Contenido',
            'add_new_item' => 'Añadir Nueva Regla',
            'add_new' => 'Añadir Nueva',
            'edit_item' => 'Editar Regla',
            'all_items' => 'Todas las Reglas',
            'view_item' => 'Ver Regla',
            'search_items' => 'Buscar Reglas',
            'not_found' => 'No se encontraron reglas',
        ];
        
        $args = [
            'label' => 'Regla de Contenido',
            'labels' => $labels,
            'supports' => ['title'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-networking',
            'capability_type' => 'post',
            'hierarchical' => false,
            'show_in_rest' => false,
        ];
        
        register_post_type('descripcion_regla', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'gdm_rule_settings_box',
            'Configuración de la Regla',
            [$this, 'render_meta_box_html'],
            'descripcion_regla',
            'normal',
            'high'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (('post.php' === $hook || 'post-new.php' === $hook) && get_post_type() === 'descripcion_regla') {
            wp_enqueue_script('jquery-ui-sortable');
            
            wp_enqueue_script(
                'gdm-admin-script',
                GDM_PLUGIN_URL . 'assets/admin/admin-script.js',
                ['jquery', 'jquery-ui-sortable'],
                GDM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'gdm-admin-style',
                GDM_PLUGIN_URL . 'assets/admin/admin-style.css',
                [],
                GDM_VERSION
            );
            
            // Pasar datos al JS
            wp_localize_script('gdm-admin-script', 'gdmAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_admin_nonce'),
                'currentPostId' => get_the_ID(),
            ]);
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
                    <p><strong>ID de Regla:</strong> <code><?php echo esc_html($post->ID); ?></code></p>
                    <label for="gdm_prioridad"><strong>Prioridad:</strong></label>
                    <input type="number" id="gdm_prioridad" name="gdm_prioridad" value="<?php echo esc_attr($data['prioridad']); ?>" class="small-text" min="0" step="1" />
                    <p class="description">Número más bajo = Mayor prioridad.</p>
                    <hr>
                    <label><strong>Aplicar a:</strong></label>
                    <fieldset>
                        <label><input type="checkbox" name="gdm_aplicar_a[]" value="larga" <?php checked(in_array('larga', $data['aplicar_a'])); ?>> Descripción Larga</label><br>
                        <label><input type="checkbox" name="gdm_aplicar_a[]" value="corta" <?php checked(in_array('corta', $data['aplicar_a'])); ?>> Descripción Corta</label><br>
                        <label><input type="checkbox" name="gdm_aplicar_a[]" value="reutilizable" <?php checked(in_array('reutilizable', $data['aplicar_a'])); ?>> Regla Reutilizable</label>
                    </fieldset>
                    <p class="description">Las reglas reutilizables solo se activan mediante <code>[rule-id]</code>.</p>
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
                    
                    <div id="gdm_category_list_wrapper">
                        <input type="text" id="gdm_category_filter" class="gdm-filter-input" placeholder="🔍 Filtrar categorías..." />
                        <div class="gdm-scroll-list">
                            <?php
                            $product_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                            if (!is_wp_error($product_cats)) {
                                foreach ($product_cats as $cat) {
                                    echo '<label class="gdm-filterable-item" data-name="' . esc_attr(strtolower($cat->name)) . '">';
                                    echo '<input type="checkbox" name="gdm_categorias_objetivo[]" value="' . esc_attr($cat->term_id) . '" ' . checked(in_array((int)$cat->term_id, $data['categorias_objetivo']), true, false) . '> ';
                                    echo esc_html($cat->name);
                                    echo '</label><br>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <hr>
                    <strong>Tag Objetivo:</strong><br>
                    <label><input type="checkbox" id="gdm_cualquier_tag" name="gdm_cualquier_tag" value="1" <?php checked($data['cualquier_tag'], '1'); ?>> Aplicar con Cualquier Tag (o sin ninguno)</label>
                    
                    <div id="gdm_tag_list_wrapper">
                        <input type="text" id="gdm_tag_filter" class="gdm-filter-input" placeholder="🔍 Filtrar tags..." />
                        <div class="gdm-scroll-list">
                            <?php
                            $product_tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
                            if (!is_wp_error($product_tags)) {
                                foreach ($product_tags as $tag) {
                                    echo '<label class="gdm-filterable-item" data-name="' . esc_attr(strtolower($tag->name)) . '">';
                                    echo '<input type="checkbox" name="gdm_tags_objetivo[]" value="' . esc_attr($tag->term_id) . '" ' . checked(in_array((int)$tag->term_id, $data['tags_objetivo']), true, false) . '> ';
                                    echo esc_html($tag->name);
                                    echo '</label><br>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="gdm-full-width-container">
            <fieldset class="gdm-fieldset">
                <legend>Contenido Principal</legend>
                <div class="gdm-shortcode-buttons">
                    <p class="description" style="margin-bottom: 10px;">Haz clic para insertar comodines:</p>
                    <button type="button" class="button gdm-insert-shortcode" data-shortcode="[slug-prod]">
                        <span class="dashicons dashicons-tag"></span> [slug-prod]
                    </button>
                    <button type="button" class="button gdm-insert-shortcode" data-shortcode="[var-cond]">
                        <span class="dashicons dashicons-admin-settings"></span> [var-cond]
                    </button>
                    <button type="button" class="button gdm-insert-rule-id">
                        <span class="dashicons dashicons-admin-links"></span> [rule-id]
                    </button>
                </div>
                <?php 
                wp_editor($data['descripcion'], 'gdm_descripcion', [
                    'textarea_name' => 'gdm_descripcion',
                    'media_buttons' => false,
                    'textarea_rows' => 10,
                    'teeny' => false,
                    'tinymce' => true
                ]); 
                ?>
            </fieldset>
            
            <fieldset class="gdm-fieldset">
                <legend>Variantes por Condición</legend>
                <p class="description">Se aplicará la primera condición verdadera. Arrastra las filas para reordenar.</p>
                
                <table id="gdm-repeater-table" class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th class="check-col">
                                <input type="checkbox" id="gdm-select-all-variants" title="Seleccionar todo">
                            </th>
                            <th class="sort-col"></th>
                            <th class="cond-type-col">Condición</th>
                            <th class="cond-key-col">Clave / Tag</th>
                            <th class="cond-value-col">Valor</th>
                            <th class="action-col">Acción</th>
                            <th class="text-col">Texto</th>
                        </tr>
                    </thead>
                    <tbody id="gdm-repeater-tbody">
                        <?php
                        if (!empty($data['variantes']) && is_array($data['variantes'])) {
                            foreach ($data['variantes'] as $i => $variante) {
                                $this->render_variant_row($i, $variante);
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="gdm-repeater-actions">
                    <button type="button" class="button" id="gdm-add-repeater-row">
                        <span class="dashicons dashicons-plus-alt"></span> Añadir Variante
                    </button>
                    <button type="button" class="button button-secondary" id="gdm-delete-selected" disabled>
                        <span class="dashicons dashicons-trash"></span> Eliminar Seleccionadas
                    </button>
                    <span id="gdm-variant-counter" style="margin-left: 15px; font-weight: bold;"></span>
                </div>
                
                <script type="text/html" id="gdm-repeater-template">
                    <?php $this->render_variant_row('__INDEX__', []); ?>
                </script>
            </fieldset>
        </div>
        <?php
    }

    private function render_variant_row($index, $data) {
        $cond_type = $data['cond_type'] ?? 'tag';
        $cond_key = $data['cond_key'] ?? '';
        $cond_value = $data['cond_value'] ?? '';
        $action = $data['action'] ?? 'placeholder';
        $text = $data['text'] ?? '';
        
        // Determinar estados disabled
        $key_disabled = ($cond_type === 'default') ? 'disabled' : '';
        $value_disabled = ($cond_type === 'tag' || $cond_type === 'default') ? 'disabled' : '';
        ?>
        <tr class="gdm-repeater-row" data-index="<?php echo esc_attr($index); ?>">
            <td class="check-cell">
                <input type="checkbox" class="gdm-row-checkbox">
            </td>
            <td class="sort-handle"><span class="dashicons dashicons-menu"></span></td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_type]" class="gdm-cond-type">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>>Tag</option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>>Clave</option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>>Defecto</option>
                </select>
            </td>
            <td class="gdm-cond-key-cell">
                <input type="text" 
                       class="gdm-cond-key" 
                       name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_key]" 
                       value="<?php echo esc_attr($cond_key); ?>" 
                       placeholder="slug-del-tag" 
                       style="width: 100%;"
                       <?php echo $key_disabled; ?>>
            </td>
            <td class="gdm-cond-value-cell">
                <input type="text" 
                       class="gdm-cond-value" 
                       name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_value]" 
                       value="<?php echo esc_attr($cond_value); ?>" 
                       placeholder="valor" 
                       style="width: 100%;"
                       <?php echo $value_disabled; ?>>
            </td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][action]">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>Reemplaza [var-cond]</option>
                    <option value="reemplaza_todo" <?php selected($action, 'reemplaza_todo'); ?>>Reemplaza Descripción Completa</option>
                </select>
            </td>
            <td><textarea name="gdm_variantes[<?php echo esc_attr($index); ?>][text]" rows="2" style="width: 100%;"><?php echo esc_textarea($text); ?></textarea></td>
        </tr>
        <?php
    }

    public function save_rule_data($post_id, $post) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'descripcion_regla') {
            return;
        }

        $fields_to_save = [
            '_gdm_prioridad'          => isset($_POST['gdm_prioridad']) ? max(0, intval($_POST['gdm_prioridad'])) : 10,
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

        $aplicar_a = isset($_POST['gdm_aplicar_a']) && is_array($_POST['gdm_aplicar_a']) 
            ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) 
            : [];
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        
        $categorias = isset($_POST['gdm_categorias_objetivo']) && is_array($_POST['gdm_categorias_objetivo'])
            ? array_map('intval', $_POST['gdm_categorias_objetivo']) 
            : [];
        update_post_meta($post_id, '_gdm_categorias_objetivo', $categorias);
        
        $tags = isset($_POST['gdm_tags_objetivo']) && is_array($_POST['gdm_tags_objetivo'])
            ? array_map('intval', $_POST['gdm_tags_objetivo']) 
            : [];
        update_post_meta($post_id, '_gdm_tags_objetivo', $tags);

        $variantes_sanitizadas = [];
        if (isset($_POST['gdm_variantes']) && is_array($_POST['gdm_variantes'])) {
            foreach ($_POST['gdm_variantes'] as $variante) {
                if (!isset($variante['cond_type'])) {
                    continue;
                }
                
                if ($variante['cond_type'] !== 'default' && empty($variante['cond_key'])) {
                    continue;
                }
                
                $variantes_sanitizadas[] = [
                    'cond_type'    => sanitize_text_field($variante['cond_type']),
                    'cond_key'     => isset($variante['cond_key']) ? sanitize_text_field($variante['cond_key']) : '',
                    'cond_value'   => isset($variante['cond_value']) ? sanitize_text_field($variante['cond_value']) : '',
                    'action'       => isset($variante['action']) ? sanitize_text_field($variante['action']) : 'placeholder',
                    'text'         => isset($variante['text']) ? wp_kses_post($variante['text']) : '',
                ];
            }
        }
        update_post_meta($post_id, '_gdm_variantes', $variantes_sanitizadas);
    }
    
    /**
     * AJAX: Obtener lista de reglas reutilizables
     */
    public function ajax_get_reusable_rules() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        $current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
        
        $rules = get_posts([
            'post_type'      => 'descripcion_regla',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'exclude'        => [$current_post_id], // Excluir regla actual
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        
        $reusable_rules = [];
        foreach ($rules as $rule) {
            $aplicar_a = get_post_meta($rule->ID, '_gdm_aplicar_a', true) ?: [];
            if (in_array('reutilizable', $aplicar_a)) {
                $reusable_rules[] = [
                    'id'    => $rule->ID,
                    'title' => $rule->post_title,
                ];
            }
        }
        
        wp_send_json_success($reusable_rules);
    }
    
    private function get_rule_data($rule_id) {
        static $cache = [];
        
        if (isset($cache[$rule_id])) {
            return $cache[$rule_id];
        }

        $post = get_post($rule_id);
        if (!$post || $post->post_type !== 'descripcion_regla') {
            return [
                'prioridad' => 10,
                'aplicar_a' => [],
                'todas_categorias' => '0',
                'categorias_objetivo' => [],
                'cualquier_tag' => '0',
                'tags_objetivo' => [],
                'ubicacion' => 'reemplaza',
                'regla_final' => '0',
                'forzar_aplicacion' => '0',
                'descripcion' => '',
                'variantes' => [],
            ];
        }

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
        
        $cache[$rule_id] = $data;
        return $data;
    }
}