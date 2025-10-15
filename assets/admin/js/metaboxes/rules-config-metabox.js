/**
 * JavaScript para Metabox de Reglas v6.2
 * Sistema modular con ámbitos independientes
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 6.2.0
 * @date 2025-10-15
 */

jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // CONTROL DE MÓDULOS
    // =========================================================================

    function initModuleToggles() {
        $('.gdm-module-toggle').on('change', function() {
            const moduleId = $(this).data('module');
            const $checkbox = $(this);
            const $label = $checkbox.closest('.gdm-module-checkbox');
            const $metabox = $('#gdm_module_' + moduleId);
            const $moduleWrapper = $metabox.find('.gdm-module-wrapper');
            
            if ($checkbox.is(':checked')) {
                $label.addClass('active');
                $metabox.show();
                $moduleWrapper.find('.gdm-module-inactive').hide();
                $moduleWrapper.find('.gdm-module-content').show();
                $metabox.addClass('gdm-fade-in');
            } else {
                $label.removeClass('active');
                $moduleWrapper.find('.gdm-module-content').hide();
                $moduleWrapper.find('.gdm-module-inactive').show();
            }
        });
        
        // Estado inicial
        $('.gdm-module-toggle').each(function() {
            const moduleId = $(this).data('module');
            const $metabox = $('#gdm_module_' + moduleId);
            const $moduleWrapper = $metabox.find('.gdm-module-wrapper');
            
            if ($(this).is(':checked')) {
                $moduleWrapper.find('.gdm-module-inactive').hide();
                $moduleWrapper.find('.gdm-module-content').show();
                $metabox.show();
            } else {
                $moduleWrapper.find('.gdm-module-content').hide();
                $moduleWrapper.find('.gdm-module-inactive').show();
                $metabox.show();
            }
        });
    }

    // =========================================================================
    // SISTEMA DE ÁMBITOS (GLOBAL)
    // =========================================================================

    function initScopeSystem() {
        
        // Variables para control de estado
        let originalData = {};
        
        // Toggle checkbox principal
        $(document).on('change', '.gdm-scope-checkbox', function() {
            const $checkbox = $(this);
            const $scopeGroup = $checkbox.closest('.gdm-scope-group');
            const $content = $scopeGroup.find('.gdm-scope-content');
            const $summary = $scopeGroup.find('.gdm-scope-summary');
            const scopeId = $scopeGroup.data('scope');
            
            if ($checkbox.is(':checked')) {
                // Guardar estado original antes de abrir
                saveOriginalState(scopeId);
                
                // Mostrar contenido
                $content.addClass('active').slideDown(300);
                $summary.hide();
            } else {
                // Desmarcar y ocultar
                $content.removeClass('active').slideUp(300);
                $summary.hide();
                $content.find('input[type="checkbox"]').prop('checked', false);
                $content.find('input[type="text"], input[type="number"]').val('');
            }
        });
        
        // Botón "Editar"
        $(document).on('click', '.gdm-scope-edit', function(e) {
            e.preventDefault();
            const $scopeGroup = $(this).closest('.gdm-scope-group');
            const $checkbox = $scopeGroup.find('.gdm-scope-checkbox');
            const $content = $scopeGroup.find('.gdm-scope-content');
            const $summary = $scopeGroup.find('.gdm-scope-summary');
            const scopeId = $scopeGroup.data('scope');
            
            // Guardar estado original
            saveOriginalState(scopeId);
            
            // Asegurar que checkbox esté marcado
            if (!$checkbox.is(':checked')) {
                $checkbox.prop('checked', true);
            }
            
            // Mostrar contenido
            $summary.hide();
            $content.addClass('active').slideDown(300);
        });
        
        // Botón "Guardar"
        $(document).on('click', '.gdm-scope-save', function(e) {
            e.preventDefault();
            const $scopeGroup = $(this).closest('.gdm-scope-group');
            const $content = $scopeGroup.find('.gdm-scope-content');
            const $summary = $scopeGroup.find('.gdm-scope-summary');
            const $checkbox = $scopeGroup.find('.gdm-scope-checkbox');
            const scopeId = $scopeGroup.data('scope');
            
            // Verificar si hay selección
            const hasSelection = $content.find('input[type="checkbox"]:checked').length > 0 ||
                                $content.find('input[type="text"]').filter(function() { return $(this).val(); }).length > 0 ||
                                $content.find('input[type="number"]').filter(function() { return $(this).val(); }).length > 0;
            
            if (!hasSelection) {
                $checkbox.prop('checked', false);
                $content.removeClass('active').slideUp(300);
                $summary.hide();
                return;
            }
            
            // Actualizar resumen (cada ámbito tiene su script específico)
            const event = new CustomEvent('gdm-scope-update-summary', { detail: { scopeId: scopeId } });
            document.dispatchEvent(event);
            
            // Cerrar y mostrar resumen
            $content.removeClass('active').slideUp(300);
            $summary.fadeIn(200);
            
            // Animación de confirmación
            const $button = $(this);
            const originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Guardado').css('background', '#46b450');
            
            setTimeout(function() {
                $button.html(originalHtml).css('background', '');
            }, 1500);
        });
        
        // Botón "Cancelar"
        $(document).on('click', '.gdm-scope-cancel', function(e) {
            e.preventDefault();
            const $scopeGroup = $(this).closest('.gdm-scope-group');
            const $content = $scopeGroup.find('.gdm-scope-content');
            const $summary = $scopeGroup.find('.gdm-scope-summary');
            const scopeId = $scopeGroup.data('scope');
            
            // Restaurar estado original
            restoreOriginalState(scopeId);
            
            // Cerrar
            $content.removeClass('active').slideUp(300);
            
            // Si había resumen antes, mostrarlo
            if ($summary.find('.gdm-summary-text').text().trim()) {
                $summary.fadeIn(200);
            }
        });
        
        /**
         * Guardar estado original de un ámbito
         */
        function saveOriginalState(scopeId) {
            const $scopeGroup = $('[data-scope="' + scopeId + '"]');
            const $content = $scopeGroup.find('.gdm-scope-content');
            
            originalData[scopeId] = {
                checkboxes: [],
                inputs: {}
            };
            
            // Guardar checkboxes
            $content.find('input[type="checkbox"]').each(function() {
                originalData[scopeId].checkboxes.push({
                    name: $(this).attr('name'),
                    value: $(this).val(),
                    checked: $(this).is(':checked')
                });
            });
            
            // Guardar inputs de texto/número
            $content.find('input[type="text"], input[type="number"], select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    originalData[scopeId].inputs[name] = $(this).val();
                }
            });
        }
        
        /**
         * Restaurar estado original de un ámbito
         */
        function restoreOriginalState(scopeId) {
            if (!originalData[scopeId]) return;
            
            const $scopeGroup = $('[data-scope="' + scopeId + '"]');
            const $content = $scopeGroup.find('.gdm-scope-content');
            
            // Restaurar checkboxes
            originalData[scopeId].checkboxes.forEach(function(item) {
                $content.find('input[name="' + item.name + '"][value="' + item.value + '"]')
                    .prop('checked', item.checked);
            });
            
            // Restaurar inputs
            for (let name in originalData[scopeId].inputs) {
                $content.find('[name="' + name + '"]').val(originalData[scopeId].inputs[name]);
            }
        }
    }

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    initModuleToggles();
    initScopeSystem();

    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Metabox v6.2: Inicializado');
        console.log('Módulos disponibles:', $('.gdm-module-toggle').length);
        console.log('Módulos activos:', $('.gdm-module-toggle:checked').length);
        console.log('Ámbitos disponibles:', $('.gdm-scope-group').length);
    }
});