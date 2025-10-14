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
    /**
 * Inicializar campos de programación
 */
function initScheduleFields() {
    const $programarCheckbox = $('#gdm_programar');
    const $scheduleFields = $('#gdm-schedule-fields');
    
    // IMPORTANTE: Usar .off() para evitar eventos duplicados
    $programarCheckbox.off('change.gdm').on('change.gdm', function() {
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            $scheduleFields.slideDown(200);
            
            // Si activa programación, setear fecha de inicio por defecto si está vacía
            const $fechaInicio = $('#gdm_fecha_inicio');
            if (!$fechaInicio.val()) {
                setDefaultStartDate();
            }
        } else {
            $scheduleFields.slideUp(200);
        }
    });

    // Toggle de "Fecha Fin"
    $('#gdm_habilitar_fecha_fin').off('change.gdm').on('change.gdm', function() {
        const $wrapper = $('#gdm-fecha-fin-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    // Validar fechas en tiempo real
    $('#gdm_fecha_inicio, #gdm_fecha_fin').off('change.gdm').on('change.gdm', function() {
        validateDates();
    });
}
/**
 * Actualizar mensaje de estado dinámicamente
 */
function updateStatusMessage() {
    const $toggle = $('#gdm-metabox-toggle');
    const $programar = $('#gdm_programar');
    const $fechaInicio = $('#gdm_fecha_inicio');
    const $fechaFin = $('#gdm_fecha_fin');
    const $habilitarFin = $('#gdm_habilitar_fecha_fin');
    const $statusDisplay = $('.gdm-status-display');
    const $statusDescription = $('.gdm-status-indicator .description');
    
    const isEnabled = $toggle.is(':checked');
    const tieneProgramacion = $programar.is(':checked');
    const fechaInicio = $fechaInicio.val();
    const fechaFin = $fechaFin.val();
    const habilitarFin = $habilitarFin.is(':checked');
    
    let titulo = '';
    let descripcion = '';
    
    // DESHABILITADA
    if (!isEnabled) {
        titulo = 'Deshabilitada';
        descripcion = 'La regla no se activará';
    }
    // HABILITADA sin programación
    else if (!tieneProgramacion || !fechaInicio) {
        titulo = 'Habilitada';
        descripcion = 'Se activa inmediatamente';
    }
    // HABILITADA con programación
    else {
        const now = new Date();
        const inicio = new Date(fechaInicio);
        
        // Programada (futuro)
        if (inicio > now) {
            const diff = inicio - now;
            const dias = Math.floor(diff / (1000 * 60 * 60 * 24));
            const horas = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            let tiempoTexto = '';
            if (dias > 0) {
                tiempoTexto = dias + (dias === 1 ? ' día' : ' días');
                if (horas > 0) {
                    tiempoTexto += ' y ' + horas + (horas === 1 ? ' hora' : ' horas');
                }
            } else if (horas > 0) {
                tiempoTexto = horas + (horas === 1 ? ' hora' : ' horas');
            } else {
                const minutos = Math.max(1, Math.floor(diff / (1000 * 60)));
                tiempoTexto = minutos + (minutos === 1 ? ' minuto' : ' minutos');
            }
            
            titulo = 'Habilitada';
            descripcion = 'Se activa en ' + tiempoTexto;
        }
        // Ya comenzó
        else {
            // Con fecha fin
            if (habilitarFin && fechaFin) {
                const fin = new Date(fechaFin);
                
                // Ya terminó
                if (fin < now) {
                    titulo = 'Habilitada';
                    descripcion = 'Terminada (fecha fin alcanzada)';
                }
                // Aún activa
                else {
                    const diff = fin - now;
                    const horasRestantes = Math.floor(diff / (1000 * 60 * 60));
                    
                    titulo = 'Habilitada';
                    if (horasRestantes < 24) {
                        descripcion = 'Activa (termina en ' + Math.max(1, horasRestantes) + ' horas)';
                    } else {
                        const diasRestantes = Math.floor(diff / (1000 * 60 * 60 * 24));
                        descripcion = 'Activa (termina en ' + diasRestantes + ' días)';
                    }
                }
            }
            // Sin fecha fin
            else {
                titulo = 'Habilitada';
                descripcion = 'Activa actualmente';
            }
        }
    }
    
    // Actualizar UI
    $statusDisplay.text(titulo);
    
    if ($statusDescription.length) {
        $statusDescription.text(descripcion);
    } else {
        // Crear el elemento si no existe
        $('.gdm-status-indicator > div').append(
            '<p class="description" style="margin: 4px 0 0 0;">' + descripcion + '</p>'
        );
    }
}

// Agregar al documento ready y eventos
$(document).ready(function() {
    if (!$('body').hasClass('post-type-gdm_regla')) {
        return;
    }

    // ... código existente ...
    
    // Actualizar mensajes cuando cambian los campos
    $('#gdm-metabox-toggle, #gdm_programar, #gdm_fecha_inicio, #gdm_fecha_fin, #gdm_habilitar_fecha_fin')
        .on('change', updateStatusMessage);
});
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