<?php
/**
 * Metabox de Configuración de Reglas de Contenido
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Reglas_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_gdm_get_reusable_rules', [__CLASS__, 'ajax_get_reusable_rules']);
    }

    /**
     * Encolar scripts y estilos
     */
    public static function enqueue_assets($hook) {
        $screen = get_current_screen();
        if (!in_array($screen->id, ['gdm_regla', 'gdm_opcion'])) {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_script(
            'gdm-admin',
            GDM_PLUGIN_URL . 'assets/admin/gdm-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            GDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'gdm-admin',
            GDM_PLUGIN_URL . 'assets/admin/gdm-admin.css',
            [],
            GDM_VERSION
        );
        
        wp_localize_script('gdm-admin', 'gdmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_admin_nonce'),
            'currentPostId' => get_the_ID(),
            'i18n' => [
                'deleteConfirm' => __('¿Eliminar la variante seleccionada?', 'product-conditional-content'),
                'deleteMultipleConfirm' => __('¿Eliminar %d variantes seleccionadas?', 'product-conditional-content'),
                'selectRule' => __('Seleccionar Regla Reutilizable', 'product-conditional-content'),
                'cancel' => __('Cancelar', 'product-conditional-content'),
                'insert' => __('Insertar', 'product-conditional-content'),
            ]
        ]);
    }

    /**
     * Registrar metabox
     */
    public static function add_metabox() {
        add_meta_box(
            'gdm_regla_config',
            __('Configuración de la Regla', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
    }

    /**
     * Renderizar metabox completo
     */
    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_rule_data', 'gdm_nonce');
        
        $data = self::get_rule_data($post->ID);
        ?>
        <div class="gdm-container">
            <!-- COLUMNA IZQUIERDA -->
            <div class="gdm-column">
                <fieldset class="gdm-fieldset">
                    <legend><?php _e('Configuración General', 'product-conditional-content'); ?></legend>
                    
                    <p><strong><?php _e('ID de Regla:', 'product-conditional-content'); ?></strong> <code><?php echo esc_html($post->ID); ?></code></p>
                    
                    <label for="gdm_prioridad">
                        <strong><?php _e('Prioridad:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="number" 
                           id="gdm_prioridad" 
                           name="gdm_prioridad" 
                           value="<?php echo esc_attr($data['prioridad']); ?>" 
                           class="small-text" 
                           min="0" 
                           step="1" />
                    <p class="description"><?php _e('Número más bajo = Mayor prioridad.', 'product-conditional-content'); ?></p>
                    
                    <hr>
                    
                    <label><strong><?php _e('Aplicar a:', 'product-conditional-content'); ?></strong></label>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="gdm_aplicar_a[]" 
                                   value="larga" 
                                   <?php checked(in_array('larga', $data['aplicar_a'])); ?>>
                            <?php _e('Descripción Larga', 'product-conditional-content'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" 
                                   name="gdm_aplicar_a[]" 
                                   value="corta" 
                                   <?php checked(in_array('corta', $data['aplicar_a'])); ?>>
                            <?php _e('Descripción Corta', 'product-conditional-content'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" 
                                   name="gdm_aplicar_a[]" 
                                   value="reutilizable" 
                                   <?php checked(in_array('reutilizable', $data['aplicar_a'])); ?>>
                            <?php _e('Regla Reutilizable', 'product-conditional-content'); ?>
                        </label>
                    </fieldset>
                    <p class="description"><?php _e('Las reglas reutilizables solo se activan mediante [rule-id].', 'product-conditional-content'); ?></p>
                </fieldset>

                <fieldset class="gdm-fieldset">
                    <legend><?php _e('Opciones de Fusión', 'product-conditional-content'); ?></legend>
                    
                    <strong><?php _e('Ubicación:', 'product-conditional-content'); ?></strong>
                    <select name="gdm_ubicacion" class="widefat">
                        <option value="reemplaza" <?php selected($data['ubicacion'], 'reemplaza'); ?>>
                            <?php _e('Reemplaza la descripción manual', 'product-conditional-content'); ?>
                        </option>
                        <option value="antes" <?php selected($data['ubicacion'], 'antes'); ?>>
                            <?php _e('Añadir antes de la descripción manual', 'product-conditional-content'); ?>
                        </option>
                        <option value="despues" <?php selected($data['ubicacion'], 'despues'); ?>>
                            <?php _e('Añadir después de la descripción manual', 'product-conditional-content'); ?>
                        </option>
                        <option value="solo_vacia" <?php selected($data['ubicacion'], 'solo_vacia'); ?>>
                            <?php _e('Aplicar sólo si la descripción está vacía', 'product-conditional-content'); ?>
                        </option>
                    </select>
                    
                    <hr>
                    
                    <label>
                        <input type="checkbox" 
                               name="gdm_forzar_aplicacion" 
                               value="1" 
                               <?php checked($data['forzar_aplicacion'], '1'); ?>>
                        <strong><?php _e('Forzar Aplicación:', 'product-conditional-content'); ?></strong>
                        <?php _e('Ignora "Regla Final" de otras reglas.', 'product-conditional-content'); ?>
                    </label><br>
                    
                    <label>
                        <input type="checkbox" 
                               name="gdm_regla_final" 
                               value="1" 
                               <?php checked($data['regla_final'], '1'); ?>>
                        <strong><?php _e('Regla Final:', 'product-conditional-content'); ?></strong>
                        <?php _e('Detiene procesamiento de otras reglas.', 'product-conditional-content'); ?>
                    </label>
                </fieldset>
            </div>

            <!-- COLUMNA DERECHA -->
            <div class="gdm-column">
                <fieldset class="gdm-fieldset">
                    <legend><?php _e('Ámbito de Aplicación', 'product-conditional-content'); ?></legend>
                    
                    <strong><?php _e('Categoría Objetivo:', 'product-conditional-content'); ?></strong><br>
                    <label>
                        <input type="checkbox" 
                               id="gdm_todas_categorias" 
                               name="gdm_todas_categorias" 
                               value="1" 
                               <?php checked($data['todas_categorias'], '1'); ?>>
                        <?php _e('Aplicar a Todas las Categorías', 'product-conditional-content'); ?>
                    </label>
                    
                    <div id="gdm_category_list_wrapper">
                        <input type="text" 
                               id="gdm_category_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Filtrar categorías...', 'product-conditional-content'); ?>" />
                        <div class="gdm-scroll-list">
                            <?php
                            $product_cats = get_terms([
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false
                            ]);
                            if (!is_wp_error($product_cats) && !empty($product_cats)) {
                                foreach ($product_cats as $cat) {
                                    printf(
                                        '<label class="gdm-filterable-item" data-name="%s"><input type="checkbox" name="gdm_categorias_objetivo[]" value="%d" %s> %s</label><br>',
                                        esc_attr(strtolower($cat->name)),
                                        esc_attr($cat->term_id),
                                        checked(in_array((int)$cat->term_id, $data['categorias_objetivo']), true, false),
                                        esc_html($cat->name)
                                    );
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <strong><?php _e('Tag Objetivo:', 'product-conditional-content'); ?></strong><br>
                    <label>
                        <input type="checkbox" 
                               id="gdm_cualquier_tag" 
                               name="gdm_cualquier_tag" 
                               value="1" 
                               <?php checked($data['cualquier_tag'], '1'); ?>>
                        <?php _e('Aplicar con Cualquier Tag', 'product-conditional-content'); ?>
                    </label>
                    
                    <div id="gdm_tag_list_wrapper">
                        <input type="text" 
                               id="gdm_tag_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Filtrar tags...', 'product-conditional-content'); ?>" />
                        <div class="gdm-scroll-list">
                            <?php
                            $product_tags = get_terms([
                                'taxonomy' => 'product_tag',
                                'hide_empty' => false
                            ]);
                            if (!is_wp_error($product_tags) && !empty($product_tags)) {
                                foreach ($product_tags as $tag) {
                                    printf(
                                        '<label class="gdm-filterable-item" data-name="%s"><input type="checkbox" name="gdm_tags_objetivo[]" value="%d" %s> %s</label><br>',
                                        esc_attr(strtolower($tag->name)),
                                        esc_attr($tag->term_id),
                                        checked(in_array((int)$tag->term_id, $data['tags_objetivo']), true, false),
                                        esc_html($tag->name)
                                    );
                                }
                            }
                            ?>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="gdm-full-width-container">
            <fieldset class="gdm-fieldset">
                <legend><?php _e('Contenido Principal', 'product-conditional-content'); ?></legend>
                
                <div class="gdm-shortcode-buttons">
                    <p class="description" style="margin-bottom: 10px;">
                        <?php _e('Haz clic para insertar comodines:', 'product-conditional-content'); ?>
                    </p>
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
                    'tinymce' => true,
                    'quicktags' => true
                ]); 
                ?>
            </fieldset>
            
            <!-- VARIANTES -->
            <fieldset class="gdm-fieldset">
                <legend><?php _e('Variantes por Condición', 'product-conditional-content'); ?></legend>
                <p class="description">
                    <?php _e('Se aplicará la primera condición verdadera. Arrastra las filas para reordenar.', 'product-conditional-content'); ?>
                </p>
                
                <table id="gdm-repeater-table" class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th class="check-col">
                                <input type="checkbox" id="gdm-select-all-variants">
                            </th>
                            <th class="sort-col"></th>
                            <th class="cond-type-col"><?php _e('Condición', 'product-conditional-content'); ?></th>
                            <th class="cond-key-col"><?php _e('Clave/Tag', 'product-conditional-content'); ?></th>
                            <th class="cond-value-col"><?php _e('Valor', 'product-conditional-content'); ?></th>
                            <th class="action-col"><?php _e('Acción', 'product-conditional-content'); ?></th>
                            <th class="text-col"><?php _e('Texto', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdm-repeater-tbody">
                        <?php
                        if (!empty($data['variantes']) && is_array($data['variantes'])) {
                            foreach ($data['variantes'] as $i => $variante) {
                                self::render_variant_row($i, $variante);
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <div class="gdm-repeater-actions">
                    <button type="button" class="button" id="gdm-add-repeater-row">
                        <span class="dashicons dashicons-plus-alt"></span> 
                        <?php _e('Añadir Variante', 'product-conditional-content'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="gdm-delete-selected" disabled>
                        <span class="dashicons dashicons-trash"></span> 
                        <?php _e('Eliminar Seleccionadas', 'product-conditional-content'); ?>
                    </button>
                    <span id="gdm-variant-counter"></span>
                </div>
                
                <script type="text/html" id="gdm-repeater-template">
                    <?php self::render_variant_row('__INDEX__', []); ?>
                </script>
            </fieldset>
        </div>
        
        <div id="gdm-rule-modal-overlay" style="display:none;"></div>
        <?php
    }

    /**
     * Renderizar fila de variante
     */
    private static function render_variant_row($index, $data) {
        $cond_type = $data['cond_type'] ?? 'tag';
        $cond_key = $data['cond_key'] ?? '';
        $cond_value = $data['cond_value'] ?? '';
        $action = $data['action'] ?? 'placeholder';
        $text = $data['text'] ?? '';
        
        $key_disabled = ($cond_type === 'default') ? 'disabled' : '';
        $value_disabled = ($cond_type === 'tag' || $cond_type === 'default') ? 'disabled' : '';
        ?>
        <tr class="gdm-repeater-row" data-index="<?php echo esc_attr($index); ?>">
            <td class="check-cell">
                <input type="checkbox" class="gdm-row-checkbox">
            </td>
            <td class="sort-handle">
                <span class="dashicons dashicons-menu"></span>
            </td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_type]" class="gdm-cond-type">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>><?php _e('Tag', 'product-conditional-content'); ?></option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>><?php _e('Clave', 'product-conditional-content'); ?></option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>><?php _e('Defecto', 'product-conditional-content'); ?></option>
                </select>
            </td>
            <td>
                <input type="text" 
                       class="gdm-cond-key" 
                       name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_key]" 
                       value="<?php echo esc_attr($cond_key); ?>" 
                       placeholder="slug-del-tag" 
                       style="width:100%;"
                       <?php echo $key_disabled; ?>>
            </td>
            <td>
                <input type="text" 
                       class="gdm-cond-value" 
                       name="gdm_variantes[<?php echo esc_attr($index); ?>][cond_value]" 
                       value="<?php echo esc_attr($cond_value); ?>" 
                       placeholder="valor" 
                       style="width:100%;"
                       <?php echo $value_disabled; ?>>
            </td>
            <td>
                <select name="gdm_variantes[<?php echo esc_attr($index); ?>][action]">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>
                        <?php _e('Reemplaza [var-cond]', 'product-conditional-content'); ?>
                    </option>
                    <option value="reemplaza_todo" <?php selected($action, 'reemplaza_todo'); ?>>
                        <?php _e('Reemplaza Todo', 'product-conditional-content'); ?>
                    </option>
                </select>
            </td>
            <td>
                <textarea name="gdm_variantes[<?php echo esc_attr($index); ?>][text]" 
                          rows="2" 
                          style="width:100%;"><?php echo esc_textarea($text); ?></textarea>
            </td>
        </tr>
        <?php
    }

    /**
     * Guardar datos
     */
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'gdm_regla') {
            return;
        }

        $fields_to_save = [
            '_gdm_prioridad' => isset($_POST['gdm_prioridad']) ? max(0, intval($_POST['gdm_prioridad'])) : 10,
            '_gdm_ubicacion' => isset($_POST['gdm_ubicacion']) ? sanitize_text_field($_POST['gdm_ubicacion']) : 'reemplaza',
            '_gdm_todas_categorias' => isset($_POST['gdm_todas_categorias']) ? '1' : '0',
            '_gdm_cualquier_tag' => isset($_POST['gdm_cualquier_tag']) ? '1' : '0',
            '_gdm_regla_final' => isset($_POST['gdm_regla_final']) ? '1' : '0',
            '_gdm_forzar_aplicacion' => isset($_POST['gdm_forzar_aplicacion']) ? '1' : '0',
            '_gdm_descripcion' => isset($_POST['gdm_descripcion']) ? wp_kses_post($_POST['gdm_descripcion']) : ''
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
                    'cond_type' => sanitize_text_field($variante['cond_type']),
                    'cond_key' => isset($variante['cond_key']) ? sanitize_text_field($variante['cond_key']) : '',
                    'cond_value' => isset($variante['cond_value']) ? sanitize_text_field($variante['cond_value']) : '',
                    'action' => isset($variante['action']) ? sanitize_text_field($variante['action']) : 'placeholder',
                    'text' => isset($variante['text']) ? wp_kses_post($variante['text']) : '',
                ];
            }
        }
        update_post_meta($post_id, '_gdm_variantes', $variantes_sanitizadas);
    }
    
    /**
     * AJAX: Reglas reutilizables
     */
    public static function ajax_get_reusable_rules() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        $current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
        
        $rules = get_posts([
            'post_type' => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'exclude' => [$current_post_id],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        $reusable_rules = [];
        foreach ($rules as $rule) {
            $aplicar_a = get_post_meta($rule->ID, '_gdm_aplicar_a', true) ?: [];
            if (in_array('reutilizable', $aplicar_a)) {
                $reusable_rules[] = [
                    'id' => $rule->ID,
                    'title' => $rule->post_title,
                ];
            }
        }
        
        wp_send_json_success($reusable_rules);
    }
    
    /**
     * Obtener datos de regla
     */
    private static function get_rule_data($rule_id) {
        static $cache = [];
        
        if (isset($cache[$rule_id])) {
            return $cache[$rule_id];
        }

        $data = [
            'prioridad' => (int) (get_post_meta($rule_id, '_gdm_prioridad', true) ?: 10),
            'aplicar_a' => get_post_meta($rule_id, '_gdm_aplicar_a', true) ?: [],
            'todas_categorias' => get_post_meta($rule_id, '_gdm_todas_categorias', true),
            'categorias_objetivo' => array_map('intval', get_post_meta($rule_id, '_gdm_categorias_objetivo', true) ?: []),
            'cualquier_tag' => get_post_meta($rule_id, '_gdm_cualquier_tag', true),
            'tags_objetivo' => array_map('intval', get_post_meta($rule_id, '_gdm_tags_objetivo', true) ?: []),
            'ubicacion' => get_post_meta($rule_id, '_gdm_ubicacion', true) ?: 'reemplaza',
            'regla_final' => get_post_meta($rule_id, '_gdm_regla_final', true),
            'forzar_aplicacion' => get_post_meta($rule_id, '_gdm_forzar_aplicacion', true),
            'descripcion' => get_post_meta($rule_id, '_gdm_descripcion', true),
            'variantes' => get_post_meta($rule_id, '_gdm_variantes', true) ?: [],
        ];
        
        $cache[$rule_id] = $data;
        return $data;
    }
}

GDM_Reglas_Metabox::init();