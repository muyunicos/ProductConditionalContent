/**
 * JavaScript para Metabox Principal de Reglas v6.1 CORREGIDO
 * Sistema modular con ámbitos mejorados y UX optimizada
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // CONTROL DE MÓDULOS
    // =========================================================================

    /**
     * Inicializar toggles de módulos
     * ✅ CORREGIDO: Ocultar/mostrar metaboxes dinámicamente
     */
    function initModuleToggles() {
        $('.gdm-module-toggle').on('change', function() {
            const moduleId = $(this).data('module');
            const $checkbox = $(this);
            const $label = $checkbox.closest('.gdm-module-checkbox');
            const $metabox = $('#gdm_module_' + moduleId);
            const $moduleWrapper = $metabox.find('.gdm-module-wrapper');
            
            if ($checkbox.is(':checked')) {
                // Activar módulo
                $label.addClass('active');
                
                // Mostrar el metabox completo
                $metabox.show();
                
                // Ocultar mensaje de inactivo y mostrar contenido
                $moduleWrapper.find('.gdm-module-inactive').hide();
                $moduleWrapper.find('.gdm-module-content').show();
                
                // Efecto de fade in
                $metabox.addClass('gdm-fade-in');
            } else {
                // Desactivar módulo
                $label.removeClass('active');
                
                // Mostrar mensaje de inactivo y ocultar contenido
                $moduleWrapper.find('.gdm-module-content').hide();
                $moduleWrapper.find('.gdm-module-inactive').show();
            }
        });
        
        // ✅ Aplicar estado inicial al cargar la página
        $('.gdm-module-toggle').each(function() {
            const moduleId = $(this).data('module');
            const $metabox = $('#gdm_module_' + moduleId);
            const $moduleWrapper = $metabox.find('.gdm-module-wrapper');
            
            if ($(this).is(':checked')) {
                // Módulo activo: ocultar mensaje inactivo
                $moduleWrapper.find('.gdm-module-inactive').hide();
                $moduleWrapper.find('.gdm-module-content').show();
                $metabox.show();
            } else {
                // Módulo inactivo: mostrar mensaje
                $moduleWrapper.find('.gdm-module-content').hide();
                $moduleWrapper.find('.gdm-module-inactive').show();
                $metabox.show(); // ✅ MOSTRAR el metabox con el mensaje
            }
        });
    }

    // =========================================================================
    // ÁMBITO: SISTEMA MEJORADO
    // =========================================================================

    /**
     * Inicializar sistema de ámbitos mejorado
     * ✅ CORREGIDO: Toggle funciona correctamente
     */
    function initScopeSystem() {
        
        // Toggle checkbox principal: mostrar/ocultar contenido
        $('.gdm-scope-checkbox').on('change', function() {
            const $checkbox = $(this);
            const target = $checkbox.attr('id').replace('_enabled', '');
            const $content = $('#' + target + '-content');
            const $summary = $('#' + target + '-summary');
            
            if ($checkbox.is(':checked')) {
                // Mostrar contenido para edición
                $content.addClass('active').slideDown(300);
                $summary.hide();
            } else {
                // Ocultar contenido y resumen
                $content.removeClass('active').slideUp(300);
                $summary.hide();
                
                // Desmarcar todos los items
                $content.find('input[type="checkbox"]').prop('checked', false);
                updateScopeCounter(target);
            }
        });
        
        // Botón "Editar": reabrir el contenido
        $('.gdm-scope-edit').on('click', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            const $checkbox = $('#gdm_' + target + '_enabled');
            const $content = $('#' + target + '-content');
            const $summary = $('#' + target + '-summary');
            
            // Marcar checkbox si no está marcado
            if (!$checkbox.is(':checked')) {
                $checkbox.prop('checked', true);
            }
            
            // Mostrar contenido y ocultar resumen
            $summary.hide();
            $content.addClass('active').slideDown(300);
        });
        
        // Botón "Aceptar": cerrar y mostrar resumen
        $('.gdm-scope-accept').on('click', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            const $content = $('#' + target + '-content');
            const $summary = $('#' + target + '-summary');
            const $checkbox = $('#gdm_' + target + '_enabled');
            
            // Obtener items seleccionados
            const selectedCount = $content.find('input[type="checkbox"]:checked').length;
            
            if (selectedCount === 0) {
                // Si no hay selección, desmarcar el checkbox principal
                $checkbox.prop('checked', false);
                $content.removeClass('active').slideUp(300);
                $summary.hide();
                return;
            }
            
            // Actualizar resumen
            updateScopeSummary(target);
            
            // Ocultar contenido y mostrar resumen
            $content.removeClass('active').slideUp(300);
            $summary.fadeIn(200);
            
            // Animación de confirmación
            const $button = $(this);
            const originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Aplicado').css('background', '#46b450');
            
            setTimeout(function() {
                $button.html(originalHtml).css('background', '');
            }, 1500);
        });
    }

    /**
     * Actualizar resumen de ámbito
     */
    function updateScopeSummary(target) {
        const $content = $('#' + target + '-content');
        const $summaryText = $('#' + target + '-summary-text');
        const selectedItems = [];
        
        // Obtener nombres de items seleccionados
        $content.find('input[type="checkbox"]:checked').each(function() {
            const itemName = $(this).closest('.gdm-checkbox-item').find('span').text().trim();
            selectedItems.push(itemName);
        });
        
        if (selectedItems.length > 0) {
            // Mostrar hasta 3 items, luego "y X más"
            let summaryText = '';
            if (selectedItems.length <= 3) {
                summaryText = selectedItems.join(', ');
            } else {
                summaryText = selectedItems.slice(0, 3).join(', ') + 
                             ' <em>y ' + (selectedItems.length - 3) + ' más</em>';
            }
            $summaryText.html(summaryText);
        }
    }

    /**
     * Actualizar contador de selección
     */
    function updateScopeCounter(target) {
        const $content = $('#' + target + '-content');
        const $counter = $('#' + target + '-counter');
        const count = $content.find('input[type="checkbox"]:checked').length;
        
        if (count > 0) {
            $counter.text(count + ' ' + (target === 'categorias' ? 'seleccionadas' : 
                         target === 'tags' ? 'seleccionadas' : 'seleccionados'));
        } else {
            $counter.text('Ninguno seleccionado');
        }
    }

    // =========================================================================
    // CATEGORÍAS
    // =========================================================================

    /**
     * Filtro de búsqueda de categorías
     */
    $('#gdm_category_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        let visibleCount = 0;
        
        $('#gdm-category-list .gdm-checkbox-item').each(function() {
            const categoryName = $(this).text().toLowerCase();
            
            if (categoryName.indexOf(searchTerm) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        // Mostrar mensaje si no hay resultados
        const $list = $('#gdm-category-list');
        $list.find('.gdm-empty-state').remove();
        
        if (visibleCount === 0) {
            $list.append(
                '<div class="gdm-empty-state">' +
                '<span class="dashicons dashicons-search"></span>' +
                '<p>No se encontraron categorías</p>' +
                '</div>'
            );
        }
    });

    /**
     * Actualizar contador al cambiar selección
     */
    $(document).on('change', '.gdm-category-checkbox', function() {
        updateScopeCounter('categorias');
    });

    // =========================================================================
    // TAGS
    // =========================================================================

    /**
     * Filtro de búsqueda de tags
     */
    $('#gdm_tag_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        let visibleCount = 0;
        
        $('#gdm-tag-list .gdm-checkbox-item').each(function() {
            const tagName = $(this).text().toLowerCase();
            
            if (tagName.indexOf(searchTerm) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        const $list = $('#gdm-tag-list');
        $list.find('.gdm-empty-state').remove();
        
        if (visibleCount === 0) {
            $list.append(
                '<div class="gdm-empty-state">' +
                '<span class="dashicons dashicons-search"></span>' +
                '<p>No se encontraron etiquetas</p>' +
                '</div>'
            );
        }
    });

    /**
     * Actualizar contador al cambiar selección
     */
    $(document).on('change', '.gdm-tag-checkbox', function() {
        updateScopeCounter('tags');
    });

    // =========================================================================
    // PRODUCTOS ESPECÍFICOS
    // =========================================================================

    /**
     * Búsqueda de productos con AJAX
     */
    let productSearchTimeout;
    $('#gdm_product_search').on('keyup', function() {
        clearTimeout(productSearchTimeout);
        const searchTerm = $(this).val();
        
        if (searchTerm.length < 3) {
            $('#gdm-product-list').html(
                '<div class="gdm-empty-state">' +
                '<p>Escribe al menos 3 caracteres para buscar</p>' +
                '</div>'
            );
            return;
        }

        productSearchTimeout = setTimeout(function() {
            searchProducts(searchTerm);
        }, 500);
    });

    /**
     * Buscar productos via AJAX
     */
    function searchProducts(searchTerm) {
        const $list = $('#gdm-product-list');
        
        $list.html('<div class="gdm-empty-state"><p>Buscando productos...</p></div>');

        $.ajax({
            url: gdmReglasMetabox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_search_products',
                nonce: gdmReglasMetabox.nonce,
                search: searchTerm
            },
            success: function(response) {
                if (response.success && response.data.products.length > 0) {
                    let html = '';
                    
                    // Obtener productos ya seleccionados
                    const selectedProducts = [];
                    $('[name="gdm_productos_objetivo[]"]:checked').each(function() {
                        selectedProducts.push($(this).val());
                    });
                    
                    response.data.products.forEach(function(product) {
                        const checked = selectedProducts.includes(product.id.toString()) ? 'checked' : '';
                        html += `
                            <label class="gdm-checkbox-item">
                                <input type="checkbox" 
                                       name="gdm_productos_objetivo[]" 
                                       value="${product.id}"
                                       class="gdm-product-checkbox"
                                       ${checked}>
                                <span>${product.title}</span>
                            </label>
                        `;
                    });
                    $list.html(html);
                    
                    // Reactivar eventos
                    $('.gdm-product-checkbox').on('change', function() {
                        updateScopeCounter('productos');
                    });
                    
                    updateScopeCounter('productos');
                } else {
                    $list.html(
                        '<div class="gdm-empty-state">' +
                        '<span class="dashicons dashicons-search"></span>' +
                        '<p>No se encontraron productos</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $list.html(
                    '<div class="gdm-empty-state">' +
                    '<p style="color:#dc3232;">Error al buscar productos</p>' +
                    '</div>'
                );
            }
        });
    }

    /**
     * Actualizar contador al cambiar selección
     */
    $(document).on('change', '.gdm-product-checkbox', function() {
        updateScopeCounter('productos');
    });

    // =========================================================================
    // UTILIDADES
    // =========================================================================

    /**
     * Función para copiar al portapapeles
     */
    window.copyToClipboard = function(element) {
        const text = $(element).text();
        const $temp = $('<input>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Feedback visual
        const originalText = $(element).html();
        $(element).html('✓ Copiado').css('background', '#46b450');
        
        setTimeout(function() {
            $(element).html(originalText).css('background', '');
        }, 1500);
    };

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

    // Inicializar módulos
    initModuleToggles();
    
    // Inicializar sistema de ámbitos
    initScopeSystem();
    
    // Inicializar contadores
    updateScopeCounter('categorias');
    updateScopeCounter('tags');
    updateScopeCounter('productos');
    
    // Estado inicial: ocultar contenidos si hay selección
    $('.gdm-scope-checkbox:checked').each(function() {
        const target = $(this).attr('id').replace('_enabled', '');
        const $content = $('#' + target + '-content');
        const $summary = $('#' + target + '-summary');
        
        // Si tiene items seleccionados, mostrar resumen
        const selectedCount = $content.find('input[type="checkbox"]:checked').length;
        if (selectedCount > 0) {
            $content.hide();
            updateScopeSummary(target);
            $summary.show();
        }
    });

    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Metabox Principal v6.1: Inicializado');
        console.log('Módulos disponibles:', $('.gdm-module-toggle').length);
        console.log('Módulos activos:', $('.gdm-module-toggle:checked').length);
        console.log('Ámbitos configurados:', $('.gdm-scope-checkbox:checked').length);
    }
});