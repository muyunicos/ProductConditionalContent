/**
 * Quick Edit para Reglas con Toggle
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 5.0.3
 */

(function($) {
    'use strict';

    // =========================================================================
    // QUICK EDIT ENHANCEMENT
    // =========================================================================

    // Guardar la función original de WordPress
    const wp_inline_edit = inlineEditPost.edit;

    // Sobreescribir la función
    inlineEditPost.edit = function(id) {
        // Llamar a la función original
        wp_inline_edit.apply(this, arguments);

        // Obtener el ID del post
        let post_id = 0;
        if (typeof(id) === 'object') {
            post_id = parseInt(this.getId(id));
        }

        if (post_id > 0) {
            populateQuickEditData(post_id);
        }
    };

    /**
     * Popular Quick Edit con datos de la regla
     */
    function populateQuickEditData(post_id) {
        // Obtener la fila del post
        const $row = $('#post-' + post_id);
        const $toggle = $row.find('.gdm-toggle-switch');
        
        if (!$toggle.length) {
            return;
        }

        // Obtener estado del toggle
        const currentStatus = $toggle.data('current-status');
        const isEnabled = (currentStatus === 'habilitada');

        // Actualizar checkbox en Quick Edit
        const $quickEditRow = $('.inline-edit-row');
        const $quickToggle = $quickEditRow.find('input[name="gdm_quick_toggle"]');
        
        if ($quickToggle.length) {
            $quickToggle.prop('checked', isEnabled);
        }

        // Si quieres cargar más datos vía AJAX, puedes hacerlo aquí
        loadAdditionalData(post_id, $quickEditRow);
    }

    /**
     * Cargar datos adicionales vía AJAX
     */
    function loadAdditionalData(post_id, $quickEditRow) {
        if (!gdmQuickEdit || !gdmQuickEdit.ajaxUrl) {
            return;
        }

        $.ajax({
            url: gdmQuickEdit.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_get_regla_data',
                nonce: gdmQuickEdit.nonce,
                post_id: post_id
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Aquí puedes popular más campos si los agregas
                    // Por ejemplo, checkbox de programación
                    const $programar = $quickEditRow.find('input[name="gdm_quick_programar"]');
                    if ($programar.length && response.data.programar) {
                        $programar.prop('checked', response.data.programar === '1');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Quick Edit AJAX Error:', error);
            }
        });
    }

    // =========================================================================
    // OCULTAR CAMPOS NO DESEADOS
    // =========================================================================

    /**
     * Ocultar opciones de visibilidad en Quick Edit
     */
    function hideUnwantedFields() {
        // Ocultar después de un pequeño delay para asegurar que el DOM esté listo
        setTimeout(function() {
            $('.inline-edit-row .inline-edit-password-input').hide();
            $('.inline-edit-row .inline-edit-private').hide();
            
            // También ocultar en el selector de estado si existe
            const $statusSelect = $('.inline-edit-row select[name="post_status"]');
            if ($statusSelect.length) {
                $statusSelect.find('option[value="private"]').remove();
                $statusSelect.find('option[value="pending"]').remove();
            }
        }, 100);
    }

    // Ejecutar cuando se abre Quick Edit
    $(document).on('click', '.editinline', function() {
        hideUnwantedFields();
    });

    // =========================================================================
    // BULK EDIT
    // =========================================================================

    /**
     * Manejar Bulk Edit
     */
    function handleBulkEdit() {
        const $bulkEditRow = $('.inline-edit-row.bulk-edit-row');
        
        if (!$bulkEditRow.length) {
            return;
        }

        // Agregar toggle para bulk edit si no existe
        if ($bulkEditRow.find('input[name="gdm_bulk_toggle"]').length === 0) {
            const $statusField = $bulkEditRow.find('select[name="gdm_bulk_status"]');
            
            if ($statusField.length) {
                $statusField.on('change', function() {
                    const value = $(this).val();
                    
                    if (value === '-1') {
                        // Sin cambios
                        return;
                    }
                    
                    // Aquí puedes agregar lógica adicional para bulk edit
                    console.log('Bulk status change:', value);
                });
            }
        }
    }

    // Ejecutar cuando se abre Bulk Edit
    $(document).on('click', '.editinline', function() {
        setTimeout(handleBulkEdit, 150);
    });

    // =========================================================================
    // ESTILOS ADICIONALES
    // =========================================================================

    /**
     * Agregar estilos CSS para Quick Edit
     */
    function addQuickEditStyles() {
        if ($('#gdm-quick-edit-styles').length > 0) {
            return;
        }

        const styles = `
            <style id="gdm-quick-edit-styles">
                /* Ocultar campos no deseados */
                .post-type-gdm_regla .inline-edit-password-input,
                .post-type-gdm_regla .inline-edit-private {
                    display: none !important;
                }
                
                /* Mejorar aspecto del checkbox de Quick Edit */
                .inline-edit-row input[name="gdm_quick_toggle"] {
                    margin-right: 6px;
                }
                
                .inline-edit-row .checkbox-title {
                    font-weight: 500;
                }
                
                /* Highlight cuando se actualiza */
                .gdm-row-updated {
                    animation: gdm-highlight 2s ease-out;
                }
                
                @keyframes gdm-highlight {
                    0% { background-color: #d4edda; }
                    100% { background-color: transparent; }
                }
            </style>
        `;

        $('head').append(styles);
    }

    // =========================================================================
    // INIT
    // =========================================================================

    $(document).ready(function() {
        if ($('body').hasClass('post-type-gdm_regla')) {
            addQuickEditStyles();
            console.log('✅ GDM Quick Edit: Initialized');
        }
    });

})(jQuery);