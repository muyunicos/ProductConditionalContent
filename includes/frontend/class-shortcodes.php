<?php
if (!defined('ABSPATH')) exit;

/**
 * Implementación de shortcodes frontend para el plugin Motor de Reglas de Contenido MuyUnicos.
 * 
 * - [campo-cond id="..."] Renderiza el campo personalizado en cualquier lugar del contenido o descripción.
 * - Puedes extender este archivo para otros shortcodes relacionados (por ejemplo, [regla-cond] en el futuro).
 */
final class GDM_Shortcodes_Frontend
{
    private static $instance = null;
    private $fields = [];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Carga la configuración de campos una sola vez por request
        $this->fields = get_option('gdm_product_custom_fields', []);

        // Registrar shortcodes
        add_shortcode('campo-cond', [$this, 'shortcode_campo_cond']);
    }

    /**
     * Shortcode: [campo-cond id="..."]
     * Renderiza el campo personalizado con ese ID si existe
     */
    public function shortcode_campo_cond($atts) {
        if (!is_singular('product')) return '';
        $atts = shortcode_atts(['id' => ''], $atts, 'campo-cond');
        $field_id = sanitize_key($atts['id']);
        if (!$field_id) return '';

        $field = null;
        foreach ($this->fields as $f) {
            if ($f['id'] === $field_id) {
                $field = $f;
                break;
            }
        }
        if (!$field) return '';

        // Renderizado similar a class-fields-frontend.php pero solo del campo individual
        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }
}

// Inicialización automática del módulo de shortcodes en frontend
GDM_Shortcodes_Frontend::instance();