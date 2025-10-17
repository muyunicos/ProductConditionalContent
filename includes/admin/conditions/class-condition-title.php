<?php
/**
 * Ámbito: Filtro por Título
 * Filtrar productos por coincidencias en el título
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Condition_Title extends GDM_Condition_Base {
    
    protected $condition_id = 'product-title';
    protected $condition_name = 'Filtro por Título';
    protected $condition_description = 'Filtra productos por contenido en el título';
    protected $condition_icon = '📝';
    protected $priority = 70;
    protected $supported_contexts = ['products'];
    
    protected function render_content($post_id, $data) {
        ?>
        <div class="gdm-<?php echo esc_attr($this->condition_id); ?>-fields">
            <div class="gdm-field-group">
                <label><strong><?php _e('Condición:', 'product-conditional-content'); ?></strong></label>
                <select name="gdm_<?php echo esc_attr($this->condition_id); ?>_condicion" class="regular-text">
                    <option value="contiene" <?php selected($data['condicion'], 'contiene'); ?>>
                        <?php _e('Contiene', 'product-conditional-content'); ?>
                    </option>
                    <option value="no_contiene" <?php selected($data['condicion'], 'no_contiene'); ?>>
                        <?php _e('No contiene', 'product-conditional-content'); ?>
                    </option>
                    <option value="empieza_con" <?php selected($data['condicion'], 'empieza_con'); ?>>
                        <?php _e('Empieza con', 'product-conditional-content'); ?>
                    </option>
                    <option value="termina_con" <?php selected($data['condicion'], 'termina_con'); ?>>
                        <?php _e('Termina con', 'product-conditional-content'); ?>
                    </option>
                    <option value="regex" <?php selected($data['condicion'], 'regex'); ?>>
                        <?php _e('Expresión Regular (Regex)', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <div class="gdm-field-group">
                <label><strong><?php _e('Texto:', 'product-conditional-content'); ?></strong></label>
                <input type="text" 
                       name="gdm_<?php echo esc_attr($this->condition_id); ?>_texto" 
                       value="<?php echo esc_attr($data['texto']); ?>" 
                       class="regular-text"
                       placeholder="<?php esc_attr_e('Texto a buscar', 'product-conditional-content'); ?>">
            </div>
            
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_<?php echo esc_attr($this->condition_id); ?>_case_sensitive" 
                           value="1" 
                           <?php checked($data['case_sensitive'], '1'); ?>>
                    <?php _e('Distinguir mayúsculas/minúsculas', 'product-conditional-content'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $this->save_field($post_id, 'condicion', isset($_POST["gdm_{$this->condition_id}_condicion"]) ? sanitize_text_field($_POST["gdm_{$this->condition_id}_condicion"]) : 'contiene');
        $this->save_field($post_id, 'texto', isset($_POST["gdm_{$this->condition_id}_texto"]) ? sanitize_text_field($_POST["gdm_{$this->condition_id}_texto"]) : '');
        $this->save_field($post_id, 'case_sensitive', isset($_POST["gdm_{$this->condition_id}_case_sensitive"]) ? '1' : '0');
    }
    
    protected function get_default_data() {
        return [
            'condicion' => 'contiene',
            'texto' => '',
            'case_sensitive' => '0',
        ];
    }
    
    protected function has_selection($data) {
        return !empty($data['texto']);
    }
    
    protected function get_summary($data) {
        if (empty($data['texto'])) {
            return '';
        }
        
        $conditions = [
            'contiene' => __('Contiene', 'product-conditional-content'),
            'no_contiene' => __('No contiene', 'product-conditional-content'),
            'empieza_con' => __('Empieza con', 'product-conditional-content'),
            'termina_con' => __('Termina con', 'product-conditional-content'),
            'regex' => __('Regex', 'product-conditional-content'),
        ];
        
        return sprintf('%s: "%s"', $conditions[$data['condicion']], $data['texto']);
    }
    
    protected function get_counter_text($data) {
        return !empty($data['texto']) ? 'Configurado' : 'Sin configurar';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_condition_data($rule_id);
        
        if (empty($data['texto'])) {
            return true;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $title = $product->get_name();
        $search = $data['texto'];
        
        if ($data['case_sensitive'] !== '1') {
            $title = strtolower($title);
            $search = strtolower($search);
        }
        
        switch ($data['condicion']) {
            case 'contiene':
                return strpos($title, $search) !== false;
            case 'no_contiene':
                return strpos($title, $search) === false;
            case 'empieza_con':
                return strpos($title, $search) === 0;
            case 'termina_con':
                return substr($title, -strlen($search)) === $search;
            case 'regex':
                return @preg_match($search, $product->get_name()) === 1;
            default:
                return true;
        }
    }
    
    protected function render_styles() {
        ?>
        <style>
            .gdm-<?php echo esc_attr($this->condition_id); ?>-fields {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
        </style>
        <?php
    }
}