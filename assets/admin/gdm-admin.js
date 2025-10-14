/**
 * Admin Script para Motor de Reglas MuyUnicos
 * Versión: 5.0.0 - Enhanced UX Completo
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * Fecha: 2025-10-14
 */
jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // MANEJO DE VARIANTES CON DRAG & DROP
    // =========================================================================

    /**
     * Actualiza estado de campos según tipo de condición
     */
    function handleRepeaterRowVisibility(row) {
        if (!row || !row.length) return;
        
        const condType = row.find('.gdm-cond-type').val();
        const keyField = row.find('.gdm-cond-key');
        const valueField = row.find('.gdm-cond-value');

        if (condType === 'tag') {
            keyField.prop('disabled', false).attr('placeholder', 'slug-del-tag');
            valueField.prop('disabled', true).val('');
        } else if (condType === 'meta') {
            keyField.prop('disabled', false).attr('placeholder', 'meta_key');
            valueField.prop('disabled', false).attr('placeholder', 'valor');
        } else { // default
            keyField.prop('disabled', true).val('');
            valueField.prop('disabled', true).val('');
        }
    }

    /**
     * Actualiza índices de las filas después de reordenar
     */
    function updateRepeaterIndexes() {
        $('#gdm-repeater-tbody .gdm-repeater-row').each(function(index) {
            $(this).attr('data-index', index);
            $(this).find('input, select, textarea').each(function() {
                if (this.name) {
                    this.name = this.name.replace(/\[(\d+|__INDEX__)\]/, '[' + index + ']');
                }
            });
        });
    }

    /**
     * Actualiza contador de variantes
     */
    function updateVariantCounter() {
        const total = $('#gdm-repeater-tbody .gdm-repeater-row').length;
        const selected = $('#gdm-repeater-tbody .gdm-row-checkbox:checked').length;
        
        let text = 'Total: ' + total + ' variante' + (total !== 1 ? 's' : '');
        if (selected > 0) {
            text += ' | Seleccionadas: ' + selected;
        }
        
        $('#gdm-variant-counter').text(text);
        
        // Habilitar/deshabilitar botón eliminar
        $('#gdm-delete-selected').prop('disabled', selected === 0);
    }

    /**
     * Añadir nueva variante
     */
    $('#gdm-add-repeater-row').on('click', function(e) {
        e.preventDefault();
        
        const template = $('#gdm-repeater-template').html();
        if (!template) {
            console.error('Plantilla de variante no encontrada');
            return;
        }
        
        const newIndex = $('#gdm-repeater-tbody .gdm-repeater-row').length;
        const newRowHTML = template.replace(/__INDEX__/g, newIndex);
        const newRow = $(newRowHTML);
        
        $('#gdm-repeater-tbody').append(newRow);
        handleRepeaterRowVisibility(newRow);
        updateRepeaterIndexes();
        updateVariantCounter();
        
        // Scroll suave a la nueva fila
        $('html, body').animate({
            scrollTop: newRow.offset().top - 100
        }, 300);
    });

    /**
     * Cambio en tipo de condición
     */
    $('#gdm-repeater-tbody').on('change', '.gdm-cond-type', function() {
        const row = $(this).closest('.gdm-repeater-row');
        handleRepeaterRowVisibility(row);
    });

    /**
     * Cambio en checkbox de fila (resaltar)
     */
    $('#gdm-repeater-tbody').on('change', '.gdm-row-checkbox', function() {
        const row = $(this).closest('.gdm-repeater-row');
        row.toggleClass('gdm-row-selected', $(this).is(':checked'));
        updateVariantCounter();
    });

    /**
     * Seleccionar/deseleccionar todas las variantes
     */
    $('#gdm-select-all-variants').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('#gdm-repeater-tbody .gdm-row-checkbox').prop('checked', isChecked).trigger('change');
    });

    /**
     * Eliminar variantes seleccionadas
     */
    $('#gdm-delete-selected').on('click', function(e) {
        e.preventDefault();
        
        const selected = $('#gdm-repeater-tbody .gdm-row-checkbox:checked');
        const count = selected.length;
        
        if (count === 0) return;
        
        const confirmMsg = count === 1 
            ? gdmAdmin.i18n.deleteConfirm
            : gdmAdmin.i18n.deleteMultipleConfirm.replace('%d', count);
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        selected.closest('.gdm-repeater-row').fadeOut(300, function() {
            $(this).remove();
            updateRepeaterIndexes();
            updateVariantCounter();
            $('#gdm-select-all-variants').prop('checked', false);
        });
    });

    /**
     * Inicializar sortable con jQuery UI
     */
    if ($.fn.sortable) {
        $('#gdm-repeater-tbody').sortable({
            handle: '.sort-handle',
            placeholder: 'gdm-sortable-placeholder',
            opacity: 0.6,
            cursor: 'move',
            axis: 'y',
            update: function() {
                updateRepeaterIndexes();
            }
        });
    }

    /**
     * Inicializar estados al cargar
     */
    $('#gdm-repeater-tbody .gdm-repeater-row').each(function() {
        handleRepeaterRowVisibility($(this));
    });
    updateVariantCounter();

    // =========================================================================
    // FILTROS DE CATEGORÍAS Y TAGS
    // =========================================================================

    /**
     * Filtro en tiempo real para categorías
     */
    $('#gdm_category_filter').on('input', function() {
        const filterValue = $(this).val().toLowerCase().trim();
        
        $('#gdm_category_list_wrapper .gdm-filterable-item').each(function() {
            const itemName = $(this).data('name') || '';
            const matches = itemName.indexOf(filterValue) !== -1;
            $(this).toggle(matches);
        });
    });

    /**
     * Filtro en tiempo real para tags
     */
    $('#gdm_tag_filter').on('input', function() {
        const filterValue = $(this).val().toLowerCase().trim();
        
        $('#gdm_tag_list_wrapper .gdm-filterable-item').each(function() {
            const itemName = $(this).data('name') || '';
            const matches = itemName.indexOf(filterValue) !== -1;
            $(this).toggle(matches);
        });
    });

    /**
     * Toggle "Todas las categorías"
     */
    $('#gdm_todas_categorias').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('#gdm_category_list_wrapper').toggleClass('gdm-disabled', isChecked);
        $('#gdm_category_list_wrapper input[type="checkbox"]').prop('disabled', isChecked);
    }).trigger('change');

    /**
     * Toggle "Cualquier tag"
     */
    $('#gdm_cualquier_tag').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('#gdm_tag_list_wrapper').toggleClass('gdm-disabled', isChecked);
        $('#gdm_tag_list_wrapper input[type="checkbox"]').prop('disabled', isChecked);
    }).trigger('change');

    // =========================================================================
    // BOTONES DE SHORTCODES
    // =========================================================================

    /**
     * Insertar shortcode simple en el editor
     */
    $('.gdm-insert-shortcode').on('click', function(e) {
        e.preventDefault();
        
        const shortcode = $(this).data('shortcode');
        if (!shortcode) return;
        
        // Intentar insertar en TinyMCE o en textarea
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('gdm_descripcion')) {
            const editor = tinyMCE.get('gdm_descripcion');
            if (editor && !editor.isHidden()) {
                editor.execCommand('mceInsertContent', false, shortcode);
                return;
            }
        }
        
        // Fallback: insertar en textarea
        const textarea = $('#gdm_descripcion');
        if (textarea.length) {
            const cursorPos = textarea[0].selectionStart;
            const textBefore = textarea.val().substring(0, cursorPos);
            const textAfter = textarea.val().substring(cursorPos);
            textarea.val(textBefore + shortcode + textAfter);
            
            // Mover cursor después del shortcode
            const newPos = cursorPos + shortcode.length;
            textarea[0].setSelectionRange(newPos, newPos);
            textarea.focus();
        }
    });

    /**
     * Botón [rule-id] con modal AJAX
     */
    $('.gdm-insert-rule-id').on('click', function(e) {
        e.preventDefault();
        showRuleSelectionModal();
    });

    /**
     * Mostrar modal de selección de reglas reutilizables
     */
    function showRuleSelectionModal() {
        // Mostrar overlay con spinner
        const $overlay = $('#gdm-rule-modal-overlay');
        $overlay.html('<div class="gdm-modal-spinner"><span class="spinner is-active"></span><p>Cargando reglas...</p></div>').fadeIn(200);
        
        // Petición AJAX
        $.ajax({
            url: gdmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_get_reusable_rules',
                nonce: gdmAdmin.nonce,
                current_post_id: gdmAdmin.currentPostId
            },
            success: function(response) {
                if (response.success && response.data) {
                    renderRuleSelectionModal(response.data);
                } else {
                    $overlay.html('<div class="gdm-modal-content"><p>No se encontraron reglas reutilizables.</p><button type="button" class="button" id="gdm-modal-close">Cerrar</button></div>');
                }
            },
            error: function() {
                $overlay.html('<div class="gdm-modal-content"><p>Error al cargar reglas.</p><button type="button" class="button" id="gdm-modal-close">Cerrar</button></div>');
            }
        });
    }

    /**
     * Renderizar modal con lista de reglas
     */
    function renderRuleSelectionModal(rules) {
        if (!rules || rules.length === 0) {
            $('#gdm-rule-modal-overlay').html('<div class="gdm-modal-content"><p>No hay reglas reutilizables disponibles.</p><button type="button" class="button" id="gdm-modal-close">Cerrar</button></div>');
            return;
        }
        
        let html = '<div class="gdm-modal-content">';
        html += '<h2>' + gdmAdmin.i18n.selectRule + '</h2>';
        html += '<div class="gdm-rule-list">';
        
        rules.forEach(function(rule) {
            html += '<label class="gdm-rule-option">';
            html += '<input type="radio" name="gdm_selected_rule" value="' + rule.id + '">';
            html += '<span class="gdm-rule-title">' + rule.title + '</span>';
            html += '<span class="gdm-rule-id">ID: ' + rule.id + '</span>';
            html += '</label>';
        });
        
        html += '</div>';
        html += '<div class="gdm-modal-actions">';
        html += '<button type="button" class="button button-primary" id="gdm-modal-insert">' + gdmAdmin.i18n.insert + '</button>';
        html += '<button type="button" class="button" id="gdm-modal-close">' + gdmAdmin.i18n.cancel + '</button>';
        html += '</div>';
        html += '</div>';
        
        $('#gdm-rule-modal-overlay').html(html);
    }

    /**
     * Insertar regla seleccionada
     */
    $(document).on('click', '#gdm-modal-insert', function() {
        const selectedRuleId = $('input[name="gdm_selected_rule"]:checked').val();
        
        if (!selectedRuleId) {
            alert('Por favor selecciona una regla.');
            return;
        }
        
        const shortcode = '[rule-id id="' + selectedRuleId + '"]';
        
        // Intentar insertar en TinyMCE
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('gdm_descripcion')) {
            const editor = tinyMCE.get('gdm_descripcion');
            if (editor && !editor.isHidden()) {
                editor.execCommand('mceInsertContent', false, shortcode);
                $('#gdm-rule-modal-overlay').fadeOut(200);
                return;
            }
        }
        
        // Fallback: textarea
        const textarea = $('#gdm_descripcion');
        if (textarea.length) {
            const cursorPos = textarea[0].selectionStart;
            const textBefore = textarea.val().substring(0, cursorPos);
            const textAfter = textarea.val().substring(cursorPos);
            textarea.val(textBefore + shortcode + textAfter);
            textarea.focus();
        }
        
        $('#gdm-rule-modal-overlay').fadeOut(200);
    });

    /**
     * Cerrar modal
     */
    $(document).on('click', '#gdm-modal-close, #gdm-rule-modal-overlay', function(e) {
        if (e.target === this) {
            $('#gdm-rule-modal-overlay').fadeOut(200);
        }
    });

    // Cerrar modal con ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#gdm-rule-modal-overlay').is(':visible')) {
            $('#gdm-rule-modal-overlay').fadeOut(200);
        }
    });

    // =========================================================================
    // VALIDACIÓN ANTES DE PUBLICAR
    // =========================================================================

    /**
     * Validar formulario antes de submit
     */
    $('#post').on('submit', function(e) {
        const title = $('#title').val().trim();
        
        if (!title) {
            alert('Por favor ingresa un título para la regla.');
            $('#title').focus();
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // =========================================================================
    // HELPERS Y UTILIDADES
    // =========================================================================

    /**
     * Añadir tooltips a elementos con title
     */
    if ($.fn.tooltip) {
        $('[title]').tooltip({
            position: { my: "left top+10", at: "left bottom" },
            show: { effect: "fadeIn", duration: 200 },
            hide: { effect: "fadeOut", duration: 200 }
        });
    }

    /**
     * Console log para debugging (solo en desarrollo)
     */
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('GDM Admin Script v5.0.0 cargado correctamente');
        console.log('Current Post ID:', gdmAdmin.currentPostId);
    }

});