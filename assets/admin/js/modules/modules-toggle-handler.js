/**
 * Control de Visibilidad de Metaboxes de Módulos
 * Muestra/oculta el contenido según selección en "Aplica a"
 * 
 * @package ProductConditionalContent
 * @since 6.2.2
 * @date 2025-10-15
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Sincronizar estado de módulos al cambiar checkboxes
     */
    function syncModuleStates() {
        $('.gdm-module-toggle').each(function() {
            const moduleId = $(this).data('module');
            const isChecked = $(this).is(':checked');
            const $metabox = $('#gdm_module_' + moduleId);
            
            if (!$metabox.length) return;
            
            const $wrapper = $metabox.find('.gdm-module-wrapper');
            const $inactive = $wrapper.find('.gdm-module-inactive');
            const $content = $wrapper.find('.gdm-module-content');
            
            if (isChecked) {
                // Módulo activado
                $inactive.slideUp(200);
                $content.slideDown(300);
                
                // Expandir metabox automáticamente
                if ($metabox.hasClass('closed')) {
                    $metabox.removeClass('closed');
                }
                
                // Añadir clase visual
                $(this).closest('.gdm-module-checkbox').addClass('active');
            } else {
                // Módulo desactivado
                $content.slideUp(200);
                $inactive.slideDown(300);
                
                // Colapsar metabox automáticamente
                if (!$metabox.hasClass('closed')) {
                    $metabox.addClass('closed');
                }
                
                // Quitar clase visual
                $(this).closest('.gdm-module-checkbox').removeClass('active');
            }
        });
    }

    /**
     * Inicialización
     */
    function init() {
        // Sincronizar estado inicial
        syncModuleStates();
        
        // Escuchar cambios en checkboxes de módulos
        $(document).on('change', '.gdm-module-toggle', function() {
            syncModuleStates();
        });
        
        // Prevenir que se cierre un metabox si su módulo está activo
        $(document).on('click', '.postbox .hndle, .postbox .handlediv', function(e) {
            const $metabox = $(this).closest('.postbox');
            const metaboxId = $metabox.attr('id');
            
            if (!metaboxId || !metaboxId.startsWith('gdm_module_')) {
                return;
            }
            
            const moduleId = metaboxId.replace('gdm_module_', '');
            const $checkbox = $('.gdm-module-toggle[data-module="' + moduleId + '"]');
            
            // Si el módulo está activo y se intenta cerrar, advertir
            if ($checkbox.is(':checked') && !$metabox.hasClass('closed')) {
                const confirmed = confirm(
                    'Este módulo está activo. ¿Deseas ocultarlo de todos modos?\n\n' +
                    '(Para desactivarlo, desmarca su checkbox en "Aplica a")'
                );
                
                if (!confirmed) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }
        });
    }

    // Ejecutar al cargar la página
    init();

    // Debug (solo desarrollo)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Modules Toggle Handler: Inicializado');
    }
});