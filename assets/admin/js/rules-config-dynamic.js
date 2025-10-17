/**
 * Rules Config Dynamic JavaScript v7.0
 * Maneja la funcionalidad dinámica del metabox reorganizado
 * 
 * FUNCIONALIDADES:
 * ✅ Filtrado dinámico de conditions por categoría
 * ✅ Filtrado dinámico de actions por categoría  
 * ✅ Toggle de módulos conditions/actions
 * ✅ Validación en tiempo real
 * ✅ Tooltips informativos
 * 
 * @package ProductConditionalContent
 * @since 7.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Inicializar funcionalidades
    initCategoryFiltering();
    initModuleToggles();
    initTooltips();
    initValidation();
    
    /**
     * Filtrado dinámico por categorías de contenido
     */
    function initCategoryFiltering() {
        $('.gdm-content-category-checkbox').on('change', function() {
            filterModulesByCategories();
        });
        
        // Inicializar filtrado al cargar
        filterModulesByCategories();
    }
    
    /**
     * Filtrar modules (conditions y actions) según categorías seleccionadas
     */
    function filterModulesByCategories() {
        const selectedCategories = [];
        
        $('.gdm-content-category-checkbox:checked').each(function() {
            selectedCategories.push($(this).val());
        });
        
        console.log('Categorías seleccionadas:', selectedCategories);
        
        // Filtrar CONDITIONS
        $('.gdm-condition-module').each(function() {
            const $module = $(this);
            const conditionId = $module.data('condition');
            const moduleCategories = $module.data('categories').toString().split(',');
            
            let shouldShow = false;
            
            // Verificar si alguna categoría seleccionada coincide
            selectedCategories.forEach(function(category) {
                if (moduleCategories.includes(category)) {
                    shouldShow = true;
                }
            });
            
            if (shouldShow) {
                $module.show();
                console.log(`Mostrar condition: ${conditionId}`);
            } else {
                $module.hide();
                // Desmarcar si se oculta
                $module.find('.gdm-condition-toggle').prop('checked', false);
                $module.find('.gdm-module-content').hide();
                console.log(`Ocultar condition: ${conditionId}`);
            }
        });
        
        // Filtrar ACTIONS
        $('.gdm-action-module').each(function() {
            const $module = $(this);
            const actionId = $module.data('action');
            const moduleCategories = $module.data('categories').toString().split(',');
            
            let shouldShow = false;
            
            // Verificar si alguna categoría seleccionada coincide
            selectedCategories.forEach(function(category) {
                if (moduleCategories.includes(category)) {
                    shouldShow = true;
                }
            });
            
            if (shouldShow) {
                $module.show();
                console.log(`Mostrar action: ${actionId}`);
            } else {
                $module.hide();
                // Desmarcar si se oculta
                $module.find('.gdm-action-toggle').prop('checked', false);
                $module.find('.gdm-module-content').hide();
                console.log(`Ocultar action: ${actionId}`);
            }
        });
        
        // Mostrar mensaje si no hay categorías seleccionadas
        if (selectedCategories.length === 0) {
            showCategoryWarning();
        } else {
            hideCategoryWarning();
        }
        
        // Actualizar contadores
        updateModuleCounts();
    }
    
    /**
     * Inicializar toggles de módulos
     */
    function initModuleToggles() {
        // Toggle para CONDITIONS
        $('.gdm-condition-toggle').on('change', function() {
            const $module = $(this).closest('.gdm-condition-module');
            const $content = $module.find('.gdm-module-content');
            
            if ($(this).is(':checked')) {
                $content.slideDown(300);
                $module.addClass('active');
            } else {
                $content.slideUp(300);
                $module.removeClass('active');
            }
        });
        
        // Toggle para ACTIONS
        $('.gdm-action-toggle').on('change', function() {
            const $module = $(this).closest('.gdm-action-module');
            const $content = $module.find('.gdm-module-content');
            
            if ($(this).is(':checked')) {
                $content.slideDown(300);
                $module.addClass('active');
            } else {
                $content.slideUp(300);
                $module.removeClass('active');
            }
        });
        
        // Click en header para toggle
        $('.gdm-module-header').on('click', function(e) {
            // Solo si no se clickeó el checkbox
            if (!$(e.target).is('input[type="checkbox"]')) {
                const $checkbox = $(this).find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.is(':checked')).trigger('change');
            }
        });
    }
    
    /**
     * Mostrar warning de categorías
     */
    function showCategoryWarning() {
        if ($('#gdm-category-warning').length === 0) {
            const warning = `
                <div id="gdm-category-warning" class="gdm-warning-box">
                    <h4>⚠️ Selecciona Categorías de Contenido</h4>
                    <p>Debes seleccionar al menos una categoría de contenido para ver las condiciones y acciones disponibles.</p>
                </div>
            `;
            
            $('#gdm-conditions-container').prepend(warning);
            $('#gdm-actions-container').prepend(warning.replace('gdm-category-warning', 'gdm-category-warning-actions'));
        }
    }
    
    /**
     * Ocultar warning de categorías
     */
    function hideCategoryWarning() {
        $('#gdm-category-warning, #gdm-category-warning-actions').remove();
    }
    
    /**
     * Actualizar contadores de módulos
     */
    function updateModuleCounts() {
        const visibleConditions = $('.gdm-condition-module:visible').length;
        const activeConditions = $('.gdm-condition-module:visible .gdm-condition-toggle:checked').length;
        
        const visibleActions = $('.gdm-action-module:visible').length;
        const activeActions = $('.gdm-action-module:visible .gdm-action-toggle:checked').length;
        
        // Actualizar títulos de metaboxes
        updateMetaboxTitle('gdm-conditions-config', 'Ámbitos de Aplicación (Condiciones)', activeConditions, visibleConditions);
        updateMetaboxTitle('gdm-actions-config', 'Aplica a (Acciones)', activeActions, visibleActions);
    }
    
    /**
     * Actualizar título de metabox con contadores
     */
    function updateMetaboxTitle(metaboxId, baseTitle, active, total) {
        const $metabox = $('#' + metaboxId);
        const $title = $metabox.find('.hndle span');
        
        if ($title.length) {
            let title = baseTitle;
            if (total > 0) {
                title += ` (${active}/${total})`;
            }
            $title.text(title);
        }
    }
    
    /**
     * Inicializar tooltips
     */
    function initTooltips() {
        $('.gdm-tooltip').each(function() {
            const $tooltip = $(this);
            const text = $tooltip.data('tooltip');
            
            $tooltip.hover(
                function() {
                    if (!$tooltip.data('tooltip-shown')) {
                        const tooltipEl = $('<div class="gdm-tooltip-popup"></div>')
                            .text(text)
                            .appendTo('body');
                        
                        const offset = $tooltip.offset();
                        tooltipEl.css({
                            position: 'absolute',
                            top: offset.top - tooltipEl.outerHeight() - 10,
                            left: offset.left + ($tooltip.outerWidth() / 2) - (tooltipEl.outerWidth() / 2),
                            backgroundColor: '#333',
                            color: 'white',
                            padding: '8px 12px',
                            borderRadius: '4px',
                            fontSize: '11px',
                            zIndex: 10000,
                            maxWidth: '250px',
                            whiteSpace: 'normal',
                            pointerEvents: 'none'
                        });
                        
                        $tooltip.data('tooltip-shown', tooltipEl);
                    }
                },
                function() {
                    const tooltipEl = $tooltip.data('tooltip-shown');
                    if (tooltipEl) {
                        tooltipEl.remove();
                        $tooltip.removeData('tooltip-shown');
                    }
                }
            );
        });
    }
    
    /**
     * Validación en tiempo real
     */
    function initValidation() {
        // Validación de prioridad
        $('#gdm_regla_prioridad').on('input', function() {
            const value = parseInt($(this).val());
            const $field = $(this);
            
            $field.removeClass('error');
            $('#priority-error').remove();
            
            if (isNaN(value) || value < 1 || value > 999) {
                $field.addClass('error');
                $field.after('<p id="priority-error" class="error-message">La prioridad debe ser un número entre 1 y 999</p>');
            }
        });
        
        // Validación de categorías
        $('.gdm-content-category-checkbox').on('change', function() {
            const selectedCount = $('.gdm-content-category-checkbox:checked').length;
            
            $('.gdm-content-categories').removeClass('error');
            $('#categories-error').remove();
            
            if (selectedCount === 0) {
                $('.gdm-content-categories').addClass('error');
                $('.gdm-content-categories').after('<p id="categories-error" class="error-message">Debe seleccionar al menos una categoría de contenido</p>');
            }
        });
        
        // Validación antes de guardar
        $('form#post').on('submit', function(e) {
            if (!validateBeforeSave()) {
                e.preventDefault();
                showNotice('Por favor corrige los errores antes de guardar', 'error');
                return false;
            }
        });
    }
    
    /**
     * Validar antes de guardar
     */
    function validateBeforeSave() {
        let isValid = true;
        const errors = [];
        
        // Validar categorías
        const selectedCategories = $('.gdm-content-category-checkbox:checked').length;
        if (selectedCategories === 0) {
            errors.push('Debe seleccionar al menos una categoría de contenido');
            isValid = false;
        }
        
        // Validar prioridad
        const priority = parseInt($('#gdm_regla_prioridad').val());
        if (isNaN(priority) || priority < 1 || priority > 999) {
            errors.push('La prioridad debe ser un número entre 1 y 999');
            isValid = false;
        }
        
        // Mostrar errores
        if (!isValid) {
            console.log('Errores de validación:', errors);
        }
        
        return isValid;
    }
    
    /**
     * Mostrar notificación
     */
    function showNotice(message, type = 'info') {
        const noticeClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 'notice-info';
        
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible gdm-notice">
                <p>${message}</p>
            </div>
        `);
        
        $('.wrap .page-title-action').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Funciones de debugging (solo en desarrollo)
     */
    if (window.console && window.console.log) {
        // Debug button (solo si WP_DEBUG está activo)
        if (window.location.search.indexOf('debug=1') !== -1) {
            $('<button type="button" id="gdm-debug-btn">🔍 Debug Modules</button>')
                .css({
                    position: 'fixed',
                    top: '50px',
                    right: '20px',
                    zIndex: 9999,
                    padding: '5px 10px',
                    background: '#0073aa',
                    color: 'white',
                    border: 'none',
                    borderRadius: '3px',
                    cursor: 'pointer'
                })
                .on('click', function() {
                    debugModules();
                })
                .appendTo('body');
        }
    }
    
    /**
     * Debug de módulos
     */
    function debugModules() {
        console.group('🔍 GDM Modules Debug');
        
        const selectedCategories = [];
        $('.gdm-content-category-checkbox:checked').each(function() {
            selectedCategories.push($(this).val());
        });
        console.log('Categorías seleccionadas:', selectedCategories);
        
        console.group('📋 Conditions');
        $('.gdm-condition-module').each(function() {
            const $module = $(this);
            const id = $module.data('condition');
            const categories = $module.data('categories');
            const visible = $module.is(':visible');
            const active = $module.find('.gdm-condition-toggle').is(':checked');
            
            console.log(`${visible ? '👁️' : '🙈'} ${active ? '✅' : '❌'} ${id}`, {
                categories: categories,
                visible: visible,
                active: active
            });
        });
        console.groupEnd();
        
        console.group('🎯 Actions');
        $('.gdm-action-module').each(function() {
            const $module = $(this);
            const id = $module.data('action');
            const categories = $module.data('categories');
            const visible = $module.is(':visible');
            const active = $module.find('.gdm-action-toggle').is(':checked');
            
            console.log(`${visible ? '👁️' : '🙈'} ${active ? '✅' : '❌'} ${id}`, {
                categories: categories,
                visible: visible,
                active: active
            });
        });
        console.groupEnd();
        
        console.groupEnd();
    }
    
    // Auto-actualizar contadores al cargar
    setTimeout(function() {
        updateModuleCounts();
    }, 500);
});

/* CSS adicional para JavaScript */
jQuery(document).ready(function($) {
    const additionalCSS = `
        <style>
        .gdm-warning-box {
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        
        .gdm-warning-box h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        .gdm-warning-box p {
            margin: 0;
            color: #856404;
        }
        
        .gdm-module-header {
            transition: background-color 0.2s ease;
        }
        
        .gdm-module-header:hover {
            background: #f0f0f1;
        }
        
        .gdm-condition-module.active,
        .gdm-action-module.active {
            border-color: #2271b1;
            box-shadow: 0 2px 4px rgba(34, 113, 177, 0.1);
        }
        
        .gdm-condition-module.active .gdm-module-header,
        .gdm-action-module.active .gdm-module-header {
            background: #e6f3ff;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 11px;
            margin: 3px 0 0 0;
            font-style: italic;
        }
        
        input.error,
        .gdm-content-categories.error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 1px #dc3545 !important;
        }
        
        .gdm-notice {
            margin: 15px 0;
        }
        
        .gdm-tooltip-popup {
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .gdm-module-content {
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 782px) {
            .gdm-content-categories {
                flex-direction: column;
                gap: 5px;
            }
            
            .gdm-category-item {
                width: 100%;
                justify-content: flex-start;
            }
        }
        </style>
    `;
    
    $('head').append(additionalCSS);
});