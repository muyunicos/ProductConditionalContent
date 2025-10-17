/**
 * Rule Config JavaScript v7.0
 * Maneja la interactividad del metabox de configuración de reglas
 * 
 * @package ProductConditionalContent
 * @since 7.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Inicializar funcionalidades
    initCategoryContexts();
    initConfigPreview();
    initTooltips();
    initValidation();
    
    /**
     * Manejar cambios en categorías de contenido
     */
    function initCategoryContexts() {
        $('.gdm-content-category-checkbox').on('change', function() {
            updateContextsDisplay();
        });
        
        // Inicializar vista
        updateContextsDisplay();
    }
    
    /**
     * Actualizar contextos según categorías seleccionadas
     */
    function updateContextsDisplay() {
        const selectedCategories = [];
        
        $('.gdm-content-category-checkbox:checked').each(function() {
            selectedCategories.push($(this).val());
        });
        
        // Ocultar todos los contextos primero
        $('.gdm-category-contexts').hide();
        
        // Mostrar solo los contextos de las categorías seleccionadas
        selectedCategories.forEach(function(category) {
            $(`.gdm-category-contexts[data-category="${category}"]`).show();
        });
        
        // Si no hay categorías seleccionadas, mostrar mensaje
        if (selectedCategories.length === 0) {
            if ($('#gdm-no-categories').length === 0) {
                $('#gdm-contexts-list').append(
                    '<div id="gdm-no-categories" class="gdm-info-message">' +
                    '<p>⚠️ Selecciona al menos una categoría de contenido.</p>' +
                    '</div>'
                );
            }
        } else {
            $('#gdm-no-categories').remove();
        }
        
        // Actualizar vista previa
        updateConfigPreview();
    }
    
    /**
     * Inicializar vista previa de configuración
     */
    function initConfigPreview() {
        // Escuchar cambios en todos los campos relevantes
        $('#gdm_regla_prioridad, .gdm-rule-option').on('change', function() {
            updateConfigPreview();
        });
        
        // Actualizar vista previa inicial
        updateConfigPreview();
    }
    
    /**
     * Actualizar vista previa de configuración
     */
    function updateConfigPreview() {
        const priority = parseInt($('#gdm_regla_prioridad').val()) || 10;
        const isLast = $('input[name="gdm_regla_ultima"]').is(':checked');
        const isForced = $('input[name="gdm_regla_forzada"]').is(':checked');
        const isReusable = $('input[name="gdm_regla_reutilizable"]').is(':checked');
        
        // Actualizar prioridad
        $('#preview-priority').text(priority);
        
        // Determinar nivel de prioridad
        let priorityLevel = '';
        if (priority <= 5) {
            priorityLevel = 'Alta prioridad';
        } else if (priority <= 15) {
            priorityLevel = 'Prioridad media';
        } else {
            priorityLevel = 'Baja prioridad';
        }
        
        $('#preview-priority').next('small').text(`(${priorityLevel})`);
        
        // Actualizar comportamiento
        const behaviorList = $('#preview-behavior');
        behaviorList.empty();
        
        if (isForced) {
            behaviorList.append('<li>⚡ Regla forzada - siempre se ejecuta</li>');
        }
        
        if (isLast) {
            behaviorList.append('<li>🛑 Última regla - bloquea reglas posteriores</li>');
        }
        
        if (isReusable) {
            behaviorList.append('<li>🔄 Reutilizable - disponible como shortcode</li>');
        }
        
        if (!isForced && !isLast && !isReusable) {
            behaviorList.append('<li>📋 Regla estándar</li>');
        }
        
        // Mostrar/ocultar shortcode info
        if (isReusable) {
            $('.gdm-shortcode-info').show();
        } else {
            $('.gdm-shortcode-info').hide();
        }
        
        // Validar combinaciones problemáticas
        validateRuleConfiguration(isLast, isForced, isReusable, priority);
    }
    
    /**
     * Validar configuración de reglas
     */
    function validateRuleConfiguration(isLast, isForced, isReusable, priority) {
        const warningsContainer = $('#gdm-config-warnings');
        
        // Remover warnings anteriores
        warningsContainer.remove();
        
        const warnings = [];
        
        // Validaciones
        if (isLast && isReusable) {
            warnings.push('⚠️ Una regla reutilizable generalmente no debería ser "última regla"');
        }
        
        if (isForced && priority > 50) {
            warnings.push('💡 Las reglas forzadas con baja prioridad pueden ser problemáticas');
        }
        
        if (isLast && priority > 20) {
            warnings.push('💡 Las "últimas reglas" funcionan mejor con alta prioridad');
        }
        
        // Mostrar warnings si los hay
        if (warnings.length > 0) {
            let warningHtml = '<div id="gdm-config-warnings" class="gdm-warnings-box">';
            warnings.forEach(function(warning) {
                warningHtml += `<p>${warning}</p>`;
            });
            warningHtml += '</div>';
            
            $('#gdm-config-preview').after(warningHtml);
        }
    }
    
    /**
     * Inicializar tooltips
     */
    function initTooltips() {
        $('.gdm-tooltip').each(function() {
            const $tooltip = $(this);
            const text = $tooltip.data('tooltip');
            
            $tooltip.hover(
                function() {
                    // Mostrar tooltip
                    if (!$tooltip.data('tooltip-shown')) {
                        const tooltipEl = $('<div class="gdm-tooltip-popup"></div>')
                            .text(text)
                            .appendTo('body');
                        
                        // Posicionar tooltip
                        const offset = $tooltip.offset();
                        tooltipEl.css({
                            position: 'absolute',
                            top: offset.top - tooltipEl.outerHeight() - 10,
                            left: offset.left + ($tooltip.outerWidth() / 2) - (tooltipEl.outerWidth() / 2),
                            backgroundColor: '#333',
                            color: 'white',
                            padding: '8px 12px',
                            borderRadius: '4px',
                            fontSize: '11px',
                            zIndex: 10000,
                            maxWidth: '250px',
                            whiteSpace: 'normal'
                        });
                        
                        $tooltip.data('tooltip-shown', tooltipEl);
                    }
                },
                function() {
                    // Ocultar tooltip
                    const tooltipEl = $tooltip.data('tooltip-shown');
                    if (tooltipEl) {
                        tooltipEl.remove();
                        $tooltip.removeData('tooltip-shown');
                    }
                }
            );
        });
    }
    
    /**
     * Inicializar validación
     */
    function initValidation() {
        // Validación de prioridad
        $('#gdm_regla_prioridad').on('input', function() {
            const value = parseInt($(this).val());
            const $field = $(this);
            
            // Remover estilos de error previos
            $field.removeClass('error');
            $('#priority-error').remove();
            
            if (isNaN(value) || value < 1 || value > 999) {
                $field.addClass('error');
                $field.after('<p id="priority-error" class="error-message">La prioridad debe ser un número entre 1 y 999</p>');
            }
        });
        
        // Validación antes de guardar
        $('form#post').on('submit', function(e) {
            if (!validateBeforeSave()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Validar antes de guardar
     */
    function validateBeforeSave() {
        let isValid = true;
        const errors = [];
        
        // Validar que hay al menos una categoría seleccionada
        const selectedCategories = $('.gdm-content-category-checkbox:checked').length;
        if (selectedCategories === 0) {
            errors.push('Debe seleccionar al menos una categoría de contenido');
            isValid = false;
        }
        
        // Validar que hay al menos un contexto seleccionado
        const selectedContexts = $('input[name="gdm_regla_contextos[]"]:checked').length;
        if (selectedContexts === 0) {
            errors.push('Debe seleccionar al menos un contexto de aplicación');
            isValid = false;
        }
        
        // Validar prioridad
        const priority = parseInt($('#gdm_regla_prioridad').val());
        if (isNaN(priority) || priority < 1 || priority > 999) {
            errors.push('La prioridad debe ser un número entre 1 y 999');
            isValid = false;
        }
        
        // Mostrar errores si los hay
        if (!isValid) {
            $('#gdm-validation-errors').remove();
            
            let errorHtml = '<div id="gdm-validation-errors" class="gdm-error-box">';
            errorHtml += '<h4>❌ Errores de validación:</h4><ul>';
            errors.forEach(function(error) {
                errorHtml += `<li>${error}</li>`;
            });
            errorHtml += '</ul></div>';
            
            $('.gdm-rule-config').prepend(errorHtml);
            
            // Scroll to errors
            $('html, body').animate({
                scrollTop: $('#gdm-validation-errors').offset().top - 100
            }, 500);
        }
        
        return isValid;
    }
    
    /**
     * Auto-guardar configuración (cada 30 segundos si hay cambios)
     */
    let hasChanges = false;
    let autoSaveInterval;
    
    $('.gdm-rule-config input, .gdm-rule-config select').on('change', function() {
        hasChanges = true;
        
        // Limpiar interval anterior
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }
        
        // Configurar nuevo auto-guardado
        autoSaveInterval = setTimeout(function() {
            if (hasChanges && validateBeforeSave()) {
                autoSaveConfig();
            }
        }, 30000); // 30 segundos
    });
    
    /**
     * Auto-guardar configuración via AJAX
     */
    function autoSaveConfig() {
        const formData = $('.gdm-rule-config input, .gdm-rule-config select').serialize();
        
        $.ajax({
            url: gdm_rule_config.ajax_url,
            type: 'POST',
            data: {
                action: 'gdm_auto_save_rule_config',
                nonce: gdm_rule_config.nonce,
                post_id: $('#post_ID').val(),
                form_data: formData
            },
            success: function(response) {
                if (response.success) {
                    hasChanges = false;
                    showNotice('✅ Configuración guardada automáticamente', 'success', 3000);
                }
            }
        });
    }
    
    /**
     * Mostrar notificación temporal
     */
    function showNotice(message, type, timeout) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $(`<div class="notice ${noticeClass} is-dismissible gdm-auto-notice">
            <p>${message}</p>
        </div>`);
        
        $('.gdm-rule-config').prepend($notice);
        
        if (timeout) {
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, timeout);
        }
    }
});

/* CSS adicional para JavaScript */
jQuery(document).ready(function($) {
    const additionalCSS = `
        <style>
        .gdm-warnings-box {
            padding: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .gdm-warnings-box p {
            margin: 5px 0;
            font-size: 12px;
            color: #856404;
        }
        
        .gdm-error-box {
            padding: 10px;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .gdm-error-box h4 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        
        .gdm-error-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .gdm-error-box li {
            color: #721c24;
            font-size: 12px;
        }
        
        .gdm-info-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        input.error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 1px #dc3545 !important;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 11px;
            margin: 3px 0 0 0;
        }
        
        .gdm-tooltip-popup {
            pointer-events: none;
        }
        
        .gdm-auto-notice {
            position: relative;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .gdm-category-contexts {
            border-left: 2px solid #ddd;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .gdm-category-contexts h5 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: 600;
            color: #2271b1;
        }
        </style>
    `;
    
    $('head').append(additionalCSS);
});