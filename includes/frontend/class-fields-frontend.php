<?php
if (!defined('ABSPATH')) exit;

final class GDM_Product_Fields
{
    private static $instance;
    private $fields;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Define fields structure here or load from DB/options
        $this->fields = get_option('gdm_product_custom_fields', []);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_fields']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'save_field_values'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_field_values_in_cart'], 10, 2);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_custom_price'], 20, 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function display_fields() {
        ?>
        <div id="gdm-product-fields" data-fields='<?php echo esc_attr(json_encode($this->fields)); ?>'>
        <?php foreach ($this->fields as $field): ?>
            <div class="gdm-field" data-conditional='<?php echo esc_attr(json_encode($field['conditional'])); ?>'>
                <label><?php echo esc_html($field['label']); ?></label>
                <?php
                switch ($field['type']) {
                    case 'text':
                        printf('<input type="text" name="gdm_%s" placeholder="%s" />', esc_attr($field['id']), esc_attr($field['placeholder'] ?? ''));
                        break;
                    case 'textarea':
                        printf('<textarea name="gdm_%s"></textarea>', esc_attr($field['id']));
                        break;
                    case 'checkbox':
                        printf('<input type="checkbox" name="gdm_%s" value="1" />', esc_attr($field['id']));
                        break;
                    case 'radio':
                        if (!empty($field['options'])) {
                            foreach ($field['options'] as $val => $label) {
                                printf('<label><input type="radio" name="gdm_%s" value="%s" /> %s</label>', esc_attr($field['id']), esc_attr($val), esc_html($label));
                            }
                        }
                        break;
                    case 'select':
                        printf('<select name="gdm_%s">', esc_attr($field['id']));
                        foreach ($field['options'] as $val => $label) {
                            printf('<option value="%s">%s</option>', esc_attr($val), esc_html($label));
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

    public function save_field_values($cart_item_data, $product_id, $variation_id) {
        foreach ($this->fields as $field) {
            $key = 'gdm_' . $field['id'];
            if (isset($_POST[$key])) {
                $cart_item_data[$key] = sanitize_text_field($_POST[$key]);
            }
        }
        return $cart_item_data;
    }

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

    public function add_custom_price($cart) {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            foreach ($this->fields as $field) {
                $key = 'gdm_' . $field['id'];
                if (isset($cart_item[$key])) {
                    // Suma precio extra si aplica
                    if ($field['type'] === 'checkbox' && $field['price'] && $cart_item[$key]) {
                        $cart->add_fee($field['label'], $field['price']);
                    }
                    if ($field['type'] === 'select' && isset($field['price'][$cart_item[$key]])) {
                        $cart->add_fee($field['label'], $field['price'][$cart_item[$key]]);
                    }
                }
            }
        }
    }

    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script('gdm-product-fields', plugins_url('/assets/product-fields.js', __FILE__), ['jquery'], '1.0', true);
        }
    }
}

GDM_Product_Fields::instance();