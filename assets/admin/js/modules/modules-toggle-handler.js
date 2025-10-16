/**
 * Control de Visibilidad de Módulos v7.0
 * Muestra/oculta el contenido según selección en "Aplica a"
 * Compatible con nueva estructura de acciones dentro de un solo metabox
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
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

            // Buscar el módulo de acción correspondiente
            const $actionModule = $('.gdm-action-module[data-action="' + moduleId + '"]');

            if (!$actionModule.length) {
                console.warn('⚠️ No se encontró módulo de acción para:', moduleId);
                return;
            }

            const $actionContent = $actionModule.find('.gdm-action-content');

            if (isChecked) {
                // Módulo activado: mostrar formulario
                $actionModule.slideDown(300);
                $actionContent.slideDown(300).attr('aria-hidden', 'false');

                // Añadir clase visual al checkbox
                $(this).closest('.gdm-module-checkbox').addClass('active');

                if (window.GDM_DEBUG) {
                    console.log('✅ Módulo activado:', moduleId);
                }
            } else {
                // Módulo desactivado: ocultar formulario
                $actionContent.slideUp(200).attr('aria-hidden', 'true');
                $actionModule.slideUp(200);

                // Quitar clase visual del checkbox
                $(this).closest('.gdm-module-checkbox').removeClass('active');

                if (window.GDM_DEBUG) {
                    console.log('❌ Módulo desactivado:', moduleId);
                }
            }
        });
    }

    /**
     * Habilitar el checkbox de acción dentro del módulo cuando se selecciona arriba
     */
    function syncActionCheckboxes() {
        $('.gdm-module-toggle').each(function() {
            const moduleId = $(this).data('module');
            const isChecked = $(this).is(':checked');

            // Sincronizar checkbox de habilitación dentro del módulo
            const $actionCheckbox = $('.gdm-action-checkbox[data-action="' + moduleId + '"]');
            if ($actionCheckbox.length) {
                $actionCheckbox.prop('checked', isChecked);
            }
        });
    }

    /**
     * Inicialización
     */
    function init() {
        // Ocultar todos los módulos por defecto
        $('.gdm-action-module').hide();

        // Sincronizar estado inicial
        syncModuleStates();
        syncActionCheckboxes();

        // Escuchar cambios en checkboxes de "Aplica a"
        $(document).on('change', '.gdm-module-toggle', function() {
            syncModuleStates();
            syncActionCheckboxes();
        });

        // Escuchar cambios en checkboxes dentro de los módulos
        $(document).on('change', '.gdm-action-checkbox', function() {
            const actionId = $(this).data('action');
            const isChecked = $(this).is(':checked');
            const $content = $(this).closest('.gdm-action-module').find('.gdm-action-content');

            if (isChecked) {
                $content.slideDown(300).attr('aria-hidden', 'false');
            } else {
                $content.slideUp(200).attr('aria-hidden', 'true');
            }
        });

        // Inicializar estado de los checkboxes de acción
        $('.gdm-action-checkbox:checked').each(function() {
            const $content = $(this).closest('.gdm-action-module').find('.gdm-action-content');
            $content.show().attr('aria-hidden', 'false');
        });
    }

    // Ejecutar al cargar la página
    init();

    // Exponer debug globalmente
    window.GDM_DEBUG = window.location.hostname === 'localhost' ||
                       window.location.hostname === '127.0.0.1' ||
                       window.location.hostname.includes('backup.');

    if (window.GDM_DEBUG) {
        console.log('✅ GDM Modules Toggle Handler v7.0: Inicializado');
        console.log('📦 Módulos encontrados:', $('.gdm-action-module').length);
        console.log('☑️ Checkboxes encontrados:', $('.gdm-module-toggle').length);
    }
});