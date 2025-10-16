<?php
/**
 * Gestor de Acciones v7.0 - Sistema Dinámico con Auto-Discovery
 * Compatible con WordPress 6.8.3, PHP 8.2
 *
 * Sistema universal multi-contexto con carga automática de módulos
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

final class GDM_Action_Manager {
    private static $instance = null;
    private $actions = [];
    private $action_instances = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Registrar base class
        add_action('init', [$this, 'load_base_class'], 5);

        // Auto-discovery de acciones en prioridad 10 (DESPUÉS de traducciones)
        add_action('init', [$this, 'auto_discover_actions'], 10);

        // Permitir registro externo prioridad 11
        add_action('init', [$this, 'allow_external_registration'], 11);

        // Inicializar acciones prioridad 12
        add_action('init', [$this, 'init_registered_actions'], 12);

        // Registrar hook de guardado
        add_action('gdm_save_modules_data', [$this, 'save_all_actions'], 10, 2);

        // Encolar assets globales
        add_action('admin_enqueue_scripts', ['GDM_Action_Base', 'enqueue_action_assets']);
    }

    /**
     * Cargar clase base
     */
    public function load_base_class() {
        $base_file = GDM_PLUGIN_DIR . 'includes/admin/metaboxes/class-action-base.php';
        if (file_exists($base_file)) {
            require_once $base_file;
        }
    }
    
    /**
     * Auto-discovery de acciones desde el directorio actions/
     * Escanea y registra automáticamente todos los archivos class-action-*.php
     *
     * @since 7.0.0
     */
    public function auto_discover_actions() {
        $actions_dir = GDM_PLUGIN_DIR . 'includes/admin/actions/';

        if (!is_dir($actions_dir)) {
            return;
        }

        // Escanear directorio buscando archivos class-action-*.php
        $files = glob($actions_dir . 'class-action-*.php');

        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            // Extraer ID del nombre de archivo: class-action-{id}.php
            $filename = basename($file, '.php');
            if (!preg_match('/^class-action-(.+)$/', $filename, $matches)) {
                continue;
            }

            $action_id = $matches[1];

            // Convertir action-id a Action_Id para nombre de clase
            // Ejemplo: price -> Price, featured -> Featured, product-types -> Product_Types
            $parts = explode('-', $action_id);
            $parts = array_map('ucfirst', $parts);
            $class_name = 'GDM_Action_' . implode('_', $parts);

            // Registrar acción
            $this->register_action($action_id, [
                'class' => $class_name,
                'file' => $file,
                'enabled' => true,
                'auto_discovered' => true,
            ]);
        }

        // Hook para extensiones
        do_action('gdm_register_actions', $this);
    }
    
    /**
     * Permitir registro externo
     */
    public function allow_external_registration() {
        do_action('gdm_actions_init', $this);
    }

    /**
     * Inicializar acciones registradas
     */
    public function init_registered_actions() {
        foreach ($this->actions as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }

            // Cargar archivo si existe
            if (!empty($config['file']) && file_exists($config['file'])) {
                require_once $config['file'];
            }

            // Instanciar clase
            if (class_exists($config['class'])) {
                try {
                    $instance = new $config['class']();

                    // Obtener datos desde la instancia si no están en config
                    if (empty($config['label'])) {
                        $config['label'] = $instance->get_name();
                    }
                    if (empty($config['icon'])) {
                        $config['icon'] = $instance->get_icon();
                    }
                    if (empty($config['priority'])) {
                        $config['priority'] = $instance->get_priority();
                    }

                    // Actualizar config con datos reales
                    $this->actions[$id] = $config;

                    $this->action_instances[$id] = $instance;
                } catch (Exception $e) {
                    error_log(sprintf(
                        'GDM Action Manager: Error al inicializar acción "%s": %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            } else {
                error_log(sprintf(
                    'GDM Action Manager: Clase "%s" no encontrada para acción "%s"',
                    $config['class'],
                    $id
                ));
            }
        }

        do_action('gdm_actions_loaded', $this->action_instances);
    }
    
    /**
     * Registrar una acción
     */
    public function register_action($id, $config = []) {
        if (empty($id)) {
            return false;
        }

        if (isset($this->actions[$id]) && empty($config['force'])) {
            return false;
        }

        $defaults = [
            'class' => '',
            'label' => '',
            'icon' => '',
            'file' => '',
            'enabled' => true,
            'priority' => 10,
            'description' => '',
            'auto_discovered' => false,
        ];

        $config = wp_parse_args($config, $defaults);

        if (empty($config['class'])) {
            return false;
        }

        $this->actions[$id] = $config;

        return true;
    }
    
    /**
     * Desregistrar acción
     */
    public function unregister_action($id) {
        if (isset($this->actions[$id])) {
            unset($this->actions[$id]);

            if (isset($this->action_instances[$id])) {
                unset($this->action_instances[$id]);
            }

            return true;
        }

        return false;
    }

    /**
     * Obtener acción por ID
     */
    public function get_action($id) {
        return $this->actions[$id] ?? null;
    }

    /**
     * Obtener todas las acciones
     */
    public function get_actions() {
        return $this->actions;
    }

    /**
     * Obtener acciones habilitadas
     */
    public function get_enabled_actions() {
        return array_filter($this->actions, function($action) {
            return $action['enabled'] === true;
        });
    }

    /**
     * Obtener acciones ordenadas por prioridad (para selector UI)
     */
    public function get_actions_ordered() {
        $actions = [];

        foreach ($this->actions as $id => $config) {
            if ($config['enabled']) {
                $actions[$id] = [
                    'id' => $id,
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'priority' => $config['priority'],
                    'description' => $config['description'] ?? '',
                ];
            }
        }

        // Ordenar por prioridad
        uasort($actions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $actions;
    }

    /**
     * Obtener instancia de acción
     */
    public function get_action_instance($id) {
        return $this->action_instances[$id] ?? null;
    }

    /**
     * Verificar si acción está registrada
     */
    public function is_action_registered($id) {
        return isset($this->actions[$id]);
    }

    /**
     * Verificar si acción está habilitada
     */
    public function is_action_enabled($id) {
        return isset($this->actions[$id]) && $this->actions[$id]['enabled'] === true;
    }

    /**
     * Habilitar/deshabilitar acción
     */
    public function set_action_status($id, $enabled = true) {
        if (isset($this->actions[$id])) {
            $this->actions[$id]['enabled'] = (bool) $enabled;
            return true;
        }

        return false;
    }

    /**
     * Renderizar todas las acciones para una regla
     *
     * @param int $rule_id ID de la regla
     */
    public function render_all($rule_id) {
        $actions = $this->get_actions_ordered();

        foreach ($actions as $id => $config) {
            $instance = $this->get_action_instance($id);
            if ($instance) {
                $instance->render($rule_id);
            }
        }
    }

    /**
     * Guardar todas las acciones de una regla
     *
     * @param int $rule_id ID de la regla
     * @param WP_Post $post Objeto del post
     */
    public function save_all_actions($rule_id, $post) {
        foreach ($this->action_instances as $instance) {
            $instance->save($rule_id, $post);
        }
    }

    /**
     * Obtener conteo de acciones
     */
    public function get_actions_count() {
        return [
            'total' => count($this->actions),
            'enabled' => count($this->get_enabled_actions()),
            'disabled' => count($this->actions) - count($this->get_enabled_actions()),
        ];
    }

    /**
     * Debug
     */
    public function debug_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<pre>';
        echo "=== GDM Action Manager v7.0 Debug ===\n\n";
        echo "Acciones registradas: " . count($this->actions) . "\n";
        echo "Acciones inicializadas: " . count($this->action_instances) . "\n\n";

        foreach ($this->actions as $id => $config) {
            $status = $config['enabled'] ? '✅' : '❌';
            $loaded = isset($this->action_instances[$id]) ? '🟢' : '🔴';
            $auto = !empty($config['auto_discovered']) ? '🔍' : '📝';

            echo "{$status} {$loaded} {$auto} {$config['icon']} {$id}\n";
            echo "   Clase: {$config['class']}\n";
            echo "   Label: {$config['label']}\n";
            echo "   Prioridad: {$config['priority']}\n";

            if (!empty($config['file'])) {
                echo "   Archivo: " . (file_exists($config['file']) ? '✓' : '✗') . " {$config['file']}\n";
            }

            if (isset($this->action_instances[$id])) {
                $instance = $this->action_instances[$id];
                $contexts = $instance->get_supported_contexts();
                echo "   Contextos: " . implode(', ', $contexts) . "\n";
            }

            echo "\n";
        }

        echo "Leyenda:\n";
        echo "✅ = Habilitado | ❌ = Deshabilitado\n";
        echo "🟢 = Cargado | 🔴 = No cargado\n";
        echo "🔍 = Auto-descubierto | 📝 = Registro manual\n";
        echo '</pre>';
    }
}

/**
 * Helper function
 */
function gdm_actions() {
    return GDM_Action_Manager::instance();
}

/**
 * RETROCOMPATIBILIDAD: Alias para código legacy
 */
function gdm_modules() {
    return GDM_Action_Manager::instance();
}

class_alias('GDM_Action_Manager', 'GDM_Module_Manager');

/**
 * Debug (solo desarrollo)
 */
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', function() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'gdm_regla' && isset($_GET['debug_actions'])) {
            GDM_Action_Manager::instance()->debug_actions();
        }
    });
}