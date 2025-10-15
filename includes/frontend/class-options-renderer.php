<?php
if (!defined('ABSPATH')) exit;

final class GDM_Fields_Frontend {
    private static $instance = null;
    private $fields = [];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Cargar configuración de campos personalizados
        $this->fields = get_option('gdm_product_custom_fields', []);

        // Mostrar campos en la página de producto
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_fields']);

        // Guardar valores al añadir al carrito
        add_filter('woocommerce_add_cart_item_data', [$this, 'save_field_values'], 10, 3);

        // Mostrar valores en el carrito/checkout
        add_filter('woocommerce_get_item_data', [$this, 'display_field_values_in_cart'], 10, 2);

        // Sumar precios adicionales de campos personalizados
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_custom_price'], 20, 1);

        // Cargar assets solo si hay campos personalizados para este producto
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        if (!is_product() || empty($this->fields)) return;
        wp_enqueue_script(
            'gdm-fields-frontend',
            GDM_PLUGIN_URL . 'assets/frontend/js/options-renderer.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        wp_enqueue_style(
            'gdm-fields-frontend',
            GDM_PLUGIN_URL . 'assets/frontend/css/options-renderer.css',
            [],
            GDM_VERSION
        );
        // Pasar config al JS
        wp_localize_script('gdm-fields-frontend', 'gdmFieldsFrontend', [
            'fields' => $this->fields
        ]);
    }
    /**
     * Renderiza los campos personalizados en el producto.
     */
    public function display_fields() {
        if (empty($this->fields)) return;
        ?>
        <div id="gdm-product-fields" data-fields='<?php echo esc_attr(json_encode($this->fields)); ?>'>
        <?php foreach ($this->fields as $field): ?>
            <div class="gdm-field" data-conditional='<?php echo esc_attr(json_encode($field['conditional'] ?? [])); ?>'>
                <label for="gdm_<?php echo esc_attr($field['id']); ?>">
                    <?php echo esc_html($field['label']); ?>
                    <?php if (!empty($field['required'])): ?><span class="gdm-required">*</span><?php endif; ?>
                </label>
                <?php
                switch ($field['type']) {
                    case 'text':
                        printf(
                            '<input type="text" id="gdm_%s" name="gdm_%s" placeholder="%s" %s %s />',
                            esc_attr($field['id']),
                            esc_attr($field['id']),
                            esc_attr($field['placeholder'] ?? ''),
                            !empty($field['required']) ? 'required' : '',
                            !empty($field['maxlength']) ? 'maxlength="'.intval($field['maxlength']).'"' : ''
                        );
                        break;
                    case 'textarea':
                        printf(
                            '<textarea id="gdm_%s" name="gdm_%s" %s %s>%s</textarea>',
                            esc_attr($field['id']),
                            esc_attr($field['id']),
                            !empty($field['required']) ? 'required' : '',
                            !empty($field['maxlength']) ? 'maxlength="'.intval($field['maxlength']).'"' : '',
                            ''
                        );
                        break;
                    case 'checkbox':
                        printf(
                            '<input type="checkbox" id="gdm_%s" name="gdm_%s" value="1" %s />',
                            esc_attr($field['id']),
                            esc_attr($field['id']),
                            !empty($field['required']) ? 'required' : ''
                        );
                        break;
                    case 'radio':
                        if (!empty($field['options'])) {
                            foreach ($field['options'] as $opt) {
                                printf(
                                    '<label><input type="radio" name="gdm_%s" value="%s" %s /> %s</label>',
                                    esc_attr($field['id']),
                                    esc_attr($opt['value']),
                                    !empty($field['required']) ? 'required' : '',
                                    esc_html($opt['label'])
                                );
                            }
                        }
                        break;
                    case 'select':
                        printf('<select id="gdm_%s" name="gdm_%s" %s>', esc_attr($field['id']), esc_attr($field['id']), !empty($field['required']) ? 'required' : '');
                        foreach ($field['options'] as $opt) {
                            printf('<option value="%s">%s</option>', esc_attr($opt['value']), esc_html($opt['label']));
                        }
                        echo '</select>';
                        break;
                }
                ?>
            </div>
        <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Guarda los valores de los campos personalizados en el carrito.
     */
    public function save_field_values($cart_item_data, $product_id, $variation_id) {
        foreach ($this->fields as $field) {
            $key = 'gdm_' . $field['id'];
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                // Sanitización según tipo
                if ($field['type'] === 'checkbox') {
                    $cart_item_data[$key] = absint($value) ? 1 : 0;
                } else {
                    $cart_item_data[$key] = sanitize_text_field($value);
                }
            }
        }
        return $cart_item_data;
    }

    /**
     * Muestra los valores de los campos personalizados en el carrito y checkout.
     */
    public function display_field_values_in_cart($item_data, $cart_item) {
        foreach ($this->fields as $field) {
            $key = 'gdm_' . $field['id'];
            if (isset($cart_item[$key])) {
                $item_data[] = [
                    'key' => $field['label'],
                    'value' => is_array($cart_item[$key]) ? implode(', ', $cart_item[$key]) : $cart_item[$key]
                ];
            }
        }
        return $item_data;
    }

    /**
     * Suma el precio extra de los campos personalizados al total del carrito.
     */
    public function add_custom_price($cart) {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            foreach ($this->fields as $field) {
                $key = 'gdm_' . $field['id'];
                if (isset($cart_item[$key])) {
                    // Precio extra para checkbox
                    if ($field['type'] === 'checkbox' && !empty($field['price']) && $cart_item[$key]) {
                        $cart->add_fee($field['label'], floatval($field['price']));
                    }
                    // Precio extra para select/radio por opción
                    if (in_array($field['type'], ['select', 'radio']) && !empty($field['options'])) {
                        foreach ($field['options'] as $opt) {
                            if ($cart_item[$key] == $opt['value'] && !empty($opt['price'])) {
                                $cart->add_fee($field['label'] . ': ' . $opt['label'], floatval($opt['price']));
                            }
                        }
                    }
                }
            }
        }
    }
}

GDM_Fields_Frontend::instance();