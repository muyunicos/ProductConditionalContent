<?php
/**
 * Clase Base Abstracta para M�dulos de Acci�n v7.0
 * Sistema Universal Multi-Contexto (Products, Posts, Pages, Shortcodes, etc.)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

abstract class GDM_Action_Base {

    /**
     * ID �nico del m�dulo
     * @var string
     */
    protected $action_id = '';

    /**
     * Nombre del m�dulo
     * @var string
     */
    protected $action_name = '';

    /**
     * Icono del m�dulo
     * @var string
     */
    protected $action_icon = '�';

    /**
     * Descripci�n corta
     * @var string
     */
    protected $action_description = '';

    /**
     * Prioridad de orden
     * @var int
     */
    protected $priority = 10;

    /**
     * Contextos soportados
     * Valores posibles: 'products', 'posts', 'pages', 'shortcode', 'wc_cart', 'wc_checkout', '*' (todos)
     * @var array
     */
    protected $supported_contexts = ['products'];

    /**
     * Cach� est�tico
     * @var array
     */
    protected static $cache = [];

    /**
     * Constructor
     */
    public function __construct() {
        if (empty($this->action_id) || empty($this->action_name)) {
            wp_die(__('El m�dulo de acci�n debe definir action_id y action_name', 'product-conditional-content'));
        }

        // Hooks de inicializaci�n
        $this->action_init();
    }

    /**
     * Hook de inicializaci�n espec�fica (opcional)
     */
    protected function action_init() {
        // Implementar en clase hija si se necesita
    }

    /**
     * Obtener ID del m�dulo
     *
     * @return string
     */
    public function get_id() {
        return $this->action_id;
    }

    /**
     * Obtener nombre del m�dulo
     *
     * @return string
     */
    public function get_name() {
        return $this->action_name;
    }

    /**
     * Obtener icono del m�dulo
     *
     * @return string
     */
    public function get_icon() {
        return $this->action_icon;
    }

    /**
     * Obtener descripci�n del m�dulo
     *
     * @return string
     */
    public function get_description() {
        return $this->action_description;
    }

    /**
     * Obtener prioridad del m�dulo
     *
     * @return int
     */
    public function get_priority() {
        return $this->priority;
    }

    /**
     * Obtener contextos soportados
     *
     * @return array
     */
    public function get_supported_contexts() {
        return $this->supported_contexts;
    }

    /**
     * Verificar si soporta un contexto espec�fico
     *
     * @param string $context Contexto a verificar
     * @return bool
     */
    public function supports_context($context) {
        return in_array('*', $this->supported_contexts, true) ||
               in_array($context, $this->supported_contexts, true);
    }

    /**
     * Obtener etiquetas de contextos soportados
     *
     * @return array
     */
    public function get_context_labels() {
        $labels = [
            'products' => __('Productos WooCommerce', 'product-conditional-content'),
            'posts' => __('Entradas (Posts)', 'product-conditional-content'),
            'pages' => __('P�ginas', 'product-conditional-content'),
            'shortcode' => __('Shortcodes', 'product-conditional-content'),
            'wc_cart' => __('Carrito WooCommerce', 'product-conditional-content'),
            'wc_checkout' => __('Checkout WooCommerce', 'product-conditional-content'),
            '*' => __('Todos los contextos', 'product-conditional-content'),
        ];

        $supported = [];
        foreach ($this->supported_contexts as $context) {
            if (isset($labels[$context])) {
                $supported[$context] = $labels[$context];
            }
        }

        return $supported;
    }

    /**
     * Encolar CSS y JS globales de actions
     *
     * @since 7.0.0
     */
    public static function enqueue_action_assets() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'gdm_regla') {
            return;
        }

        // CSS Base Global (Capa 1)
        wp_enqueue_style(
            'gdm-rules-admin-general',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-admin-general.css',
            [],
            GDM_VERSION
        );

        // CSS Espec�fico de actions (Capa 2)
        wp_enqueue_style(
            'gdm-actions-config',
            GDM_PLUGIN_URL . 'assets/admin/css/actions-config.css',
            ['gdm-rules-admin-general'],
            GDM_VERSION
        );

        // JS Global de actions
        wp_enqueue_script(
            'gdm-actions-handler',
            GDM_PLUGIN_URL . 'assets/admin/js/actions/actions-handler.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
    }

    /**
     * Obtener configuraci�n completa del m�dulo para una regla
     *
     * @param int $rule_id ID de la regla
     * @return array
     */
    public function get_action_config($rule_id) {
        $cache_key = "{$this->action_id}_{$rule_id}";

        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $all_actions = get_post_meta($rule_id, '_gdm_actions_config', true);

        if (empty($all_actions) || !is_array($all_actions)) {
            $all_actions = [];
        }

        $config = isset($all_actions[$this->action_id]) ? $all_actions[$this->action_id] : [];

        $defaults = [
            'enabled' => false,
            'contexts' => $this->supported_contexts,
            'options' => $this->get_default_options(),
            'code' => '',
            'last_updated' => '',
        ];

        $config = wp_parse_args($config, $defaults);

        self::$cache[$cache_key] = $config;
        return $config;
    }

    /**
     * Obtener opciones por defecto del m�dulo (DEBE implementarse)
     *
     * @return array
     */
    abstract protected function get_default_options();

    /**
     * Renderizar el m�dulo completo (UI)
     *
     * @param int $rule_id ID de la regla
     */
    public function render($rule_id) {
        $config = $this->get_action_config($rule_id);
        $is_enabled = !empty($config['enabled']);
        $options = $config['options'];
        $contexts = $config['contexts'];

        ?>
        <div class="gdm-action-module"
             data-action="<?php echo esc_attr($this->action_id); ?>"
             role="region"
             aria-labelledby="gdm-<?php echo esc_attr($this->action_id); ?>-label">

            <div class="gdm-action-header">
                <label class="gdm-action-toggle">
                    <input type="checkbox"
                           id="gdm_action_<?php echo esc_attr($this->action_id); ?>_enabled"
                           name="gdm_actions[<?php echo esc_attr($this->action_id); ?>][enabled]"
                           class="gdm-action-checkbox"
                           value="1"
                           aria-describedby="gdm-<?php echo esc_attr($this->action_id); ?>-description"
                           aria-controls="gdm-<?php echo esc_attr($this->action_id); ?>-content"
                           <?php checked($is_enabled); ?>>
                    <strong id="gdm-<?php echo esc_attr($this->action_id); ?>-label">
                        <?php echo esc_html($this->action_icon . ' ' . $this->action_name); ?>
                    </strong>
                </label>

                <?php if (!empty($this->action_description)): ?>
                <p class="gdm-action-description" id="gdm-<?php echo esc_attr($this->action_id); ?>-description">
                    <?php echo esc_html($this->action_description); ?>
                </p>
                <?php endif; ?>

                <?php if (!empty($this->get_context_labels())): ?>
                <div class="gdm-action-contexts">
                    <small>
                        <?php
                        echo esc_html__('Contextos:', 'product-conditional-content') . ' ';
                        echo esc_html(implode(', ', $this->get_context_labels()));
                        ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>

            <div class="gdm-action-content"
                 id="gdm-<?php echo esc_attr($this->action_id); ?>-content"
                 role="group"
                 aria-hidden="<?php echo $is_enabled ? 'false' : 'true'; ?>"
                 style="<?php echo !$is_enabled ? 'display:none;' : ''; ?>">

                <?php $this->render_context_selector($rule_id, $contexts); ?>

                <div class="gdm-action-options">
                    <?php $this->render_options($rule_id, $options); ?>
                </div>

                <div class="gdm-action-footer">
                    <button type="button"
                            class="button button-primary gdm-action-save"
                            data-action="<?php echo esc_attr($this->action_id); ?>"
                            aria-label="<?php printf(__('Guardar cambios en %s', 'product-conditional-content'), $this->action_name); ?>">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Guardar Cambios', 'product-conditional-content'); ?>
                    </button>
                    <button type="button"
                            class="button gdm-action-cancel"
                            data-action="<?php echo esc_attr($this->action_id); ?>"
                            aria-label="<?php _e('Cancelar cambios', 'product-conditional-content'); ?>">
                        <?php _e('Cancelar', 'product-conditional-content'); ?>
                    </button>
                    <button type="button"
                            class="button button-secondary gdm-action-regenerate"
                            data-action="<?php echo esc_attr($this->action_id); ?>"
                            aria-label="<?php _e('Regenerar c�digo ejecutable', 'product-conditional-content'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Regenerar C�digo', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    /**
     * Renderizar selector de contextos
     *
     * @param int $rule_id ID de la regla
     * @param array $selected_contexts Contextos seleccionados
     */
    protected function render_context_selector($rule_id, $selected_contexts) {
        if (in_array('*', $this->supported_contexts, true)) {
            // Si soporta todos los contextos, mostrar selector completo
            ?>
            <div class="gdm-context-selector">
                <h4><?php _e('Aplicar en:', 'product-conditional-content'); ?></h4>
                <div class="gdm-context-checkboxes">
                    <?php
                    $all_contexts = [
                        'products' => __('Productos', 'product-conditional-content'),
                        'posts' => __('Entradas', 'product-conditional-content'),
                        'pages' => __('P�ginas', 'product-conditional-content'),
                        'shortcode' => __('Shortcodes', 'product-conditional-content'),
                        'wc_cart' => __('Carrito', 'product-conditional-content'),
                        'wc_checkout' => __('Checkout', 'product-conditional-content'),
                    ];

                    foreach ($all_contexts as $context => $label) {
                        $checked = in_array($context, $selected_contexts, true);
                        ?>
                        <label>
                            <input type="checkbox"
                                   name="gdm_actions[<?php echo esc_attr($this->action_id); ?>][contexts][]"
                                   value="<?php echo esc_attr($context); ?>"
                                   <?php checked($checked); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        } else {
            // Contextos fijos, campos ocultos
            foreach ($this->supported_contexts as $context) {
                ?>
                <input type="hidden"
                       name="gdm_actions[<?php echo esc_attr($this->action_id); ?>][contexts][]"
                       value="<?php echo esc_attr($context); ?>">
                <?php
            }
        }
    }

    /**
     * Renderizar opciones espec�ficas del m�dulo (DEBE implementarse)
     *
     * @param int $rule_id ID de la regla
     * @param array $options Opciones actuales
     */
    abstract protected function render_options($rule_id, $options);

    /**
     * Guardar datos del m�dulo
     *
     * @param int $rule_id ID de la regla
     * @param WP_Post $post Objeto del post
     */
    public function save($rule_id, $post) {
        // Verificar si este m�dulo fue enviado
        if (!isset($_POST['gdm_actions'][$this->action_id])) {
            return;
        }

        $post_data = $_POST['gdm_actions'][$this->action_id];

        // Obtener configuraci�n completa existente
        $all_actions = get_post_meta($rule_id, '_gdm_actions_config', true);
        if (empty($all_actions) || !is_array($all_actions)) {
            $all_actions = [];
        }

        // Sanitizar y validar opciones
        $sanitized_options = $this->sanitize_options($post_data);

        // Generar c�digo ejecutable
        $generated_code = $this->generate_execution_code($sanitized_options);

        // Construir configuraci�n del m�dulo
        $action_config = [
            'enabled' => !empty($post_data['enabled']),
            'contexts' => isset($post_data['contexts']) ? array_map('sanitize_text_field', $post_data['contexts']) : $this->supported_contexts,
            'options' => $sanitized_options,
            'code' => $generated_code,
            'last_updated' => current_time('mysql'),
        ];

        // Actualizar configuraci�n del m�dulo
        $all_actions[$this->action_id] = $action_config;

        // Guardar en meta
        update_post_meta($rule_id, '_gdm_actions_config', $all_actions);

        // Hook para extensiones
        do_action("gdm_save_action_{$this->action_id}", $rule_id, $action_config, $post);
    }

    /**
     * Sanitizar opciones del m�dulo (DEBE implementarse)
     *
     * @param array $post_data Datos del POST
     * @return array Opciones sanitizadas
     */
    abstract protected function sanitize_options($post_data);

    /**
     * Generar c�digo ejecutable para el m�dulo (DEBE implementarse)
     *
     * @param array $options Opciones sanitizadas
     * @return string C�digo PHP ejecutable
     */
    abstract protected function generate_execution_code($options);

    /**
     * Ejecutar el m�dulo en un contexto espec�fico
     *
     * @param string $context Contexto de ejecuci�n
     * @param int $object_id ID del objeto (product_id, post_id, etc.)
     * @param int $rule_id ID de la regla
     * @param array $config Configuraci�n del m�dulo
     * @return mixed Resultado de la ejecuci�n
     */
    public function execute($context, $object_id, $rule_id, $config) {
        // Verificar si soporta el contexto
        if (!$this->supports_context($context)) {
            return false;
        }

        // Verificar si est� habilitado
        if (empty($config['enabled'])) {
            return false;
        }

        // Verificar si el contexto est� activo para esta regla
        if (!in_array($context, $config['contexts'], true)) {
            return false;
        }

        // Ejecutar c�digo generado
        if (!empty($config['code'])) {
            try {
                return eval($config['code']);
            } catch (Exception $e) {
                error_log(sprintf(
                    'GDM Action Error [%s]: %s',
                    $this->action_id,
                    $e->getMessage()
                ));
                return false;
            }
        }

        return false;
    }

    /**
     * Renderizar estilos espec�ficos del m�dulo (opcional)
     */
    protected function render_styles() {
        // Implementar en clase hija si se necesitan estilos espec�ficos
    }

    /**
     * Renderizar scripts espec�ficos del m�dulo (opcional)
     */
    protected function render_scripts() {
        // Implementar en clase hija si se necesitan scripts espec�ficos
    }

    /**
     * Validar opciones (opcional, override en clase hija)
     *
     * @param array $options Opciones a validar
     * @return array Errores de validaci�n (vac�o si todo OK)
     */
    protected function validate_options($options) {
        return [];
    }

    /**
     * Helper: Sanitizar campo de texto
     *
     * @param mixed $value Valor a sanitizar
     * @return string
     */
    protected function sanitize_text($value) {
        return sanitize_text_field($value);
    }

    /**
     * Helper: Sanitizar campo num�rico
     *
     * @param mixed $value Valor a sanitizar
     * @param int $default Valor por defecto
     * @return int
     */
    protected function sanitize_number($value, $default = 0) {
        return absint($value) ?: $default;
    }

    /**
     * Helper: Sanitizar campo decimal
     *
     * @param mixed $value Valor a sanitizar
     * @param float $default Valor por defecto
     * @return float
     */
    protected function sanitize_decimal($value, $default = 0.0) {
        return floatval($value) ?: $default;
    }

    /**
     * Helper: Sanitizar array de IDs
     *
     * @param mixed $value Valor a sanitizar
     * @return array
     */
    protected function sanitize_id_array($value) {
        if (!is_array($value)) {
            return [];
        }
        return array_map('absint', $value);
    }

    /**
     * Helper: Sanitizar HTML seguro
     *
     * @param mixed $value Valor a sanitizar
     * @return string
     */
    protected function sanitize_html($value) {
        return wp_kses_post($value);
    }
}
