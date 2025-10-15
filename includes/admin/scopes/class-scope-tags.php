<?php
/**
 * Ámbito: Etiquetas Determinadas
 * Filtrar productos por etiquetas
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Scope_Tags extends GDM_Scope_Base {
    
    protected $scope_id = 'tags';
    protected $scope_name = 'Etiquetas Determinadas';
    protected $scope_icon = '🏷️';
    protected $priority = 20;
    
    protected function render_content($post_id, $data) {
        ?>
        <input type="text" 
               id="gdm-<?php echo esc_attr($this->scope_id); ?>-filter" 
               class="gdm-filter-input" 
               placeholder="<?php esc_attr_e('🔍 Buscar etiquetas...', 'product-conditional-content'); ?>">
        
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-list gdm-scope-list">
            <?php
            $tags = get_terms([
                'taxonomy' => 'product_tag',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ]);
            
            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $checked = in_array($tag->term_id, $data['objetivo']);
                    ?>
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_<?php echo esc_attr($this->scope_id); ?>_objetivo[]" 
                               value="<?php echo esc_attr($tag->term_id); ?>"
                               class="gdm-scope-item-checkbox"
                               <?php checked($checked); ?>>
                        <span><?php echo esc_html($tag->name); ?></span>
                        <span class="gdm-item-count">(<?php echo esc_html($tag->count); ?>)</span>
                    </label>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $objetivo = isset($_POST["gdm_{$this->scope_id}_objetivo"]) 
            ? array_map('intval', $_POST["gdm_{$this->scope_id}_objetivo"]) 
            : [];
        
        $this->save_field($post_id, 'objetivo', $objetivo);
    }
    
    protected function get_default_data() {
        return ['objetivo' => []];
    }
    
    protected function has_selection($data) {
        return !empty($data['objetivo']);
    }
    
    protected function get_summary($data) {
        if (empty($data['objetivo'])) {
            return '';
        }
        
        $names = [];
        foreach ($data['objetivo'] as $tag_id) {
            $tag = get_term($tag_id, 'product_tag');
            if ($tag && !is_wp_error($tag)) {
                $names[] = $tag->name;
            }
        }
        
        if (count($names) <= 3) {
            return implode(', ', $names);
        }
        
        return implode(', ', array_slice($names, 0, 3)) . 
               ' <em>y ' . (count($names) - 3) . ' más</em>';
    }
    
    protected function get_counter_text($data) {
        $count = count($data['objetivo']);
        return $count > 0 ? "{$count} seleccionadas" : 'Ninguna seleccionada';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_scope_data($rule_id);
        
        if (empty($data['objetivo'])) {
            return true;
        }
        
        $product_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']);
        
        return !empty(array_intersect($data['objetivo'], $product_tags));
    }
    
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#gdm-<?php echo esc_js($this->scope_id); ?>-filter').on('keyup', function() {
                var search = $(this).val().toLowerCase();
                $('.gdm-<?php echo esc_js($this->scope_id); ?>-list .gdm-checkbox-item').each(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
                });
            });
            
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