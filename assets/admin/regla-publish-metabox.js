/**
 * Metabox "Publicar" Mejorado para Reglas
 * Integración con toggle y programación
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 5.0.3
 */

(function($) {
    'use strict';

    // =========================================================================
    // VARIABLES GLOBALES
    // =========================================================================

    let validationTimeout = null;

    // =========================================================================
    // INIT
    // =========================================================================

    $(document).ready(function() {
        if (!$('body').hasClass('post-type-gdm_regla')) {
            return;
        }

        initMetaboxToggle();
        initScheduleFields();
        initQuickDates();
        initValidation();
        updatePublishButton();

        console.log('✅ GDM Publish Metabox: Initialized');
    });

    // =========================================================================
    // TOGGLE DEL METABOX
    // =========================================================================

    /**
     * Inicializar toggle en metabox
     */
    function initMetaboxToggle() {
        const $toggle = $('#gdm-metabox-toggle');
        
        if (!$toggle.length) {
            return;
        }

        // Sincronizar con el selector de estado nativo de WordPress
        $toggle.on('change', function() {
            const isEnabled = $(this).is(':checked');
            updateMetaboxUI(isEnabled);
            updatePublishButton();
        });

        // Estado inicial
        updateMetaboxUI($toggle.is(':checked'));
    }

    /**
     * Actualizar UI según estado del toggle
     */
    function updateMetaboxUI(isEnabled) {
        const $indicator = $('.gdm-status-indicator');
        const $scheduleSection = $('.gdm-schedule-section');
        const $statusDisplay = $('.gdm-status-display');

        // Actualizar clases del indicador
        $indicator
            .removeClass('status-disabled status-active status-scheduled status-expiring status-expired')
            .addClass(isEnabled ? 'status-active' : 'status-disabled');

        // Actualizar texto
        if ($statusDisplay.length) {
            const text = isEnabled 
                ? (gdmPublishMetabox.i18n.enabled || 'Habilitada')
                : (gdmPublishMetabox.i18n.disabled || 'Deshabilitada');
            
            $statusDisplay.text(text);
        }

        // Habilitar/deshabilitar programación
        const $programarCheckbox = $('#gdm_programar');
        const $scheduleFields = $('#gdm-schedule-fields');

        if (!isEnabled) {
            // Si está deshabilitada, ocultar y deshabilitar programación
            $programarCheckbox.prop('checked', false).prop('disabled', true);
            $scheduleFields.hide();
            $scheduleSection.css('opacity', '0.5');
        } else {
            // Si está habilitada, habilitar programación
            $programarCheckbox.prop('disabled', false);
            $scheduleSection.css('opacity', '1');
        }
    }

    // =========================================================================
    // CAMPOS DE PROGRAMACIÓN
    // =========================================================================

    /**
     * Inicializar campos de programación
     */
    function initScheduleFields() {
        // Toggle de "Programar"
        $('#gdm_programar').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('#gdm-schedule-fields').slideToggle(200);
            
            if (isChecked) {
                // Si activa programación, setear fecha de inicio por defecto
                const $fechaInicio = $('#gdm_fecha_inicio');
                if (!$fechaInicio.val()) {
                    setDefaultStartDate();
                }
            }
        });

        // Toggle de "Fecha Fin"
        $('#gdm_habilitar_fecha_fin').on('change', function() {
            $('#gdm-fecha-fin-wrapper').slideToggle(200);
        });

        // Validar fechas en tiempo real
        $('#gdm_fecha_inicio, #gdm_fecha_fin').on('change', function() {
            validateDates();
        });
    }

    /**
     * Establecer fecha de inicio por defecto
     */
    function setDefaultStartDate() {
        const $input = $('#gdm_fecha_inicio');
        const now = new Date();
        
        // Redondear a la siguiente hora
        now.setHours(now.getHours() + 1);
        now.setMinutes(0);
        now.setSeconds(0);
        
        const formatted = formatDateForInput(now);
        $input.val(formatted);
        
        validateDates();
    }

    /**
     * Formatear fecha para input datetime-local
     */
    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // =========================================================================
    // FECHAS RÁPIDAS (SHORTCUTS)
    // =========================================================================

    /**
     * Inicializar botones de fechas rápidas
     */
    function initQuickDates() {
        // Crear botones si no existen
        if ($('.gdm-quick-dates').length === 0) {
            const $quickDates = $('<div class="gdm-quick-dates" style="margin-top: 8px;"></div>');
            
            const shortcuts = [
                { label: 'En 1 hora', hours: 1 },
                { label: 'Mañana 9:00', type: 'tomorrow' },
                { label: 'Próximo lunes 9:00', type: 'monday' }
            ];
            
            shortcuts.forEach(function(shortcut) {
                const $btn = $('<button>', {
                    type: 'button',
                    class: 'button button-small gdm-quick-date',
                    text: shortcut.label,
                    'data-type': shortcut.type || 'hours',
                    'data-value': shortcut.hours || 0
                });
                
                $quickDates.append($btn);
            });
            
            $('#gdm_fecha_inicio').after($quickDates);
        }

        // Crear botones de duración para fecha fin
        if ($('.gdm-quick-durations').length === 0) {
            const $quickDurations = $('<div class="gdm-quick-durations" style="margin-top: 8px;"></div>');
            
            const durations = [
                { label: '7 días', days: 7 },
                { label: '30 días', days: 30 },
                { label: '90 días', days: 90 }
            ];
            
            durations.forEach(function(duration) {
                const $btn = $('<button>', {
                    type: 'button',
                    class: 'button button-small gdm-quick-duration',
                    text: duration.label,
                    'data-days': duration.days
                });
                
                $quickDurations.append($btn);
            });
            
            $('#gdm_fecha_fin').after($quickDurations);
        }

        // Bind eventos
        $(document).on('click', '.gdm-quick-date', function(e) {
            e.preventDefault();
            handleQuickDate($(this));
        });

        $(document).on('click', '.gdm-quick-duration', function(e) {
            e.preventDefault();
            handleQuickDuration($(this));
        });
    }

    /**
     * Manejar clic en fecha rápida
     */
    function handleQuickDate($btn) {
        const type = $btn.data('type');
        const value = $btn.data('value');
        const now = new Date();
        let targetDate;

        switch (type) {
            case 'hours':
                targetDate = new Date(now.getTime() + (value * 60 * 60 * 1000));
                targetDate.setMinutes(0);
                targetDate.setSeconds(0);
                break;

            case 'tomorrow':
                targetDate = new Date(now);
                targetDate.setDate(targetDate.getDate() + 1);
                targetDate.setHours(9, 0, 0, 0);
                break;

            case 'monday':
                targetDate = new Date(now);
                const daysUntilMonday = (8 - targetDate.getDay()) % 7 || 7;
                targetDate.setDate(targetDate.getDate() + daysUntilMonday);
                targetDate.setHours(9, 0, 0, 0);
                break;

            default:
                return;
        }

        $('#gdm_fecha_inicio').val(formatDateForInput(targetDate));
        validateDates();

        // Feedback visual
        $btn.addClass('button-primary');
        setTimeout(function() {
            $btn.removeClass('button-primary');
        }, 200);
    }

    /**
     * Manejar clic en duración rápida
     */
    function handleQuickDuration($btn) {
        const days = parseInt($btn.data('days'));
        const $fechaInicio = $('#gdm_fecha_inicio');
        
        if (!$fechaInicio.val()) {
            alert('Primero establece una fecha de inicio');
            return;
        }

        const startDate = new Date($fechaInicio.val());
        const endDate = new Date(startDate.getTime() + (days * 24 * 60 * 60 * 1000));
        
        // Setear a las 23:59 del último día
        endDate.setHours(23, 59, 0, 0);

        $('#gdm_fecha_fin').val(formatDateForInput(endDate));
        validateDates();

        // Feedback visual
        $btn.addClass('button-primary');
        setTimeout(function() {
            $btn.removeClass('button-primary');
        }, 200);
    }

    // =========================================================================
    // VALIDACIÓN
    // =========================================================================

    /**
     * Inicializar validación
     */
    function initValidation() {
        // Crear contenedor de validación si no existe
        if ($('#gdm-date-validation').length === 0) {
            const $validation = $('<div>', {
                id: 'gdm-date-validation',
                class: 'gdm-date-validation',
                style: 'display:none; margin-top: 12px; padding: 10px; border-radius: 4px;'
            });
            
            $('#gdm-schedule-fields').append($validation);
        }

        // Validar antes de publicar
        $('#post').on('submit', function(e) {
            if (!validateBeforePublish()) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Validar fechas
     */
    function validateDates() {
        clearTimeout(validationTimeout);
        
        validationTimeout = setTimeout(function() {
            const $validation = $('#gdm-date-validation');
            const $fechaInicio = $('#gdm_fecha_inicio');
            const $fechaFin = $('#gdm_fecha_fin');
            const $habilitarFin = $('#gdm_habilitar_fecha_fin');

            // Si no hay fecha de inicio, no validar
            if (!$fechaInicio.val()) {
                $validation.hide();
                return;
            }

            const now = new Date();
            const inicio = new Date($fechaInicio.val());
            const errors = [];
            const warnings = [];

            // Validar fecha de inicio
            if (inicio < now) {
                warnings.push('⚠️ La fecha de inicio ya pasó. Se activará inmediatamente.');
            }

            // Validar si está muy lejos en el futuro
            const diasHastaInicio = Math.ceil((inicio - now) / (1000 * 60 * 60 * 24));
            if (diasHastaInicio > 90) {
                warnings.push(`⚠️ La regla se activará en ${diasHastaInicio} días.`);
            }

            // Validar fecha de fin
            if ($habilitarFin.is(':checked') && $fechaFin.val()) {
                const fin = new Date($fechaFin.val());

                if (fin <= inicio) {
                    errors.push('❌ La fecha de fin debe ser posterior a la de inicio.');
                }

                const duracion = Math.ceil((fin - inicio) / (1000 * 60 * 60 * 24));
                if (duracion > 365) {
                    warnings.push(`⚠️ La duración es de ${duracion} días (más de 1 año).`);
                }
            }

            // Mostrar resultados
            if (errors.length > 0) {
                $validation
                    .removeClass('success warning')
                    .addClass('error')
                    .html(errors.join('<br>'))
                    .slideDown(200);
            } else if (warnings.length > 0) {
                $validation
                    .removeClass('success error')
                    .addClass('warning')
                    .html(warnings.join('<br>'))
                    .slideDown(200);
            } else {
                $validation
                    .removeClass('error warning')
                    .addClass('success')
                    .html('✓ Las fechas son válidas')
                    .slideDown(200);

                // Auto-ocultar después de 2 segundos
                setTimeout(function() {
                    $validation.slideUp(200);
                }, 2000);
            }
        }, 500);
    }

    /**
     * Validar antes de publicar
     */
    function validateBeforePublish() {
        const $toggle = $('#gdm-metabox-toggle');
        const $programar = $('#gdm_programar');
        const $fechaInicio = $('#gdm_fecha_inicio');
        const $fechaFin = $('#gdm_fecha_fin');
        const $habilitarFin = $('#gdm_habilitar_fecha_fin');

        // Si está deshabilitada, permitir guardar
        if (!$toggle.is(':checked')) {
            return true;
        }

        // Si tiene programación, validar fechas
        if ($programar.is(':checked')) {
            if (!$fechaInicio.val()) {
                alert('Por favor establece una fecha de inicio para la programación.');
                $fechaInicio.focus();
                return false;
            }

            const inicio = new Date($fechaInicio.val());

            // Validar fecha de fin si está habilitada
            if ($habilitarFin.is(':checked')) {
                if (!$fechaFin.val()) {
                    alert('Por favor establece una fecha de fin o desmarca la opción.');
                    $fechaFin.focus();
                    return false;
                }

                const fin = new Date($fechaFin.val());

                if (fin <= inicio) {
                    alert('La fecha de fin debe ser posterior a la fecha de inicio.');
                    $fechaFin.focus();
                    return false;
                }
            }
        }

        return true;
    }

    // =========================================================================
    // BOTÓN PUBLICAR
    // =========================================================================

    /**
     * Actualizar texto del botón Publicar
     */
    function updatePublishButton() {
        const $publishBtn = $('#publish');
        const $toggle = $('#gdm-metabox-toggle');
        const $programar = $('#gdm_programar');

        if (!$publishBtn.length) {
            return;
        }

        let buttonText = 'Guardar';

        if ($toggle.is(':checked')) {
            if ($programar.is(':checked')) {
                buttonText = 'Programar Regla';
            } else {
                buttonText = 'Activar Regla';
            }
        } else {
            buttonText = 'Guardar Deshabilitada';
        }

        $publishBtn.val(buttonText);
    }

    // Actualizar botón cuando cambian los toggles
    $(document).on('change', '#gdm-metabox-toggle, #gdm_programar', updatePublishButton);

    // =========================================================================
    // OCULTAR CAMPOS NATIVOS DE WORDPRESS
    // =========================================================================

    /**
     * Ocultar el selector de estados nativo
     */
    function hideNativeStatusSelector() {
        // Ocultar toda la sección de estado
        $('.misc-pub-section.misc-pub-post-status').hide();
        
        // Mantener sincronizado el estado oculto con nuestro toggle
        $('#gdm-metabox-toggle').on('change', function() {
            const newStatus = $(this).is(':checked') ? 'habilitada' : 'deshabilitada';
            $('#post_status').val(newStatus);
            $('#hidden_post_status').val(newStatus);
        });
    }

    // Ejecutar al cargar
    $(document).ready(function() {
        if ($('body').hasClass('post-type-gdm_regla')) {
            hideNativeStatusSelector();
        }
    });

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Añadir estilos CSS inline para validación
     */
    function addValidationStyles() {
        if ($('#gdm-validation-styles').length > 0) {
            return;
        }

        const styles = `
            <style id="gdm-validation-styles">
                .gdm-date-validation {
                    font-size: 13px;
                    line-height: 1.5;
                }
                .gdm-date-validation.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .gdm-date-validation.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .gdm-date-validation.warning {
                    background: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                }
                .gdm-quick-dates button,
                .gdm-quick-durations button {
                    margin-right: 4px;
                    margin-bottom: 4px;
                }
            </style>
        `;

        $('head').append(styles);
    }

    addValidationStyles();

})(jQuery);