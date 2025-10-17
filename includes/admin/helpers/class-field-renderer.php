<?php
class GDM_Field_Renderer {
    
    public static function render_field($args) {
        $defaults = [
            'type' => 'text',
            'name' => '',
            'value' => '',
            'label' => '',
            'description' => '',
            'required' => false,
            'class' => 'gdm-field-control'
        ];
        
        $args = wp_parse_args($args, $defaults);
        extract($args);
        
        ?>
        <div class="gdm-field-row">
            <?php if ($label): ?>
                <label class="gdm-field-label">
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if ($required): ?><span class="required">*</span><?php endif; ?>
                </label>
            <?php endif; ?>
            
            <?php self::render_input_by_type($type, $name, $value, $class); ?>
            
            <?php if ($description): ?>
                <p class="gdm-field-help"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private static function render_input_by_type($type, $name, $value, $class) {
        switch ($type) {
            case 'select':
                // Renderizar select
                break;
            case 'textarea':
                // Renderizar textarea
                break;
            case 'number':
                ?>
                <input type="number" 
                       name="<?php echo esc_attr($name); ?>" 
                       value="<?php echo esc_attr($value); ?>"
                       class="<?php echo esc_attr($class); ?>">
                <?php
                break;
            default:
                ?>
                <input type="text" 
                       name="<?php echo esc_attr($name); ?>" 
                       value="<?php echo esc_attr($value); ?>"
                       class="<?php echo esc_attr($class); ?>">
                <?php
        }
    }
}
