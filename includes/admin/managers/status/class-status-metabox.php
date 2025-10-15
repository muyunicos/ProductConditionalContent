<?php
/**
 * Componente: Metabox de Publicación con Toggle y Programación
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Metabox {
    
    public function __construct() {
        // Modificar metabox nativo
        add_action('post_submitbox_start', [$this, 'remove_native_elements']);
        add_action('post_submitbox_misc_actions', [$this, 'add_custom_sections']);
        
        // Guardar datos
        add_action('save_post_gdm_regla', [$this, 'save_metabox_data'], 30, 2);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Remover elementos nativos innecesarios
     */
    public function remove_native_elements() {
        global $post, $current_screen;
        
        if (!$post || $current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#save-action').remove();
            $('#visibility').remove();
            $('.misc-pub-curtime').remove();
            $('.misc-pub-post-status').remove();
        });
        </script>
        <?php
    }
    
    /**
     * Agregar secciones personalizadas
     */
    public function add_custom_sections() {
        global $post, $current_screen;
        
        if (!$post || $current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        $current_status = $post->post_status;
        if ($current_status === 'publish' || $current_status === 'auto-draft') {
            $current_status = 'habilitada';
        }
        
        $is_enabled = ($current_status === 'habilitada');
        $programar = get_post_meta($post->ID, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post->ID, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post->ID, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post->ID, '_gdm_habilitar_fecha_fin', true);
        
        $core = GDM_Regla_Status_Manager::get_component('core');
        $estado_info = $core ? $core->get_estado_info($is_enabled, $programar, $fecha_inicio, $fecha_fin, $habilitar_fin) : [
            'class' => 'status-unknown',
            'titulo' => 'Desconocido',
            'descripcion' => '',
            'boton' => 'guardar'
        ];
        
        wp_nonce_field('gdm_regla_schedule_nonce', 'gdm_regla_schedule_nonce');
        
        ?>
        <!-- Sección de Toggle -->
        <div class="misc-pub-section gdm-status-section">
            <div class="gdm-status-indicator <?php echo esc_attr($estado_info['class']); ?>">
                <label class="gdm-toggle-switch gdm-toggle-metabox">
                    <input type="checkbox" 
                           id="gdm-metabox-toggle" 
                           name="gdm_regla_enabled" 
                           value="1" 
                           <?php checked($is_enabled); ?>>
                    <span class="gdm-toggle-slider"></span>
                </label>
                
                <div style="flex: 1;">
                    <strong><?php _e('Estado:', 'product-conditional-content'); ?></strong>
                    <span class="gdm-status-display">
                        <?php echo esc_html($estado_info['titulo']); ?>
                    </span>
                    <p class="description gdm-status-description" style="margin: 4px 0 0 0;">
                        <?php echo esc_html($estado_info['descripcion']); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Sección de Programación -->
        <div class="misc-pub-section gdm-schedule-section">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" 
                       name="gdm_programar" 
                       id="gdm_programar" 
                       value="1" 
                       <?php checked($programar, '1'); ?>>
                <strong><?php _e('Programar activación', 'product-conditional-content'); ?></strong>
            </label>
            
            <div id="gdm-schedule-fields" style="<?php echo $programar !== '1' ? 'display:none;' : ''; ?> margin-top: 12px; padding-left: 24px;">
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 4px;">
                        <strong><?php _e('📅 Fecha de Inicio:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="datetime-local" 
                           name="gdm_fecha_inicio" 
                           id="gdm_fecha_inicio" 
                           value="<?php echo $fecha_inicio ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_inicio))) : ''; ?>" 
                           style="width: 100%;">
                    
                    <div class="gdm-quick-dates" style="margin-top: 8px;">
                        <button type="button" class="button button-small gdm-quick-date" data-type="tomorrow">
                            <?php _e('Mañana 00:00', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-date" data-type="monday">
                            <?php _e('Próximo lunes', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-date" data-type="month">
                            <?php _e('Próximo mes', 'product-conditional-content'); ?>
                        </button>
                    </div>
                    
                    <p class="description gdm-inicio-description" style="margin: 8px 0 0 0; font-size: 11px;">
                        <?php echo $this->get_inicio_description($fecha_inicio); ?>
                    </p>
                </div>
                
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="checkbox" 
                           name="gdm_habilitar_fecha_fin" 
                           id="gdm_habilitar_fecha_fin" 
                           value="1" 
                           <?php checked($habilitar_fin, '1'); ?>>
                    <?php _e('Programar fecha de fin', 'product-conditional-content'); ?>
                </label>
                
                <div id="gdm-fecha-fin-wrapper" style="<?php echo $habilitar_fin !== '1' ? 'display:none;' : ''; ?>">
                    <label style="display: block; margin-bottom: 4px;">
                        <strong><?php _e('📅 Fecha de Fin:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="datetime-local" 
                           name="gdm_fecha_fin" 
                           id="gdm_fecha_fin" 
                           value="<?php echo $fecha_fin ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_fin))) : ''; ?>" 
                           style="width: 100%;">
                    
                    <div class="gdm-quick-durations" style="margin-top: 8px;">
                        <button type="button" class="button button-small gdm-quick-duration" data-hours="24">
                            <?php _e('24hs', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-hours="72">
                            <?php _e('72hs', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-days="7">
                            <?php _e('Semana', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-days="30">
                            <?php _e('Mes', 'product-conditional-content'); ?>
                        </button>
                    </div>
                    
                    <p class="description gdm-fin-description" style="margin: 8px 0 0 0; font-size: 11px;">
                        <?php echo $this->get_fin_description($fecha_inicio, $fecha_fin); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Data attributes para JavaScript -->
        <input type="hidden" id="gdm-current-status" value="<?php echo esc_attr($current_status); ?>">
        <input type="hidden" id="gdm-button-action" value="<?php echo esc_attr($estado_info['boton']); ?>">
        <?php
    }
    
    /**
     * Guardar datos del metabox
     */
    public function save_metabox_data($post_id, $post) {
        if (!GDM_Admin_Helpers::validate_metabox_save(
            $post_id, 
            $post, 
            'gdm_regla_schedule_nonce', 
            'gdm_regla_schedule_nonce', 
            'gdm_regla'
        )) {
            return;
        }
        
        // Guardar estado del toggle
        $is_enabled = isset($_POST['gdm_regla_enabled']) && $_POST['gdm_regla_enabled'] === '1';
        $new_status = $is_enabled ? 'habilitada' : 'deshabilitada';
        
        remove_action('save_post_gdm_regla', [$this, 'save_metabox_data'], 30);
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);
        add_action('save_post_gdm_regla', [$this, 'save_metabox_data'], 30, 2);
        
        // Guardar programación
        $programar = isset($_POST['gdm_programar']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_programar', $programar);
        
        if ($programar === '1' && isset($_POST['gdm_fecha_inicio']) && !empty($_POST['gdm_fecha_inicio'])) {
            $fecha_inicio = sanitize_text_field($_POST['gdm_fecha_inicio']);
            update_post_meta($post_id, '_gdm_fecha_inicio', date('Y-m-d H:i:s', strtotime($fecha_inicio)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_inicio');
        }
        
        $habilitar_fin = isset($_POST['gdm_habilitar_fecha_fin']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin);
        
        if ($programar === '1' && $habilitar_fin === '1' && isset($_POST['gdm_fecha_fin']) && !empty($_POST['gdm_fecha_fin'])) {
            $fecha_fin = sanitize_text_field($_POST['gdm_fecha_fin']);
            update_post_meta($post_id, '_gdm_fecha_fin', date('Y-m-d H:i:s', strtotime($fecha_fin)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_fin');
        }
    }
    
    /**
     * Obtener descripción para fecha de inicio
     */
    private function get_inicio_description($fecha_inicio) {
        if (!$fecha_inicio) {
            return __('La regla se activará automáticamente', 'product-conditional-content');
        }
        
        $now = current_time('timestamp');
        $inicio = strtotime($fecha_inicio);
        $diff = $inicio - $now;
        
        if ($diff <= 0) {
            return __('La regla se activará inmediatamente', 'product-conditional-content');
        }
        
        $core = GDM_Regla_Status_Manager::get_component('core');
        if (!$core) {
            return __('Calculando...', 'product-conditional-content');
        }
        
        $tiempo = $this->calcular_tiempo_legible($diff);
        $fecha_formatted = date_i18n('j \d\e F \d\e Y', $inicio);
        
        return sprintf(
            __('La regla se activará automáticamente en %s, el día %s', 'product-conditional-content'),
            $tiempo,
            $fecha_formatted
        );
    }
    
    /**
     * Obtener descripción para fecha de fin
     */
    private function get_fin_description($fecha_inicio, $fecha_fin) {
        if (!$fecha_fin) {
            return __('La regla se desactivará automáticamente', 'product-conditional-content');
        }
        
        $now = current_time('timestamp');
        $fin = strtotime($fecha_fin);
        $diff = $fin - $now;
        
        if ($diff <= 0) {
            return __('La regla se desactivará inmediatamente', 'product-conditional-content');
        }
        
        $tiempo = $this->calcular_tiempo_legible($diff);
        $fecha_formatted = date_i18n('j \d\e F \d\e Y', $fin);
        
        return sprintf(
            __('La regla se desactivará automáticamente en %s, el día %s', 'product-conditional-content'),
            $tiempo,
            $fecha_formatted
        );
    }
    
    /**
     * Calcular tiempo legible
     */
    private function calcular_tiempo_legible($segundos) {
        $minutos = round($segundos / 60);
        $horas = round($segundos / 3600);
        $dias = round($segundos / 86400);
        $semanas = round($segundos / 604800);
        $meses = round($segundos / 2592000);
        
        if ($minutos < 60) {
            return sprintf(_n('%d minuto', '%d minutos', $minutos, 'product-conditional-content'), $minutos);
        } elseif ($horas < 24) {
            return sprintf(_n('%d hora', '%d horas', $horas, 'product-conditional-content'), $horas);
        } elseif ($dias < 7) {
            return sprintf(_n('%d día', '%d días', $dias, 'product-conditional-content'), $dias);
        } elseif ($semanas < 4) {
            return sprintf(_n('%d semana', '%d semanas', $semanas, 'product-conditional-content'), $semanas);
        } else {
            return sprintf(_n('%d mes', '%d meses', $meses, 'product-conditional-content'), $meses);
        }
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_script(
            'gdm-regla-publish-metabox',
            GDM_PLUGIN_URL . 'assets/admin/js/metaboxes/rules-publish-toggle.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-regla-publish-metabox', 'gdmPublishMetabox', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_publish_metabox_nonce'),
            'i18n' => [
                'habilitada' => __('Habilitada', 'product-conditional-content'),
                'deshabilitada' => __('Deshabilitada', 'product-conditional-content'),
                'habilitar' => __('Habilitar', 'product-conditional-content'),
                'deshabilitar' => __('Deshabilitar', 'product-conditional-content'),
                'guardar' => __('Guardar', 'product-conditional-content'),
                'laReglaSeDesactivara' => __('La regla se desactivará', 'product-conditional-content'),
            ],
        ]);
    }
}