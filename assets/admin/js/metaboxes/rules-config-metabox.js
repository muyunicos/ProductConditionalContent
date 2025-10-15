/**
 * JavaScript para Metabox Principal de Reglas
 * Sistema modular v6.0 - Control de módulos y ámbito
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // CONTROL DE MÓDULOS
    // =========================================================================

    /**
     * Inicializar toggles de módulos
     */
    function initModuleToggles() {
        // Solo manejar el checkbox, no el clic en toda la tarjeta
        $('.gdm-module-toggle').on('change', function() {
            const moduleId = $(this).data('module');
            const $checkbox = $(this);
            const $label = $checkbox.closest('.gdm-module-checkbox');
            const $metabox = $('#gdm_module_' + moduleId);
            
            if ($checkbox.is(':checked')) {
                $label.addClass('active');
                $metabox.show().addClass('gdm-fade-in');
            } else {
                $label.removeClass('active');
                $metabox.hide().removeClass('gdm-fade-in');
            }
        }).trigger('change');
    }

    // =========================================================================
    // CONTROL DE ÁMBITO
    // =========================================================================

    /**
     * Toggle de "Todas las categorías"
     */
    $('#gdm_todas_categorias').on('change', function() {
        const $wrapper = $('#gdm-categorias-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideUp(200);
            // Desmarcar todas las categorías
            $('.gdm-category-checkbox').prop('checked', false);
        } else {
            $wrapper.slideDown(200);
        }
    });

    /**
     * Toggle de "Cualquier tag"
     */
    $('#gdm_cualquier_tag').on('change', function() {
        const $wrapper = $('#gdm-tags-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideUp(200);
            // Desmarcar todos los tags
            $('.gdm-tag-checkbox').prop('checked', false);
        } else {
            $wrapper.slideDown(200);
        }
    });

    /**
     * Filtro de categorías
     */
    $('#gdm_category_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.gdm-category-list .gdm-checkbox-item').each(function() {
            const categoryName = $(this).text().toLowerCase();
            
            if (categoryName.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    /**
     * Filtro de tags
     */
    $('#gdm_tag_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.gdm-tag-list .gdm-checkbox-item').each(function() {
            const tagName = $(this).text().toLowerCase();
            
            if (tagName.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // =========================================================================
    // VALIDACIÓN DE GUARDADO
    // =========================================================================

    /**
     * Validar antes de guardar
     */
    $('#post').on('submit', function(e) {
        const $modulesChecked = $('.gdm-module-toggle:checked');
        
        if ($modulesChecked.length === 0) {
            e.preventDefault();
            
            alert(gdmReglasMetabox.i18n.selectModule);
            
            // Scroll al metabox de configuración general
            $('html, body').animate({
                scrollTop: $('#gdm_regla_config').offset().top - 50
            }, 500);
            
            return false;
        }
    });

    // =========================================================================
    // UTILIDADES
    // =========================================================================

    /**
     * Copiar texto al portapapeles
     */
    window.copyToClipboard = function(element) {
        const text = element.textContent;
        navigator.clipboard.writeText(text).then(function() {
            // Feedback visual
            const originalText = element.textContent;
            element.textContent = '✓ Copiado';
            element.style.background = '#46b450';
            element.style.color = '#fff';
            
            setTimeout(function() {
                element.textContent = originalText;
                element.style.background = '';
                element.style.color = '';
            }, 1500);
        }).catch(function() {
            // Fallback para navegadores antiguos
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        });
    };

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    initModuleToggles();

    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Metabox Principal: Inicializado');
        console.log('Módulos disponibles:', $('.gdm-module-toggle').length);
        console.log('Módulos activos:', $('.gdm-module-toggle:checked').length);
    }
});