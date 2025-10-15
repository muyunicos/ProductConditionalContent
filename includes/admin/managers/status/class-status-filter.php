<?php
/**
 * Componente: Filtros y Vistas del Listado de Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Filters {
    
    public function __construct() {
        // Filtros de listado
        add_action('restrict_manage_posts', [$this, 'add_status_filter']);
        add_filter('views_edit-gdm_regla', [$this, 'custom_status_views']);
        add_action('pre_get_posts', [$this, 'filter_by_status']);
        
        // Quick Edit
        add_action('quick_edit_custom_box', [$this, 'quick_edit_fields'], 10, 2);
        add_action('save_post_gdm_regla', [$this, 'save_quick_edit'], 35, 2);
        
        // AJAX
        add_action('wp_ajax_gdm_get_regla_data', [$this, 'ajax_get_regla_data']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Vistas del listado
     */
    public function custom_status_views($views) {
        $counts = $this->get_status_counts();
        
        $class = (!isset($_GET['post_status'])) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=gdm_regla'),
            $class,
            __('Todas', 'product-conditional-content'),
            $counts['total']
        );
        
        if ($counts['habilitada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'habilitada') ? 'current' : '';
            $views['habilitada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=habilitada'),
                $class,
                __('Habilitadas', 'product-conditional-content'),
                $counts['habilitada']
            );
        }
        
        if ($counts['deshabilitada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'deshabilitada') ? 'current' : '';
            $views['deshabilitada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=deshabilitada'),
                $class,
                __('Deshabilitadas', 'product-conditional-content'),
                $counts['deshabilitada']
            );
        }
        
        unset($views['publish'], $views['draft'], $views['pending']);
        
        return $views;
    }
    
    /**
     * Contar estados
     */
    private function get_status_counts() {
        global $wpdb;
        
        $counts = [
            'habilitada' => 0,
            'deshabilitada' => 0,
            'total' => 0,
        ];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status, COUNT(*) as count 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_status IN ('habilitada', 'deshabilitada', 'publish')
                GROUP BY post_status",
                'gdm_regla'
            )
        );
        
        foreach ($results as $row) {
            $status = ($row->post_status === 'publish') ? 'habilitada' : $row->post_status;
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row->count;
            }
            $counts['total'] += (int) $row->count;
        }
        
        return $counts;
    }
    
    /**
     * Filtrar por estado
     */
    public function filter_by_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow !== 'edit.php' || $typenow !== 'gdm_regla' || !$query->is_main_query()) {
            return;
        }
        
        if (!isset($_GET['post_status'])) {
            $query->set('post_status', ['habilitada', 'deshabilitada', 'publish']);
        }
    }
    
    /**
     * Agregar filtro de estado
     */
    public function add_status_filter() {
        global $typenow;
        
        if ($typenow !== 'gdm_regla') {
            return;
        }
        
        $current_status = isset($_GET['post_status']) ? $_GET['post_status'] : '';
        
        ?>
        <select name="post_status">
            <option value=""><?php _e('Todos los estados', 'product-conditional-content'); ?></option>
            <option value="habilitada" <?php selected($current_status, 'habilitada'); ?>>
                <?php _e('Habilitada', 'product-conditional-content'); ?>
            </option>
            <option value="deshabilitada" <?php selected($current_status, 'deshabilitada'); ?>>
                <?php _e('Deshabilitada', 'product-conditional-content'); ?>
            </option>
        </select>
        <?php
    }
    
    /**
     * Quick Edit
     */
    public function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla' || $column_name !== 'gdm_estado') {
            return;
        }
        
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <input type="checkbox" name="gdm_quick_toggle" value="1">
                    <span class="checkbox-title"><?php _e('Habilitar regla', 'product-conditional-content'); ?></span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Guardar Quick Edit
     */
    public function save_quick_edit($post_id, $post) {
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $is_enabled = isset($_POST['gdm_quick_toggle']) && $_POST['gdm_quick_toggle'] === '1';
        $new_status = $is_enabled ? 'habilitada' : 'deshabilitada';
        
        remove_action('save_post_gdm_regla', [$this, 'save_quick_edit'], 35);
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);
        add_action('save_post_gdm_regla', [$this, 'save_quick_edit'], 35, 2);
    }
    
    /**
     * AJAX: Obtener datos de regla
     */
    public function ajax_get_regla_data() {
        check_ajax_referer('gdm_quick_edit_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error();
        }
        
        $status = get_post_status($post_id);
        $is_enabled = ($status === 'habilitada' || $status === 'publish');
        
        wp_send_json_success([
            'status' => $status,
            'enabled' => $is_enabled,
            'programar' => get_post_meta($post_id, '_gdm_programar', true),
            'fecha_inicio' => get_post_meta($post_id, '_gdm_fecha_inicio', true),
            'fecha_fin' => get_post_meta($post_id, '_gdm_fecha_fin', true),
            'habilitar_fin' => get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true),
        ]);
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'edit.php') {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_script(
            'gdm-regla-quick-edit',
            GDM_PLUGIN_URL . 'assets/admin/js/components/rules-quick-edit.js',
            ['jquery', 'inline-edit-post'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-regla-quick-edit', 'gdmQuickEdit', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_quick_edit_nonce'),
        ]);
    }
}