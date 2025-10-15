<?php
/**
 * Ámbito: Categorías Determinadas
 * Filtrar productos por categorías
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Scope_Categories extends GDM_Scope_Base {
    
    protected $scope_id = 'categorias';
    protected $scope_name = 'Categorías Determinadas';
    protected $scope_icon = '📂';
    protected $priority = 10;
    
    /**
     * Renderizar contenido del ámbito
     */
    protected function render_content($post_id, $data) {
        ?>
        <input type="text" 
               id="gdm-<?php echo esc_attr($this->scope_id); ?>-filter" 
               class="gdm-filter-input" 
               placeholder="<?php esc_attr_e('🔍 Buscar categorías...', 'product-conditional-content'); ?>">
        
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-list gdm-scope-list">
            <?php
            $categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ]);
            
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $checked = in_array($cat->term_id, $data['objetivo']);
                    ?>
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_<?php echo esc_attr($this->scope_id); ?>_objetivo[]" 
                               value="<?php echo esc_attr($cat->term_id); ?>"
                               class="gdm-scope-item-checkbox"
                               <?php checked($checked); ?>>
                        <span><?php echo esc_html($cat->name); ?></span>
                        <span class="gdm-item-count">(<?php echo esc_html($cat->count); ?>)</span>
                    </label>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Guardar datos
     */
    public function save($post_id) {
        $objetivo = isset($_POST["gdm_{$this->scope_id}_objetivo"]) 
            ? array_map('intval', $_POST["gdm_{$this->scope_id}_objetivo"]) 
            : [];
        
        $this->save_field($post_id, 'objetivo', $objetivo);
    }
    
    /**
     * Datos por defecto
     */
    protected function get_default_data() {
        return [
            'objetivo' => [],
        ];
    }
    
    /**
     * Verificar si hay selección
     */
    protected function has_selection($data) {
        return !empty($data['objetivo']);
    }
    
    /**
     * Obtener resumen
     */
    protected function get_summary($data) {
        if (empty($data['objetivo'])) {
            return '';
        }
        
        $names = [];
        foreach ($data['objetivo'] as $cat_id) {
            $cat = get_term($cat_id, 'product_cat');
            if ($cat && !is_wp_error($cat)) {
                $names[] = $cat->name;
            }
        }
        
        if (count($names) <= 3) {
            return implode(', ', $names);
        }
        
        return implode(', ', array_slice($names, 0, 3)) . 
               ' <em>y ' . (count($names) - 3) . ' más</em>';
    }
    
    /**
     * Texto del contador
     */
    protected function get_counter_text($data) {
        $count = count($data['objetivo']);
        return $count > 0 ? "{$count} seleccionadas" : 'Ninguna seleccionada';
    }
    
    /**
     * Verificar si producto cumple condiciones
     */
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_scope_data($rule_id);
        
        // Si no hay selección, cumple por defecto
        if (empty($data['objetivo'])) {
            return true;
        }
        
        // Obtener categorías del producto
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        
        // Verificar si tiene alguna de las categorías seleccionadas
        return !empty(array_intersect($data['objetivo'], $product_cats));
    }
    
    /**
     * Scripts específicos
     */
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Filtro de búsqueda
            $('#gdm-<?php echo esc_js($this->scope_id); ?>-filter').on('keyup', function() {
                var search = $(this).val().toLowerCase();
                var $list = $('.gdm-<?php echo esc_js($this->scope_id); ?>-list');
                
                $list.find('.gdm-checkbox-item').each(function() {
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(search) > -1);
                });
            });
            
            // Actualizar contador al cambiar
            $('.gdm-<?php echo esc_js($this->scope_id); ?>-list input').on('change', function() {
                var count = $('.gdm-<?php echo esc_js($this->scope_id); ?>-list input:checked').length;
                $('#gdm-<?php echo esc_js($this->scope_id); ?>-counter').text(
                    count > 0 ? count + ' seleccionadas' : 'Ninguna seleccionada'
                );
            });
        });
        </script>
        <?php
    }
}