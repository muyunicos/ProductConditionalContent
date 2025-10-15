/**
 * JavaScript para Módulo de Variantes Condicionales
 * Gestión completa de variantes con drag & drop, duplicado y validación
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.2.1
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
            cursor: 'move',
            opacity: 0.8,
            tolerance: 'pointer',
            helper: function(e, tr) {
                const $originals = tr.children();
                const $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            start: function(e, ui) {
                ui.placeholder.height(ui.item.height());
            },
            update: function() {
                updateVariantesIndexes();
                updateVariantesCounter();
                updateRowNumbers();
            }
        });
    }

    /**
     * Actualizar números de fila
     */
    function updateRowNumbers() {
        $('#gdm-variantes-tbody .gdm-variante-row').each(function(index) {
            $(this).find('.gdm-variante-row-number').text(index + 1);
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
        
        let text = gdmModuloVariantes.i18n.total || 'Total: ' + total + ' variante' + (total !== 1 ? 's' : '');
        
        if (selected > 0) {
            text = 'Total: ' + total + ' | Seleccionadas: ' + selected;
        }
        
        $('#gdm-variantes-counter').text(text);
        
        // Habilitar/deshabilitar botones
        $('#gdm-delete-selected-variantes').prop('disabled', selected === 0);
        $('#gdm-duplicate-variante').prop('disabled', selected !== 1);
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
        updateRowNumbers();
        updateVariantesCounter();
        
        // Scroll suave hacia la nueva fila
        $('html, body').animate({
            scrollTop: $newRow.offset().top - 100
        }, 300);
    });

    /**
     * Duplicar variante seleccionada
     */
    $('#gdm-duplicate-variante').on('click', function(e) {
        e.preventDefault();
        
        const $selected = $('#gdm-variantes-tbody .gdm-variante-checkbox:checked');
        
        if ($selected.length !== 1) {
            alert(gdmModuloVariantes.i18n.selectOne || 'Selecciona solo una variante para duplicar');
            return;
        }
        
        const $row = $selected.closest('.gdm-variante-row');
        const $clone = $row.clone();
        
        // Actualizar índices del clon
        $clone.attr('data-index', varianteIndex);
        $clone.find('input, select, textarea').each(function() {
            if (this.name) {
                this.name = this.name.replace(/\[(\d+)\]/, '[' + varianteIndex + ']');
            }
            // Desmarcar checkbox
            if ($(this).hasClass('gdm-variante-checkbox')) {
                $(this).prop('checked', false);
            }
        });
        
        // Insertar después de la fila original
        $row.after($clone);
        
        varianteIndex++;
        updateRowNumbers();
        updateVariantesCounter();
        handleVarianteRowVisibility($clone);
        
        // Scroll suave hacia el duplicado
        $('html, body').animate({
            scrollTop: $clone.offset().top - 100
        }, 300);
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
            ? gdmModuloVariantes.i18n.deleteConfirm
            : gdmModuloVariantes.i18n.deleteMultipleConfirm.replace('%d', count);
        
        if (!confirm(message)) return;
        
        $selected.closest('.gdm-variante-row').fadeOut(200, function() {
            $(this).remove();
            updateVariantesIndexes();
            updateVariantesCounter();
            updateRowNumbers();
        });
    });

    /**
     * Actualizar checkboxes individuales
     */
    $(document).on('change', '.gdm-variante-checkbox', function() {
        updateVariantesCounter();
    });

    /**
     * Seleccionar/deseleccionar todas
     */
    $('#gdm-select-all-variantes').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.gdm-variante-checkbox').prop('checked', isChecked);
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
        } else if (condType === 'attribute') {
            $keyField.prop('disabled', false).attr('placeholder', 'pa_atributo');
            $valueField.prop('disabled', false).attr('placeholder', 'valor del atributo');
        } else { // default
            $keyField.prop('disabled', true).val('');
            $valueField.prop('disabled', true).val('');
        }
    }

    /**
     * Toggle fallback
     */
    $('#gdm_variantes_fallback_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#gdm-fallback-wrapper').slideDown(200);
        } else {
            $('#gdm-fallback-wrapper').slideUp(200);
        }
    });

    // =========================================================================
    // VALIDACIÓN ANTES DE GUARDAR
    // =========================================================================

    $('form#post').on('submit', function(e) {
        const $variantes = $('#gdm-variantes-tbody .gdm-variante-row');
        let hasError = false;
        
        $variantes.each(function() {
            const $row = $(this);
            const condType = $row.find('.gdm-variante-cond-type').val();
            const condKey = $row.find('.gdm-variante-cond-key').val();
            const text = $row.find('textarea').val();
            
            // Validar que si no es "default", debe tener clave
            if (condType !== 'default' && !condKey.trim()) {
                $row.find('.gdm-variante-cond-key').css('border-color', '#dc3232');
                hasError = true;
            } else {
                $row.find('.gdm-variante-cond-key').css('border-color', '');
            }
            
            // Validar que tenga texto
            if (!text.trim()) {
                $row.find('textarea').css('border-color', '#dc3232');
                hasError = true;
            } else {
                $row.find('textarea').css('border-color', '');
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Por favor completa todos los campos requeridos de las variantes');
            $('html, body').animate({
                scrollTop: $('.gdm-module-variantes').offset().top - 100
            }, 300);
            return false;
        }
    });

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
    updateRowNumbers();
    
    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Módulo Variantes: Inicializado correctamente');
        console.log('📊 Total de variantes:', $('#gdm-variantes-tbody .gdm-variante-row').length);
    }
});