<?php
/**
 * Helper para Renderizado de Campos - NUEVA IMPLEMENTACIÓN
 * Reemplaza código duplicado en múltiples archivos action
 * 
 * @package ProductConditionalContent
 * @since 7.0.0
 */

if (!defined('ABSPATH')) exit;

class GDM_Field_Renderer {
    
    /**
     * Renderizar campo genérico
     * 
     * @param array $args Argumentos del campo
     */
    public static function render_field($args) {
        $defaults = [
            'type' => 'text',
            'name' => '',
            'value' => '',
            'label' => '',
            'description' => '',
            'required' => false,
            'class' => 'gdm-field-control',
            'placeholder' => '',
            'options' => [], // Para selects
            'min' => null,
            'max' => null,
            'step' => null,
            'suffix' => '', // Para mostrar símbolo al lado
            'wrapper_class' => 'gdm-field-row'
        ];
        
        $args = wp_parse_args($args, $defaults);
        extract($args);
        
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <?php if ($label): ?>
                <label class="gdm-field-label" <?php if ($name) echo 'for="' . esc_attr($name) . '"'; ?>>
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if ($required): ?>
                        <span class="required" style="color: #d63638;">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <div class="gdm-field-input-wrapper">
                <?php self::render_input_by_type($args); ?>
                
                <?php if ($suffix): ?>
                    <span class="gdm-field-suffix"><?php echo esc_html($suffix); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($description): ?>
                <p class="gdm-field-help"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar input según tipo
     */
    private static function render_input_by_type($args) {
        extract($args);
        
        $base_attrs = [
            'name' => $name,
            'id' => $name,
            'class' => $class,
            'placeholder' => $placeholder
        ];
        
        if ($required) {
            $base_attrs['required'] = 'required';
        }
        
        switch ($type) {
            case 'select':
                self::render_select($base_attrs, $value, $options);
                break;
                
            case 'textarea':
                self::render_textarea($base_attrs, $value);
                break;
                
            case 'number':
                $number_attrs = $base_attrs;
                if ($min !== null) $number_attrs['min'] = $min;
                if ($max !== null) $number_attrs['max'] = $max;
                if ($step !== null) $number_attrs['step'] = $step;
                self::render_input('number', $number_attrs, $value);
                break;
                
            case 'checkbox':
                self::render_checkbox($base_attrs, $value, $label ?? '');
                break;
                
            case 'email':
                self::render_input('email', $base_attrs, $value);
                break;
                
            case 'url':
                self::render_input('url', $base_attrs, $value);
                break;
                
            case 'password':
                self::render_input('password', $base_attrs, $value);
                break;
                
            default: // text
                self::render_input('text', $base_attrs, $value);
        }
    }
    
    /**
     * Renderizar input genérico
     */
    private static function render_input($type, $attrs, $value) {
        ?>
        <input type="<?php echo esc_attr($type); ?>" 
               value="<?php echo esc_attr($value); ?>"
               <?php self::render_attributes($attrs); ?>>
        <?php
    }
    
    /**
     * Renderizar select
     */
    private static function render_select($attrs, $value, $options) {
        ?>
        <select <?php self::render_attributes($attrs); ?>>
            <?php foreach ($options as $opt_value => $opt_label): ?>
                <option value="<?php echo esc_attr($opt_value); ?>" 
                        <?php selected($value, $opt_value); ?>>
                    <?php echo esc_html($opt_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Renderizar textarea
     */
    private static function render_textarea($attrs, $value) {
        ?>
        <textarea <?php self::render_attributes($attrs); ?>><?php echo esc_textarea($value); ?></textarea>
        <?php
    }
    
    /**
     * Renderizar checkbox
     */
    private static function render_checkbox($attrs, $value, $label) {
        ?>
        <label class="gdm-checkbox-label">
            <input type="checkbox" 
                   value="1"
                   <?php checked($value, true); ?>
                   <?php self::render_attributes($attrs); ?>>
            <span><?php echo esc_html($label); ?></span>
        </label>
        <?php
    }
    
    /**
     * Renderizar atributos HTML
     */
    private static function render_attributes($attrs) {
        foreach ($attrs as $attr => $value) {
            if ($value !== '' && $value !== null) {
                echo sprintf('%s="%s" ', esc_attr($attr), esc_attr($value));
            }
        }
    }
    
    /**
     * Método de conveniencia para campos de acción
     * Genera automáticamente el name basado en action_id
     */
    public static function action_field($action_id, $field_name, $args = []) {
        $args['name'] = "gdm_actions[{$action_id}][options][{$field_name}]";
        
        if (!isset($args['id'])) {
            $args['id'] = "gdm_action_{$action_id}_{$field_name}";
        }
        
        return self::render_field($args);
    }
    
    /**
     * Shortcuts para campos comunes
     */
    public static function text_field($name, $value = '', $label = '', $description = '') {
        return self::render_field([
            'type' => 'text',
            'name' => $name,
            'value' => $value,
            'label' => $label,
            'description' => $description
        ]);
    }
    
    public static function number_field($name, $value = 0, $label = '', $min = null, $max = null, $step = '0.01') {
        return self::render_field([
            'type' => 'number',
            'name' => $name,
            'value' => $value,
            'label' => $label,
            'min' => $min,
            'max' => $max,
            'step' => $step
        ]);
    }
    
    public static function select_field($name, $value = '', $options = [], $label = '', $description = '') {
        return self::render_field([
            'type' => 'select',
            'name' => $name,
            'value' => $value,
            'options' => $options,
            'label' => $label,
            'description' => $description
        ]);
    }
    
    public static function checkbox_field($name, $checked = false, $label = '', $description = '') {
        return self::render_field([
            'type' => 'checkbox',
            'name' => $name,
            'value' => $checked,
            'label' => $label,
            'description' => $description
        ]);
    }
}