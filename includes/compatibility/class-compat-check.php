<?php
/**
 * Compatibility Check Class
 * Verifica compatibilidad con WordPress, PHP y WooCommerce
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Compat_Check {
    
    /**
     * Versiones mínimas requeridas
     */
    const MIN_WP_VERSION = '6.0';
    const MIN_PHP_VERSION = '8.0';
    const MIN_WC_VERSION = '8.0';
    
    /**
     * Versiones probadas
     */
    const TESTED_WP_VERSION = '6.8.3';
    const TESTED_PHP_VERSION = '8.2';
    const TESTED_WC_VERSION = '10.2.2';
    
    /**
     * Verificar compatibilidad completa
     * 
     * @return array Array con 'compatible' (bool) y 'messages' (array)
     */
    public static function check() {
        $messages = [];
        $compatible = true;
        
        // Verificar WordPress
        if (!self::check_wordpress()) {
            $compatible = false;
            $messages[] = sprintf(
                __('WordPress %s o superior es requerido. Versión actual: %s', 'product-conditional-content'),
                self::MIN_WP_VERSION,
                get_bloginfo('version')
            );
        }
        
        // Verificar PHP
        if (!self::check_php()) {
            $compatible = false;
            $messages[] = sprintf(
                __('PHP %s o superior es requerido. Versión actual: %s', 'product-conditional-content'),
                self::MIN_PHP_VERSION,
                PHP_VERSION
            );
        }
        
        // Verificar WooCommerce
        if (!self::check_woocommerce()) {
            $compatible = false;
            if (!defined('WC_VERSION')) {
                $messages[] = sprintf(
                    __('WooCommerce %s o superior es requerido. WooCommerce no está activo.', 'product-conditional-content'),
                    self::MIN_WC_VERSION
                );
            } else {
                $messages[] = sprintf(
                    __('WooCommerce %s o superior es requerido. Versión actual: %s', 'product-conditional-content'),
                    self::MIN_WC_VERSION,
                    WC_VERSION
                );
            }
        }
        
        // Verificar HPOS si WooCommerce está activo
        if (self::check_woocommerce()) {
            $hpos_info = self::check_hpos();
            if (!empty($hpos_info['message'])) {
                $messages[] = $hpos_info['message'];
            }
        }
        
        return [
            'compatible' => $compatible,
            'messages' => $messages
        ];
    }
    
    /**
     * Verificar versión de WordPress
     * 
     * @return bool True si es compatible
     */
    public static function check_wordpress() {
        return version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '>=');
    }
    
    /**
     * Verificar versión de PHP
     * 
     * @return bool True si es compatible
     */
    public static function check_php() {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }
    
    /**
     * Verificar WooCommerce
     * 
     * @return bool True si es compatible
     */
    public static function check_woocommerce() {
        if (!defined('WC_VERSION')) {
            return false;
        }
        return version_compare(WC_VERSION, self::MIN_WC_VERSION, '>=');
    }
    
    /**
     * Verificar compatibilidad HPOS (High-Performance Order Storage)
     * 
     * @return array Array con 'enabled' (bool) y 'message' (string)
     */
    public static function check_hpos() {
        $result = [
            'enabled' => false,
            'message' => ''
        ];
        
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return $result;
        }
        
        if (method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            $result['enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            
            if ($result['enabled']) {
                $result['message'] = __('HPOS (High-Performance Order Storage) está habilitado y es compatible.', 'product-conditional-content');
            }
        }
        
        return $result;
    }
    
    /**
     * Obtener información del sistema
     * 
     * @return array Array con información del sistema
     */
    public static function get_system_info() {
        return [
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'required' => self::MIN_WP_VERSION,
                'tested' => self::TESTED_WP_VERSION,
                'compatible' => self::check_wordpress()
            ],
            'php' => [
                'version' => PHP_VERSION,
                'required' => self::MIN_PHP_VERSION,
                'tested' => self::TESTED_PHP_VERSION,
                'compatible' => self::check_php()
            ],
            'woocommerce' => [
                'version' => defined('WC_VERSION') ? WC_VERSION : 'N/A',
                'required' => self::MIN_WC_VERSION,
                'tested' => self::TESTED_WC_VERSION,
                'compatible' => self::check_woocommerce(),
                'hpos' => self::check_hpos()
            ]
        ];
    }
    
    /**
     * Mostrar aviso de incompatibilidad en admin
     * 
     * @param array $messages Mensajes de error
     */
    public static function show_admin_notice($messages) {
        if (empty($messages)) {
            return;
        }
        
        add_action('admin_notices', function() use ($messages) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Reglas de Contenido para WooCommerce:', 'product-conditional-content') . '</strong></p>';
            echo '<ul>';
            foreach ($messages as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });
    }
}