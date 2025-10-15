<?php
/**
 * Componente: Gestión de Columnas del Listado
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Columns {
    
    public function __construct() {
        add_filter('manage_gdm_regla_posts_columns', [$this, 'custom_columns']);
        add_action('manage_gdm_regla_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_filter('manage_edit-gdm_regla_sortable_columns', [$this, 'sortable_columns']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Columnas personalizadas
     */
    public function custom_columns($columns) {
        $new_columns = [];
        
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        $new_columns['title'] = __('Nombre', 'product-conditional-content');
        $new_columns['gdm_toggle'] = __('ON/OFF', 'product-conditional-content');
        $new_columns['aplica_a'] = __('Aplica a', 'product-conditional-content');
        $new_columns['gdm_estado'] = __('Estado', 'product-conditional-content');
        $new_columns['fechas'] = __('Programación', 'product-conditional-content');
        
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        
        return $new_columns;
    }
    
    /**
     * Contenido de columnas personalizadas
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'gdm_toggle':
                $this->render_toggle_column($post_id);
                break;
                
            case 'aplica_a':
                $this->render_aplica_a_column($post_id);
                break;
                
            case 'gdm_estado':
                $this->render_estado_column($post_id);
                break;
                
            case 'fechas':
                $this->render_fechas_column($post_id);
                break;
        }
    }
    
    /**
     * Renderizar columna de toggle
     */
    private function render_toggle_column($post_id) {
        $status = get_post_status($post_id);
        $is_enabled = ($status === 'habilitada' || $status === 'publish');
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        
        ?>
        <div class="gdm-toggle-wrapper">
            <label class="gdm-toggle-switch" 
                   data-post-id="<?php echo esc_attr($post_id); ?>"
                   data-current-status="<?php echo esc_attr($status); ?>"
                   data-has-programacion="<?php echo $programar === '1' ? 'true' : 'false'; ?>"
                   aria-label="<?php echo $is_enabled ? __('Desactivar regla', 'product-conditional-content') : __('Activar regla', 'product-conditional-content'); ?>">
                <input type="checkbox" 
                       <?php checked($is_enabled); ?>
                       tabindex="0">
                <span class="gdm-toggle-slider"></span>
            </label>
        </div>
        <?php
    }
    
    /**
     * Renderizar columna "Aplica a"
     */
    private function render_aplica_a_column($post_id) {
        $aplicar_a = get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [];
        
        if (empty($aplicar_a)) {
            echo '<span style="color:#999;">—</span>';
            return;
        }
        
        $labels = [
            'todos' => __('Todos', 'product-conditional-content'),
            'categoria' => __('Categoría', 'product-conditional-content'),
            'etiqueta' => __('Etiqueta', 'product-conditional-content'),
            'reutilizable' => __('Reutilizable', 'product-conditional-content'),
        ];
        
        $output = [];
        foreach ($aplicar_a as $tipo) {
            if (isset($labels[$tipo])) {
                $output[] = '<span class="gdm-badge">' . esc_html($labels[$tipo]) . '</span>';
            }
        }
        
        echo implode(' ', $output);
    }
    
    /**
     * Renderizar columna de estado
     */
    private function render_estado_column($post_id) {
        $status = get_post_status($post_id);
        $core = GDM_Regla_Status_Manager::get_component('core');
        
        if ($core) {
            $sub_status = $core->calculate_substatus(null, $post_id, $status);
        } else {
            $sub_status = [
                'class' => 'status-unknown',
                'label' => $status,
                'description' => '',
            ];
        }
        
        ?>
        <div class="gdm-status-with-description">
            <span class="gdm-status-badge <?php echo esc_attr($sub_status['class']); ?>">
                <span class="gdm-status-icon"></span>
                <?php echo esc_html($sub_status['label']); ?>
            </span>
            <?php if (!empty($sub_status['description'])): ?>
                <span class="gdm-status-description">
                    <?php echo esc_html($sub_status['description']); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar columna de fechas
     */
    private function render_fechas_column($post_id) {
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post_id, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true);
        
        if ($programar !== '1') {
            echo '<span style="color:#999;">' . __('Sin programar', 'product-conditional-content') . '</span>';
            return;
        }
        
        echo '<div class="gdm-fechas-column">';
        
        if ($fecha_inicio) {
            echo '<div>📅 <strong>' . date_i18n('d M Y H:i', strtotime($fecha_inicio)) . '</strong></div>';
        }
        
        if ($habilitar_fin === '1' && $fecha_fin) {
            echo '<div>⏹️ <strong>' . date_i18n('d M Y H:i', strtotime($fecha_fin)) . '</strong></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Columnas ordenables
     */
    public function sortable_columns($columns) {
        $columns['gdm_estado'] = 'post_status';
        $columns['fechas'] = 'gdm_fecha_inicio';
        return $columns;
    }
    
    /**
     * Enqueue scripts y estilos
     */
    public function enqueue_assets($hook) {
        global $post_type;
        
        if ($hook !== 'edit.php' || $post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_style(
            'gdm-regla-toggle',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-toggle.css',
            [],
            GDM_VERSION
        );
        
        wp_enqueue_script(
            'gdm-regla-toggle',
            GDM_PLUGIN_URL . 'assets/admin/js/components/rules-toggle.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-regla-toggle', 'gdmToggle', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_toggle_nonce'),
            'i18n' => [
                'enabled' => __('Habilitada', 'product-conditional-content'),
                'disabled' => __('Deshabilitada', 'product-conditional-content'),
                'error' => __('Error al cambiar el estado', 'product-conditional-content'),
                'ajaxError' => __('Error de conexión', 'product-conditional-content'),
                'confirmDisable' => __('¿Desactivar esta regla?', 'product-conditional-content'),
                'onlyActive' => __('Solo activas', 'product-conditional-content'),
                'noSelection' => __('Selecciona al menos una regla', 'product-conditional-content'),
                'confirmBulkToggle' => __('¿Cambiar estado de las reglas seleccionadas?', 'product-conditional-content'),
            ],
        ]);
    }
}