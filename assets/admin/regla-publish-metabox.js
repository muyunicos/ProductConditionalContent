/**
 * Modificación del Metabox "Publicar" para gdm_regla
 * Compatible con WordPress 6.8.3
 * 
 * Funcionalidades:
 * - Ocultar sección de "Visibilidad"
 * - Reemplazar selector de estados
 * - Cambiar "Publicar" por "Fecha de Inicio"
 * - Agregar "Fecha de Fin" con checkbox
 * 
 * @package ProductConditionalContent
 * @since 5.0.2
 */

jQuery(document).ready(function($) {
    
    // =========================================================================
    // 1. OCULTAR SECCIÓN DE VISIBILIDAD
    // =========================================================================
    
    $('#visibility').hide();
    
    // =========================================================================
    // 2. REEMPLAZAR SELECTOR DE ESTADOS
    // =========================================================================
    
    function replaceStatusSelector() {
        const $statusSelect = $('#post_status');
        
        if ($statusSelect.length) {
            // Limpiar opciones existentes
            $statusSelect.empty();
            
            // Agregar estados personalizados
            const statuses = gdmPublishMetabox.statusLabels;
            const currentStatus = $('#gdm_current_status').val() || 'habilitada';
            
            $.each(statuses, function(value, label) {
                $statusSelect.append(
                    $('<option></option>')
                        .val(value)
                        .text(label)
                        .prop('selected', value === currentStatus)
                );
            });
            
            // Actualizar el display de estado
            updateStatusDisplay();
        }
    }
    
    function updateStatusDisplay() {
        const selectedStatus = $('#post_status').val();
        const statusLabel = gdmPublishMetabox.statusLabels[selectedStatus] || selectedStatus;
        $('#post-status-display').text(statusLabel);
        $('#hidden_post_status').val(selectedStatus);
    }
    
    // Ejecutar al cargar
    replaceStatusSelector();
    
    // Actualizar cuando cambie el estado
    $(document).on('change', '#post_status', updateStatusDisplay);
    
    // =========================================================================
    // 3. CAMBIAR "PUBLICAR" POR "FECHA DE INICIO"
    // =========================================================================
    
    function updateTimestampLabel() {
        const $timestampSection = $('.curtime.misc-pub-curtime');
        
        if ($timestampSection.length) {
            // Cambiar el label "Publicar" por "Activar el"
            const $timestamp = $timestampSection.find('#timestamp');
            const currentText = $timestamp.html();
            
            // Reemplazar "Publicar" con "Activar el"
            const newText = currentText.replace(/Publicar/, gdmPublishMetabox.i18n.publishOn);
            $timestamp.html(newText);
            
            // Cambiar el título del fieldset
            $('#timestampdiv legend').text(gdmPublishMetabox.i18n.startDate);
        }
    }
    
    updateTimestampLabel();
    
    // =========================================================================
    // 4. AGREGAR SECCIÓN DE FECHA DE FIN
    // =========================================================================
    
    function addEndDateSection() {
        const $fechaFinSection = $('#gdm-fecha-fin-section');
        const $miscActions = $('#misc-publishing-actions');
        
        if ($fechaFinSection.length && $miscActions.length) {
            // Mover la sección después de la fecha de inicio
            $fechaFinSection.show().appendTo($miscActions);
        }
    }
    
    addEndDateSection();
    
    // =========================================================================
    // 5. FUNCIONALIDAD DE FECHA DE FIN
    // =========================================================================
    
    // Toggle del checkbox de habilitar fecha fin
    $('#gdm_habilitar_fecha_fin').on('change', function() {
        $('#gdm_fecha_fin_wrapper').toggle(this.checked);
    });
    
    // Editar fecha de fin
    $('.edit-gdm-fecha-fin').on('click', function(e) {
        e.preventDefault();
        
        if ($('#gdm-fecha-fin-div').is(':hidden')) {
            $('#gdm-fecha-fin-div').slideDown('fast');
            $(this).hide();
        }
    });
    
    // Guardar fecha de fin
    $('.save-gdm-fecha-fin').on('click', function(e) {
        e.preventDefault();
        updateEndDateDisplay();
        $('#gdm-fecha-fin-div').slideUp('fast');
        $('.edit-gdm-fecha-fin').show();
    });
    
    // Cancelar edición de fecha de fin
    $('.cancel-gdm-fecha-fin').on('click', function(e) {
        e.preventDefault();
        $('#gdm-fecha-fin-div').slideUp('fast');
        $('.edit-gdm-fecha-fin').show();
    });
    
    function updateEndDateDisplay() {
        const jj = $('#gdm_jj_fin').val();
        const mm = $('#gdm_mm_fin').val();
        const aa = $('#gdm_aa_fin').val();
        const hh = $('#gdm_hh_fin').val();
        const mn = $('#gdm_mn_fin').val();
        
        if (jj && mm && aa && hh && mn) {
            // Formatear la fecha (simplificado, WordPress tiene sus propios formatos)
            const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                              'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            const monthName = monthNames[parseInt(mm) - 1];
            
            const displayText = '<b>' + jj + ' de ' + monthName + ' de ' + aa + ' a las ' + hh + ':' + mn + '</b>';
            $('#gdm-fecha-fin-display').html(displayText);
        }
    }
    
    // =========================================================================
    // 6. SINCRONIZAR CON EL BOTÓN PUBLICAR
    // =========================================================================
    
    // Cambiar el texto del botón "Publicar" según el estado
    function updatePublishButton() {
        const status = $('#post_status').val();
        const $publishButton = $('#publish');
        
        const buttonTexts = {
            'habilitada': 'Activar Regla',
            'deshabilitada': 'Guardar Deshabilitada',
            'programada': 'Programar Regla',
            'revisar': 'Marcar para Revisar',
            'terminada': 'Marcar como Terminada'
        };
        
        if (buttonTexts[status]) {
            $publishButton.val(buttonTexts[status]);
        }
    }
    
    // Actualizar al cambiar el estado
    $(document).on('change', '#post_status', updatePublishButton);
    
    // Actualizar al cargar
    updatePublishButton();
    
    // =========================================================================
    // 7. VALIDACIÓN ANTES DE PUBLICAR
    // =========================================================================
    
    $('#post').on('submit', function(e) {
        const status = $('#post_status').val();
        
        // Si el estado es "programada", verificar que haya fecha de inicio
        if (status === 'programada') {
            const hasStartDate = $('#gdm_has_fecha_inicio').val() === '1';
            const isEditingDate = $('#timestampdiv').is(':visible');
            
            if (!hasStartDate && !isEditingDate) {
                alert('Por favor establece una fecha de inicio para programar la regla.');
                $('.edit-timestamp').trigger('click');
                e.preventDefault();
                return false;
            }
        }
        
        // Si habilitar fecha fin está marcado, verificar que haya fecha
        if ($('#gdm_habilitar_fecha_fin').is(':checked')) {
            const jj = $('#gdm_jj_fin').val();
            const mm = $('#gdm_mm_fin').val();
            const aa = $('#gdm_aa_fin').val();
            
            if (!jj || !mm || !aa) {
                alert('Por favor completa la fecha de finalización o desmarca la opción.');
                $('.edit-gdm-fecha-fin').trigger('click');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
    
    // =========================================================================
    // 8. HELPERS
    // =========================================================================
    
    // Sincronizar cambios cuando se usa el editor de timestamp nativo
    $('.save-timestamp').on('click', function() {
        $('#gdm_has_fecha_inicio').val('1');
    });
    
    console.log('✅ GDM Regla Publish Metabox: Initialized');
});