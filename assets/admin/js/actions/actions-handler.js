/**
 * Handler Global para Actions v7.0
 * Gestiona la interacción con los módulos de acción
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 */

(function($) {
    'use strict';

    /**
     * Inicialización
     */
    $(document).ready(function() {
        initActionToggles();
        initActionButtons();
    });

    /**
     * Inicializar toggles de habilitación de acciones
     */
    function initActionToggles() {
        $('.gdm-action-checkbox').on('change', function() {
            const $module = $(this).closest('.gdm-action-module');
            const $content = $module.find('.gdm-action-content');

            if ($(this).is(':checked')) {
                $content.slideDown(300).attr('aria-hidden', 'false');
            } else {
                $content.slideUp(300).attr('aria-hidden', 'true');
            }
        });

        // Inicializar estado al cargar
        $('.gdm-action-checkbox:checked').each(function() {
            const $module = $(this).closest('.gdm-action-module');
            const $content = $module.find('.gdm-action-content');
            $content.show().attr('aria-hidden', 'false');
        });
    }

    /**
     * Inicializar botones de acción
     */
    function initActionButtons() {
        // Botón Guardar
        $('.gdm-action-save').on('click', function(e) {
            e.preventDefault();
            const actionId = $(this).data('action');
            console.log('Guardando acción:', actionId);
            // TODO: Implementar guardado individual via AJAX
            showNotice('Acción guardada correctamente', 'success');
        });

        // Botón Cancelar
        $('.gdm-action-cancel').on('click', function(e) {
            e.preventDefault();
            const actionId = $(this).data('action');
            console.log('Cancelando cambios en acción:', actionId);
            // TODO: Implementar restauración de valores
            showNotice('Cambios cancelados', 'info');
        });

        // Botón Regenerar Código
        $('.gdm-action-regenerate').on('click', function(e) {
            e.preventDefault();
            const actionId = $(this).data('action');
            console.log('Regenerando código para acción:', actionId);
            // TODO: Implementar regeneración via AJAX
            showNotice('Código regenerado correctamente', 'success');
        });
    }

    /**
     * Mostrar notificación
     */
    function showNotice(message, type = 'info') {
        const $notice = $('<div>', {
            'class': `notice notice-${type} is-dismissible`,
            'html': `<p>${message}</p>`
        });

        $('.wrap h1').after($notice);

        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Validar campos requeridos
     */
    function validateRequiredFields($form) {
        let isValid = true;

        $form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        return isValid;
    }

    /**
     * Exponer funciones al scope global si es necesario
     */
    window.GDM_Actions = {
        showNotice: showNotice,
        validateRequiredFields: validateRequiredFields
    };

})(jQuery);
