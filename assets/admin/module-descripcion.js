/**
 * JavaScript para Módulo de Descripción
 * Gestión de variantes, comodines y funcionalidad dinámica
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // VARIABLES GLOBALES
    // =========================================================================

    let varianteIndex = $('#gdm-variantes-tbody .gdm-variante-row').length;

    // =========================================================================
    // GESTIÓN DE VARIANTES
    // =========================================================================

    /**
     * Inicializar sortable para variantes
     */
    function initVariantesSortable() {
        $('#gdm-variantes-tbody').sortable({
            handle: '.sort-handle',
            placeholder: 'gdm-sortable-placeholder',
            axis: 'y',
            update: function() {
                updateVariantesIndexes();
                updateVariantesCounter();
            }
        });
    }

    /**
     * Actualizar índices de variantes después de reordenar
     */
    function updateVariantesIndexes() {
        $('#gdm-variantes-tbody .gdm-variante-row').each(function(index) {
            $(this).attr('data-index', index);
            $(this).find('input, select, textarea').each(function() {
                if (this.name) {
                    this.name = this.name.replace(/\[(\d+|__INDEX__)\]/, '[' + index + ']');
                }
            });
        });
    }

    /**
     * Actualizar contador de variantes
     */
    function updateVariantesCounter() {
        const total = $('#gdm-variantes-tbody .gdm-variante-row').length;
        const selected = $('#gdm-variantes-tbody .gdm-variante-checkbox:checked').length;
        
        let text = 'Total: ' + total + ' variante' + (total !== 1 ? 's' : '');
        if (selected > 0) {
            text += ' | Seleccionadas: ' + selected;
        }
        
        $('#gdm-variantes-counter').text(text);
        
        // Habilitar/deshabilitar botón eliminar
        $('#gdm-delete-selected-variantes').prop('disabled', selected === 0);
    }

    /**
     * Agregar nueva variante
     */
    $('#gdm-add-variante').on('click', function(e) {
        e.preventDefault();
        
        const template = $('#gdm-variante-template').html();
        const newRow = template.replace(/__INDEX__/g, varianteIndex);
        
        $('#gdm-variantes-tbody').append(newRow);
        
        // Aplicar lógica de visibilidad a la nueva fila
        const $newRow = $('#gdm-variantes-tbody .gdm-variante-row').last();
        handleVarianteRowVisibility($newRow);
        
        varianteIndex++;
        updateVariantesCounter();
    });

    /**
     * Eliminar variantes seleccionadas
     */
    $('#gdm-delete-selected-variantes').on('click', function(e) {
        e.preventDefault();
        
        const $selected = $('#gdm-variantes-tbody .gdm-variante-checkbox:checked');
        const count = $selected.length;
        
        if (count === 0) return;
        
        const message = count === 1 
            ? gdmModuloDescripcion.i18n.deleteConfirm
            : gdmModuloDescripcion.i18n.deleteMultipleConfirm.replace('%d', count);
        
        if (!confirm(message)) return;
        
        $selected.closest('.gdm-variante-row').fadeOut(200, function() {
            $(this).remove();
            updateVariantesIndexes();
            updateVariantesCounter();
        });
    });

    /**
     * Actualizar checkboxes
     */
    $(document).on('change', '.gdm-variante-checkbox', function() {
        updateVariantesCounter();
    });

    /**
     * Manejar cambio de tipo de condición
     */
    $(document).on('change', '.gdm-variante-cond-type', function() {
        const $row = $(this).closest('.gdm-variante-row');
        handleVarianteRowVisibility($row);
    });

    /**
     * Manejar visibilidad de campos según tipo de condición
     */
    function handleVarianteRowVisibility(row) {
        if (!row || !row.length) return;
        
        const condType = row.find('.gdm-variante-cond-type').val();
        const $keyField = row.find('.gdm-variante-cond-key');
        const $valueField = row.find('.gdm-variante-cond-value');

        if (condType === 'tag') {
            $keyField.prop('disabled', false).attr('placeholder', 'slug-del-tag');
            $valueField.prop('disabled', true).val('');
        } else if (condType === 'meta') {
            $keyField.prop('disabled', false).attr('placeholder', 'meta_key');
            $valueField.prop('disabled', false).attr('placeholder', 'valor (opcional)');
        } else { // default
            $keyField.prop('disabled', true).val('');
            $valueField.prop('disabled', true).val('');
        }
    }

    // =========================================================================
    // INSERCIÓN DE COMODINES
    // =========================================================================

    /**
     * Insertar shortcode en el editor
     */
    $(document).on('click', '.gdm-insert-shortcode', function(e) {
        e.preventDefault();
        
        const shortcode = $(this).data('shortcode');
        const editorId = 'gdm_descripcion_contenido';
        
        // Verificar si es TinyMCE o modo texto
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
            // TinyMCE (visual)
            tinymce.get(editorId).execCommand('mceInsertContent', false, shortcode);
        } else {
            // Textarea (texto)
            const $textarea = $('#' + editorId);
            const cursorPos = $textarea.prop('selectionStart');
            const textBefore = $textarea.val().substring(0, cursorPos);
            const textAfter = $textarea.val().substring(cursorPos);
            
            $textarea.val(textBefore + shortcode + textAfter);
            
            // Restaurar cursor
            const newPos = cursorPos + shortcode.length;
            $textarea.prop('selectionStart', newPos);
            $textarea.prop('selectionEnd', newPos);
            $textarea.focus();
        }
    });

    /**
     * Insertar regla reutilizable (rule-id)
     */
    $(document).on('click', '.gdm-insert-rule-id', function(e) {
        e.preventDefault();
        
        // Obtener reglas reutilizables vía AJAX
        $.ajax({
            url: gdmModuloDescripcion.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_get_reusable_rules',
                nonce: gdmModuloDescripcion.nonce,
                current_post_id: $('#post_ID').val()
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    showRuleSelectionModal(response.data);
                } else {
                    alert('No hay reglas reutilizables disponibles. Crea una regla y marca "Regla Reutilizable" en la configuración general.');
                }
            },
            error: function() {
                alert('Error al cargar las reglas reutilizables.');
            }
        });
    });

    /**
     * Mostrar modal de selección de regla
     */
    function showRuleSelectionModal(rules) {
        // Crear modal dinámico
        const modalHtml = `
            <div id="gdm-rule-selection-modal" class="gdm-modal">
                <div class="gdm-modal-content">
                    <div class="gdm-modal-header">
                        <h3>${gdmModuloDescripcion.i18n.selectRule}</h3>
                        <button type="button" class="gdm-modal-close">&times;</button>
                    </div>
                    <div class="gdm-modal-body">
                        <select id="gdm-selected-rule" class="widefat">
                            <option value="">-- Seleccionar --</option>
                            ${rules.map(rule => `<option value="${rule.id}">${rule.title} (ID: ${rule.id})</option>`).join('')}
                        </select>
                    </div>
                    <div class="gdm-modal-footer">
                        <button type="button" class="button button-primary gdm-modal-insert">
                            ${gdmModuloDescripcion.i18n.insert}
                        </button>
                        <button type="button" class="button gdm-modal-cancel">
                            ${gdmModuloDescripcion.i18n.cancel}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Agregar al DOM
        $('body').append(modalHtml);
        
        // Eventos del modal
        $('#gdm-rule-selection-modal .gdm-modal-close, #gdm-rule-selection-modal .gdm-modal-cancel').on('click', function() {
            $('#gdm-rule-selection-modal').remove();
        });
        
        $('#gdm-rule-selection-modal .gdm-modal-insert').on('click', function() {
            const ruleId = $('#gdm-selected-rule').val();
            
            if (!ruleId) {
                alert('Selecciona una regla');
                return;
            }
            
            const shortcode = `[rule-${ruleId}]`;
            const editorId = 'gdm_descripcion_contenido';
            
            // Insertar en editor
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
                tinymce.get(editorId).execCommand('mceInsertContent', false, shortcode);
            } else {
                const $textarea = $('#' + editorId);
                const cursorPos = $textarea.prop('selectionStart');
                const textBefore = $textarea.val().substring(0, cursorPos);
                const textAfter = $textarea.val().substring(cursorPos);
                
                $textarea.val(textBefore + shortcode + textAfter);
            }
            
            $('#gdm-rule-selection-modal').remove();
        });
    }

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    // Inicializar sortable
    initVariantesSortable();
    
    // Aplicar lógica de visibilidad a filas existentes
    $('#gdm-variantes-tbody .gdm-variante-row').each(function() {
        handleVarianteRowVisibility($(this));
    });
    
    // Actualizar contador inicial
    updateVariantesCounter();
    
    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Módulo Descripción: Inicializado correctamente');
    }
});