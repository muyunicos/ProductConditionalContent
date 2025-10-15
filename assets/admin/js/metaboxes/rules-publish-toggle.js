/**
 * Metabox "Publicar" Mejorado para Reglas - OPTIMIZADO
 * Nueva lógica: Habilitada/Deshabilitada con estados dinámicos
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 5.0.4
 */

(function($) {
    'use strict';

    // =========================================================================
    // VARIABLES GLOBALES
    // =========================================================================

    let validationTimeout = null;
    let updateStatusTimeout = null;
    let isTogglingState = false;

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
    initPublishButton();
    
    disableAutosaveInterference();

    console.log('✅ GDM Publish Metabox: Initialized');
});

function disableAutosaveInterference() {
    // Deshabilitar autosave automático de WordPress
    if (typeof autosave !== 'undefined') {
        // Aumentar el intervalo de autosave a 10 minutos
        autosave.autosaveExtras.autosaveIntents = 600; // 10 minutos en segundos
    }
    
    // Prevenir triggers de autosave por cambios en nuestros campos
    $('#gdm-metabox-toggle, #gdm_programar, #gdm_fecha_inicio, #gdm_fecha_fin, #gdm_habilitar_fecha_fin')
        .off('change.autosave input.autosave')
        .on('change.custom input.custom', function(e) {
            e.stopImmediatePropagation();
        });
}

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

        $toggle.off('change.gdm').on('change.gdm', function() {
            if (isTogglingState) return;
            
            isTogglingState = true;
            const isEnabled = $(this).is(':checked');
            
            updateMetaboxUI(isEnabled);
            updateStatusMessage();
            updatePublishButton();
            
            setTimeout(function() {
                isTogglingState = false;
            }, 100);
        });

        // Estado inicial
        updateMetaboxUI($toggle.is(':checked'));
        updatePublishButton();
    }

    /**
     * Actualizar UI según estado del toggle
     */
    function updateMetaboxUI(isEnabled) {
        const $indicator = $('.gdm-status-indicator');
        const $scheduleSection = $('.gdm-schedule-section');
        const $programarCheckbox = $('#gdm_programar');
        const $scheduleFields = $('#gdm-schedule-fields');

        // Actualizar clase del indicador
        $indicator
            .removeClass('status-disabled status-active status-scheduled status-expiring status-expired');

        if (!isEnabled) {
            $indicator.addClass('status-disabled');
            
            // Deshabilitar programación
            $programarCheckbox.prop('disabled', true);
            $scheduleSection.css('opacity', '0.5');
            
            // Ocultar campos de programación si estaban visibles
            if ($programarCheckbox.is(':checked')) {
                $scheduleFields.slideUp(200);
            }
        } else {
            // Habilitar programación
            $programarCheckbox.prop('disabled', false);
            $scheduleSection.css('opacity', '1');
            
            // Si la programación está activada, mostrar campos
            if ($programarCheckbox.is(':checked')) {
                $scheduleFields.slideDown(200);
            }
        }
    }

    // =========================================================================
    // BOTÓN PUBLICAR DINÁMICO
    // =========================================================================

    /**
     * Inicializar comportamiento del botón publicar
     */
    function initPublishButton() {
    updatePublishButton();
    $('#gdm-metabox-toggle').off('change.publish').on('change.publish', function() {
        updatePublishButton();
    });
}

    /**
     * Actualizar texto y comportamiento del botón publicar
     */
    function updatePublishButton() {
        const $toggle = $('#gdm-metabox-toggle');
        const $publishButton = $('#publish');
        const currentStatus = $('#gdm-current-status').val();
        
        if (!$publishButton.length) return;
        
        const isEnabled = $toggle.is(':checked');
        const wasEnabled = (currentStatus === 'habilitada' || currentStatus === 'publish');
        
        let buttonText = '';
        
        // CASO: REGLA HABILITADA
        if (wasEnabled) {
            if (isEnabled) {
                // Toggle activado -> Guardar
                buttonText = gdmPublishMetabox.i18n.guardar;
            } else {
                // Toggle desactivado -> Deshabilitar
                buttonText = gdmPublishMetabox.i18n.deshabilitar;
            }
        }
        // CASO: REGLA DESHABILITADA
        else {
            if (isEnabled) {
                // Toggle activado -> Habilitar
                buttonText = gdmPublishMetabox.i18n.habilitar;
            } else {
                // Toggle desactivado -> Guardar
                buttonText = gdmPublishMetabox.i18n.guardar;
            }
        }
        
        $publishButton.val(buttonText);
    }

    // =========================================================================
    // CAMPOS DE PROGRAMACIÓN
    // =========================================================================

    /**
     * Inicializar campos de programación
     */
    function initScheduleFields() {
    const $programarCheckbox = $('#gdm_programar');
    const $scheduleFields = $('#gdm-schedule-fields');
    
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
        
        updateStatusMessage();
        updateDescriptions();
    });

    // Toggle de "Fecha Fin"
    $('#gdm_habilitar_fecha_fin').off('change.gdm').on('change.gdm', function() {
        const $wrapper = $('#gdm-fecha-fin-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
        
        updateStatusMessage();
        updateDescriptions();
    });

    $('#gdm_fecha_inicio, #gdm_fecha_fin').off('change.gdm blur.gdm input.gdm')
        .on('blur.gdm focusout.gdm', function() {
            validateDates();
            updateStatusMessage();
            updateDescriptions();
        });
}

    /**
     * Establecer fecha de inicio por defecto (mañana 00:00)
     */
    function setDefaultStartDate() {
        const $input = $('#gdm_fecha_inicio');
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);
        
        const formatted = formatDateForInput(tomorrow);
        $input.val(formatted);
        
        validateDates();
        updateStatusMessage();
        updateDescriptions();
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
    // ACTUALIZAR DESCRIPCIONES DINÁMICAS
    // =========================================================================

    /**
     * Actualizar todas las descripciones
     */
    function updateDescriptions() {
        updateInicioDescription();
        updateFinDescription();
    }

    /**
     * Actualizar descripción de fecha de inicio
     */
    function updateInicioDescription() {
        const $fechaInicio = $('#gdm_fecha_inicio');
        const $description = $('.gdm-inicio-description');
        
        if (!$fechaInicio.val()) {
            $description.text('La regla se activará automáticamente');
            return;
        }
        
        const now = new Date();
        const inicio = new Date($fechaInicio.val());
        const diff = inicio - now;
        
        if (diff <= 0) {
            $description.text('La regla se activará inmediatamente');
            return;
        }
        
        const tiempo = calcularTiempoLegible(diff / 1000);
        const fechaFormatted = formatDateForDisplay(inicio);
        
        $description.text(`La regla se activará automáticamente en ${tiempo}, el día ${fechaFormatted}`);
    }

    /**
     * Actualizar descripción de fecha de fin
     */
    function updateFinDescription() {
        const $fechaFin = $('#gdm_fecha_fin');
        const $description = $('.gdm-fin-description');
        
        if (!$fechaFin.val()) {
            $description.text('La regla se desactivará automáticamente');
            return;
        }
        
        const now = new Date();
        const fin = new Date($fechaFin.val());
        const diff = fin - now;
        
        if (diff <= 0) {
            $description.text('La regla se desactivará inmediatamente');
            return;
        }
        
        const tiempo = calcularTiempoLegible(diff / 1000);
        const fechaFormatted = formatDateForDisplay(fin);
        
        $description.text(`La regla se desactivará automáticamente en ${tiempo}, el día ${fechaFormatted}`);
    }

    /**
     * Formatear fecha para mostrar
     */
    function formatDateForDisplay(date) {
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
                       'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        
        const dia = date.getDate();
        const mes = meses[date.getMonth()];
        const anio = date.getFullYear();
        
        return `${dia} de ${mes} de ${anio}`;
    }

    /**
     * Calcular tiempo legible (minutos, horas, días, semanas, meses)
     */
    function calcularTiempoLegible(segundos) {
        const minutos = Math.round(segundos / 60);
        const horas = Math.round(segundos / 3600);
        const dias = Math.round(segundos / 86400);
        const semanas = Math.round(segundos / 604800);
        const meses = Math.round(segundos / 2592000);
        
        if (minutos < 60) {
            return minutos === 1 ? '1 minuto' : `${minutos} minutos`;
        } else if (horas < 24) {
            return horas === 1 ? '1 hora' : `${horas} horas`;
        } else if (dias < 7) {
            return dias === 1 ? '1 día' : `${dias} días`;
        } else if (semanas < 4) {
            return semanas === 1 ? '1 semana' : `${semanas} semanas`;
        } else {
            return meses === 1 ? '1 mes' : `${meses} meses`;
        }
    }

    // =========================================================================
    // ACTUALIZAR MENSAJE DE ESTADO DINÁMICAMENTE
    // =========================================================================

    /**
     * Actualizar mensaje de estado según la nueva lógica
     */
    function updateStatusMessage() {
        clearTimeout(updateStatusTimeout);
        
        updateStatusTimeout = setTimeout(function() {
            const $toggle = $('#gdm-metabox-toggle');
            const $programar = $('#gdm_programar');
            const $fechaInicio = $('#gdm_fecha_inicio');
            const $fechaFin = $('#gdm_fecha_fin');
            const $habilitarFin = $('#gdm_habilitar_fecha_fin');
            const $statusDisplay = $('.gdm-status-display');
            const $statusDescription = $('.gdm-status-description');
            const $indicator = $('.gdm-status-indicator');
            const currentStatus = $('#gdm-current-status').val();
            
            const isEnabled = $toggle.is(':checked');
            const wasEnabled = (currentStatus === 'habilitada' || currentStatus === 'publish');
            const tieneProgramacion = $programar.is(':checked');
            const fechaInicio = $fechaInicio.val();
            const fechaFin = $fechaFin.val();
            const habilitarFin = $habilitarFin.is(':checked');
            
            let titulo = '';
            let descripcion = '';
            let clase = '';
            
            // CASO: REGLA HABILITADA
            if (wasEnabled) {
                if (isEnabled) {
                    // Toggle activado -> Habilitada con estado dinámico
                    titulo = 'Habilitada';
                    const estadoDinamico = calcularEstadoDinamico(tieneProgramacion, fechaInicio, fechaFin, habilitarFin);
                    descripcion = estadoDinamico.texto;
                    clase = estadoDinamico.clase;
                } else {
                    // Toggle desactivado -> Se deshabilitará
                    titulo = 'Deshabilitar';
                    descripcion = 'La regla se desactivará';
                    clase = 'status-disabled';
                }
            }
            // CASO: REGLA DESHABILITADA
            else {
                if (isEnabled) {
                    // Toggle activado -> Se habilitará con estado dinámico
                    titulo = 'Habilitar';
                    const estadoDinamico = calcularEstadoDinamico(tieneProgramacion, fechaInicio, fechaFin, habilitarFin);
                    descripcion = estadoDinamico.texto;
                    clase = estadoDinamico.clase;
                } else {
                    // Toggle desactivado -> Deshabilitada
                    titulo = 'Deshabilitada';
                    descripcion = 'La regla está desactivada';
                    clase = 'status-disabled';
                }
            }
            
            // Actualizar UI
            $statusDisplay.text(titulo);
            $statusDescription.text(descripcion);
            
            // Actualizar clases del indicador
            $indicator
                .removeClass('status-disabled status-active status-scheduled status-expiring status-expired')
                .addClass(clase);
            
        }, 100);
    }

    /**
     * Calcular estado dinámico
     */
    function calcularEstadoDinamico(tieneProgramacion, fechaInicio, fechaFin, habilitarFin) {
        // Sin programación = activa
        if (!tieneProgramacion || !fechaInicio) {
            return {
                clase: 'status-active',
                texto: 'Activa'
            };
        }
        
        const now = new Date();
        const inicio = new Date(fechaInicio);
        
        // Programada (aún no inicia)
        if (inicio > now) {
            const tiempoRestante = calcularTiempoLegible((inicio - now) / 1000);
            let texto = `Programada, inicia en ${tiempoRestante}`;
            
            // Si tiene fecha fin, agregar info
            if (habilitarFin && fechaFin) {
                const fin = new Date(fechaFin);
                const tiempoFin = calcularTiempoLegible((fin - now) / 1000);
                texto += `, termina en ${tiempoFin}`;
            }
            
            return {
                clase: 'status-scheduled',
                texto: texto
            };
        }
        
        // Ya inició
        
        // Con fecha fin
        if (habilitarFin && fechaFin) {
            const fin = new Date(fechaFin);
            
            // Ya terminó
            if (fin < now) {
                return {
                    clase: 'status-expired',
                    texto: 'Inactiva, ya terminó'
                };
            }
            
            // Activa con fin próximo
            const tiempoFin = calcularTiempoLegible((fin - now) / 1000);
            
            return {
                clase: 'status-active',
                texto: `Activa, termina en ${tiempoFin}`
            };
        }
        
        // Activa sin fin
        return {
            clase: 'status-active',
            texto: 'Activa'
        };
    }

    // =========================================================================
    // FECHAS RÁPIDAS (SHORTCUTS)
    // =========================================================================

    /**
     * Inicializar botones de fechas rápidas
     */
    function initQuickDates() {
        // Bind eventos (solo una vez)
        $(document).off('click.gdm', '.gdm-quick-date').on('click.gdm', '.gdm-quick-date', function(e) {
            e.preventDefault();
            handleQuickDate($(this));
        });

        $(document).off('click.gdm', '.gdm-quick-duration').on('click.gdm', '.gdm-quick-duration', function(e) {
            e.preventDefault();
            handleQuickDuration($(this));
        });
    }

    /**
     * Manejar clic en fecha rápida
     */
    function handleQuickDate($btn) {
        const type = $btn.data('type');
        const now = new Date();
        let targetDate;

        switch (type) {
            case 'tomorrow':
                targetDate = new Date(now);
                targetDate.setDate(targetDate.getDate() + 1);
                targetDate.setHours(0, 0, 0, 0);
                break;

            case 'monday':
                targetDate = new Date(now);
                const daysUntilMonday = (8 - targetDate.getDay()) % 7 || 7;
                targetDate.setDate(targetDate.getDate() + daysUntilMonday);
                targetDate.setHours(0, 0, 0, 0);
                break;

            case 'month':
                targetDate = new Date(now);
                targetDate.setMonth(targetDate.getMonth() + 1);
                targetDate.setDate(1);
                targetDate.setHours(0, 0, 0, 0);
                break;

            default:
                return;
        }

        $('#gdm_fecha_inicio').val(formatDateForInput(targetDate));
        validateDates();
        updateStatusMessage();
        updateDescriptions();

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
        const hours = parseInt($btn.data('hours')) || 0;
        const days = parseInt($btn.data('days')) || 0;
        const $fechaInicio = $('#gdm_fecha_inicio');
        
        if (!$fechaInicio.val()) {
            alert('Primero establece una fecha de inicio');
            return;
        }

        const startDate = new Date($fechaInicio.val());
        const endDate = new Date(startDate);
        
        if (hours > 0) {
            endDate.setHours(endDate.getHours() + hours);
        } else if (days > 0) {
            endDate.setDate(endDate.getDate() + days);
        }

        $('#gdm_fecha_fin').val(formatDateForInput(endDate));
        validateDates();
        updateStatusMessage();
        updateDescriptions();

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
    if ($('#gdm-date-validation').length === 0) {
        const $validation = $('<div>', {
            id: 'gdm-date-validation',
            class: 'gdm-date-validation',
            style: 'display:none; margin-top: 12px; padding: 10px; border-radius: 4px;'
        });
        
        $('#gdm-schedule-fields').append($validation);
    }

    // ✅ MODIFICACIÓN: Usar namespace específico y lógica más precisa
    $('#post').off('submit.gdmValidation').on('submit.gdmValidation', function(e) {
        const $toggle = $('#gdm-metabox-toggle');
        const $programar = $('#gdm_programar');
        
        // ✅ SOLO interferir si es necesario validar fechas de programación
        if ($toggle.is(':checked') && $programar.is(':checked')) {
            if (!validateBeforePublish()) {
                e.preventDefault();
                e.stopImmediatePropagation(); // ✅ NUEVO: Detener propagación inmediata
                return false;
            }
        }
        
        // ✅ Para cualquier otro caso, permitir submit normal
        return true;
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

            if (!$fechaInicio.val()) {
                $validation.hide();
                return;
            }

            const now = new Date();
            const inicio = new Date($fechaInicio.val());
            const errors = [];
            const warnings = [];

            if (inicio < now) {
                warnings.push('⚠️ La fecha de inicio ya pasó. Se activará inmediatamente.');
            }

            const diasHastaInicio = Math.ceil((inicio - now) / (1000 * 60 * 60 * 24));
            if (diasHastaInicio > 90) {
                warnings.push(`⚠️ La regla se activará en ${diasHastaInicio} días.`);
            }

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

                setTimeout(function() {
                    $validation.slideUp(200);
                }, 2000);
            }
        }, 500);
    }
function validateBeforePublish() {
    const $toggle = $('#gdm-metabox-toggle');
    const $programar = $('#gdm_programar');
    const $fechaInicio = $('#gdm_fecha_inicio');
    const $fechaFin = $('#gdm_fecha_fin');
    const $habilitarFin = $('#gdm_habilitar_fecha_fin');

    if (!$toggle.is(':checked')) {
        return true; // Regla deshabilitada, no validar
    }

    if (!$programar.is(':checked')) {
        return true; // Sin programación, no validar fechas
    }

    if (!$fechaInicio.val()) {
        alert('Por favor establece una fecha de inicio para la programación.');
        $fechaInicio.focus();
        return false;
    }

    const inicio = new Date($fechaInicio.val());

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

    return true;
}

    // =========================================================================
    // ESTILOS INLINE
    // =========================================================================

    /**
     * Añadir estilos CSS inline
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

if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    // Interceptar eventos que puedan triggerar autosave
    $(document).on('input change keyup', 'input, select, textarea', function(e) {
        if ($(this).closest('.gdm-regla-config-wrapper, #gdm-publish-metabox').length > 0) {
            console.warn('🚨 Campo GDM triggeró evento:', e.type, 'en:', this.name || this.id, 'valor:', this.value);
        }
    });
}