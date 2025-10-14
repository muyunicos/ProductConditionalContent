/**
 * Admin Script para Motor de Reglas MuyUnicos
 * Versión: 4.1.0 - Enhanced UX
 */
jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // MANEJO DE VARIANTES
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
            valueField.prop('disabled', false);
        } else { // default
            keyField.prop('disabled', true).val('');
            valueField.prop('disabled', true).val('');
        }
    }

    /**
     * Actualiza índices de las filas
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
            console.error('Plantilla no encontrada');
            return;
        }
        
        const newIndex = $('#gdm-repeater-tbody .gdm-repeater-row').length;
        const newRowHTML = template.replace(/__INDEX__/g, newIndex);
        const newRow = $(newRowHTML);
        
        $('#gdm-repeater-tbody').append(newRow);
        handleRepeaterRowVisibility(newRow);
        updateRepeaterIndexes();
        updateVariantCounter();
        
        // Scroll suave
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
            ? '¿Eliminar la variante seleccionada?'
            : '¿Eliminar ' + count + ' variantes seleccionadas?';
        
        if (!confirm(confirmMsg)) return;
        
        selected.closest('.gdm-repeater-row').fadeOut(200, function() {
            $(this).remove();
            updateRepeaterIndexes();
            updateVariantCounter();
            $('#gdm-select-all-variants').prop('checked', false);
        });
    });

    /**
     * Sortable (arrastrar y soltar)
     */
    $('#gdm-repeater-tbody').sortable({
        handle: '.sort-handle',
        placeholder: 'gdm-sortable-placeholder',
        forcePlaceholderSize: true,
        cursor: 'move',
        axis: 'y',
        opacity: 0.8,
        tolerance: 'pointer',
        start: function(e, ui) {
            ui.placeholder.height(ui.item.height());
        },
        update: function() {
            updateRepeaterIndexes();
        }
    });

    // =========================================================================
    // FILTROS DE CATEGORÍAS Y TAGS
    // =========================================================================

    /**
     * Filtro genérico para listas
     */
    function setupFilter(filterInputId, containerClass) {
        $(filterInputId).on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            const items = $(this).siblings('.gdm-scroll-list').find('.gdm-filterable-item');
            
            if (searchTerm === '') {
                items.show();
                return;
            }
            
            items.each(function() {
                const itemName = $(this).attr('data-name') || '';
                if (itemName.indexOf(searchTerm) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }

    // Inicializar filtros
    setupFilter('#gdm_category_filter', '.gdm-filterable-item');
    setupFilter('#gdm_tag_filter', '.gdm-filterable-item');

    // =========================================================================
    // INSERCIÓN DE SHORTCODES
    // =========================================================================

    /**
     * Inserta texto en el cursor del editor
     */
    function insertAtCursor(text) {
        // Intentar con TinyMCE primero
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('gdm_descripcion');
            if (editor && !editor.isHidden()) {
                editor.execCommand('mceInsertContent', false, text);
                return;
            }
        }
        
        // Fallback: textarea normal
        const textarea = document.getElementById('gdm_descripcion');
        if (textarea) {
            const startPos = textarea.selectionStart;
            const endPos = textarea.selectionEnd;
            const scrollPos = textarea.scrollTop;
            
            textarea.value = textarea.value.substring(0, startPos) + 
                           text + 
                           textarea.value.substring(endPos);
            
            textarea.selectionStart = startPos + text.length;
            textarea.selectionEnd = startPos + text.length;
            textarea.scrollTop = scrollPos;
            textarea.focus();
        }
    }

    /**
     * Insertar shortcodes simples
     */
    $('.gdm-insert-shortcode').on('click', function(e) {
        e.preventDefault();
        const shortcode = $(this).attr('data-shortcode');
        insertAtCursor(shortcode);
    });

    /**
     * Insertar [rule-id] con selector de regla
     */
    $('.gdm-insert-rule-id').on('click', function(e) {
        e.preventDefault();
        
        // Mostrar modal de carga
        const $button = $(this);
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Cargando...');
        
        // Obtener reglas reutilizables vía AJAX
        $.ajax({
            url: gdmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_get_reusable_rules',
                nonce: gdmAdmin.nonce,
                current_post_id: gdmAdmin.currentPostId
            },
            success: function(response) {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-links"></span> [rule-id]');
                
                if (!response.success || !response.data || response.data.length === 0) {
                    alert('No hay reglas reutilizables disponibles.\n\nCrea una regla y marca la opción "Regla Reutilizable" en "Aplicar a".');
                    return;
                }
                
                showRuleSelector(response.data);
            },
            error: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-links"></span> [rule-id]');
                alert('Error al cargar las reglas. Intenta nuevamente.');
            }
        });
    });

    /**
     * Muestra modal para seleccionar regla
     */
    function showRuleSelector(rules) {
        // Crear modal
        const modalHTML = `
            <div id="gdm-rule-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 20px; border-radius: 5px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <h2 style="margin-top: 0;">Seleccionar Regla Reutilizable</h2>
                    <p style="color: #666;">Elige una regla para insertar su shortcode:</p>
                    <div id="gdm-rule-list" style="margin: 20px 0;">
                        ${rules.map(rule => `
                            <label style="display: block; padding: 10px; margin: 5px 0; background: #f5f5f5; border-radius: 3px; cursor: pointer; border: 2px solid transparent;">
                                <input type="radio" name="gdm_selected_rule" value="${rule.id}" style="margin-right: 8px;">
                                <strong>${rule.title}</strong> <code style="color: #888;">(ID: ${rule.id})</code>
                            </label>
                        `).join('')}
                    </div>
                    <div style="text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <button type="button" class="button" id="gdm-cancel-rule">Cancelar</button>
                        <button type="button" class="button button-primary" id="gdm-insert-selected-rule" disabled>Insertar</button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
        
        // Eventos del modal
        $('#gdm-rule-modal-overlay').on('click', function(e) {
            if (e.target.id === 'gdm-rule-modal-overlay') {
                $(this).remove();
            }
        });
        
        $('#gdm-cancel-rule').on('click', function() {
            $('#gdm-rule-modal-overlay').remove();
        });
        
        $('input[name="gdm_selected_rule"]').on('change', function() {
            $('#gdm-insert-selected-rule').prop('disabled', false);
            $(this).closest('label').css('border-color', '#2271b1');
            $(this).closest('label').siblings().css('border-color', 'transparent');
        });
        
        $('#gdm-insert-selected-rule').on('click', function() {
            const selectedId = $('input[name="gdm_selected_rule"]:checked').val();
            if (selectedId) {
                insertAtCursor('[rule-id id="' + selectedId + '"]');
                $('#gdm-rule-modal-overlay').remove();
            }
        });
    }

    // =========================================================================
    // UI GENERAL
    // =========================================================================

    /**
     * Actualiza UI según checkboxes principales
     */
    function updateAdminUI() {
        const todasCategorias = $('#gdm_todas_categorias').is(':checked');
        const cualquierTag = $('#gdm_cualquier_tag').is(':checked');
        
        $('#gdm_category_list_wrapper').toggle(!todasCategorias);
        $('#gdm_tag_list_wrapper').toggle(!cualquierTag);

        $('#gdm-repeater-tbody .gdm-repeater-row').each(function() {
            handleRepeaterRowVisibility($(this));
        });
    }

    $('#gdm_todas_categorias, #gdm_cualquier_tag').on('change', updateAdminUI);

    /**
     * Validación antes de publicar
     */
    $('form#post').on('submit', function(e) {
        const publishButton = $('#publish').val();
        
        if (publishButton && publishButton !== '') {
            const aplicarA = $('input[name="gdm_aplicar_a[]"]:checked').length;
            
            if (aplicarA === 0) {
                const userConfirm = confirm(
                    'No has marcado ninguna opción en "Aplicar a".\n\n' +
                    'Esta regla no se aplicará automáticamente a ningún producto.\n\n' +
                    '¿Continuar de todos modos?'
                );
                
                if (!userConfirm) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });

    // =========================================================================
    // OBSERVADOR DE CAMBIOS
    // =========================================================================

    const observer = new MutationObserver(function() {
        updateVariantCounter();
    });

    const tbody = document.getElementById('gdm-repeater-tbody');
    if (tbody) {
        observer.observe(tbody, {
            childList: true,
            subtree: false
        });
    }

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    updateAdminUI();
    updateVariantCounter();
    
    // Inicializar estados de filas existentes
    $('#gdm-repeater-tbody .gdm-repeater-row').each(function() {
        handleRepeaterRowVisibility($(this));
    });
    
    console.log('✓ GDM Admin Script v4.1.0 cargado correctamente');
});