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

        $toggle.off('change.gdm').on('change.gdm', function() {
            const isEnabled = $(this).is(':checked');
            updateMetaboxUI(isEnabled);
            updateStatusMessage();
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
                $programarCheckbox.prop('checked', false);
                $scheduleFields.slideUp(200);
            }
        } else {
            // Habilitar programación
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
        const $programarCheckbox = $('#gdm_programar');
        const $scheduleFields = $('#gdm-schedule-fields');
        
        // IMPORTANTE: Usar namespace para evitar eventos duplicados
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
            
            // Actualizar mensaje de estado
            updateStatusMessage();
        });

        // Toggle de "Fecha Fin"
        $('#gdm_habilitar_fecha_fin').off('change.gdm').on('change.gdm', function() {
            const $wrapper = $('#gdm-fecha-fin-wrapper');
            
            if ($(this).is(':checked')) {
                $wrapper.slideDown(200);
            } else {
                $wrapper.slideUp(200);
            }
            
            // Actualizar mensaje de estado
            updateStatusMessage();
        });

        // Validar y actualizar cuando cambian las fechas
        $('#gdm_fecha_inicio, #gdm_fecha_fin').off('change.gdm blur.gdm').on('change.gdm blur.gdm', function() {
            validateDates();
            updateStatusMessage();
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
        updateStatusMessage();
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
            const $statusDescription = $('.gdm-status-indicator .description');
            const $indicator = $('.gdm-status-indicator');
            
            const isEnabled = $toggle.is(':checked');
            const tieneProgramacion = $programar.is(':checked');
            const fechaInicio = $fechaInicio.val();
            const fechaFin = $fechaFin.val();
            const habilitarFin = $habilitarFin.is(':checked');
            
            let titulo = '';
            let descripcion = '';
            let clase = '';
            
            // CASO: DESHABILITADA
            if (!isEnabled) {
                titulo = 'Deshabilitada';
                descripcion = 'La regla está desactivada';
                clase = 'status-disabled';
            }
            // CASO: HABILITADA
            else {
                // Sin programación = activa
                if (!tieneProgramacion || !fechaInicio) {
                    titulo = 'Habilitada';
                    descripcion = 'Activa actualmente';
                    clase = 'status-active';
                }
                // Con programación
                else {
                    const now = new Date();
                    const inicio = new Date(fechaInicio);
                    
                    // Programada (aún no inicia)
                    if (inicio > now) {
                        const tiempoRestante = calcularTiempoRestante(inicio - now);
                        titulo = 'Habilitada';
                        descripcion = 'Programada, inicia en ' + tiempoRestante;
                        clase = 'status-scheduled';
                        
                        // Si tiene fecha fin, agregar info
                        if (habilitarFin && fechaFin) {
                            const fin = new Date(fechaFin);
                            const tiempoFin = calcularTiempoRestante(fin - now);
                            descripcion += ', termina en ' + tiempoFin;
                        }
                    }
                    // Ya inició
                    else {
                        // Con fecha fin
                        if (habilitarFin && fechaFin) {
                            const fin = new Date(fechaFin);
                            
                            // Ya terminó
                            if (fin < now) {
                                titulo = 'Habilitada';
                                descripcion = 'Inactiva, ya terminó';
                                clase = 'status-expired';
                            }
                            // Activa con fin próximo
                            else {
                                const tiempoFin = calcularTiempoRestante(fin - now);
                                titulo = 'Habilitada';
                                descripcion = 'Activa, termina en ' + tiempoFin;
                                clase = 'status-active';
                            }
                        }
                        // Sin fecha fin
                        else {
                            titulo = 'Habilitada';
                            descripcion = 'Activa actualmente';
                            clase = 'status-active';
                        }
                    }
                }
            }
            
            // Actualizar UI
            $statusDisplay.text(titulo);
            
            // Actualizar o crear descripción
            if ($statusDescription.length) {
                $statusDescription.text(descripcion);
            } else {
                $('.gdm-status-indicator > div').append(
                    '<p class="description" style="margin: 4px 0 0 0;">' + descripcion + '</p>'
                );
            }
            
            // Actualizar clases del indicador
            $indicator
                .removeClass('status-disabled status-active status-scheduled status-expiring status-expired')
                .addClass(clase);
            
        }, 100); // Pequeño delay para evitar múltiples llamadas
    }

    /**
     * Calcular tiempo restante en formato legible
     */
    function calcularTiempoRestante(milisegundos) {
        if (milisegundos < 0) {
            return 'ya pasó';
        }
        
        const segundos = Math.floor(milisegundos / 1000);
        const dias = Math.floor(segundos / 86400);
        const horas = Math.floor((segundos % 86400) / 3600);
        const minutos = Math.floor((segundos % 3600) / 60);
        
        const partes = [];
        
        if (dias > 0) {
            partes.push(dias + (dias === 1 ? ' día' : ' días'));
        }
        
        if (horas > 0 && dias < 7) {
            partes.push(horas + (horas === 1 ? ' hora' : ' horas'));
        }
        
        if (dias === 0 && horas === 0 && minutos > 0) {
            partes.push(minutos + (minutos === 1 ? ' minuto' : ' minutos'));
        }
        
        return partes.join(' y ') || '1 minuto';
    }

    // =========================================================================
    // FECHAS RÁPIDAS (SHORTCUTS)
    // =========================================================================

    /**
     * Inicializar botones de fechas rápidas
     */
    function initQuickDates() {
        // Solo crear si no existen
        if ($('.gdm-quick-dates').length === 0) {
            const $quickDates = $('<div class="gdm-quick-dates" style="margin-top: 8px;"></div>');
            
            const shortcuts = [
                { label: 'En 1 hora', type: 'hours', value: 1 },
                { label: 'Mañana 9:00', type: 'tomorrow' },
                { label: 'Próximo lunes 9:00', type: 'monday' }
            ];
            
            shortcuts.forEach(function(shortcut) {
                const $btn = $('<button>', {
                    type: 'button',
                    class: 'button button-small gdm-quick-date',
                    text: shortcut.label,
                    'data-type': shortcut.type,
                    'data-value': shortcut.value || 0
                });
                
                $quickDates.append($btn);
            });
            
            $('#gdm_fecha_inicio').after($quickDates);
        }

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
        updateStatusMessage();

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
        
        endDate.setHours(23, 59, 0, 0);

        $('#gdm_fecha_fin').val(formatDateForInput(endDate));
        validateDates();
        updateStatusMessage();

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

        $('#post').off('submit.gdm').on('submit.gdm', function(e) {
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

    /**
     * Validar antes de publicar
     */
    function validateBeforePublish() {
        const $toggle = $('#gdm-metabox-toggle');
        const $programar = $('#gdm_programar');
        const $fechaInicio = $('#gdm_fecha_inicio');
        const $fechaFin = $('#gdm_fecha_fin');
        const $habilitarFin = $('#gdm_habilitar_fecha_fin');

        if (!$toggle.is(':checked')) {
            return true;
        }

        if ($programar.is(':checked')) {
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