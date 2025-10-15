/**
 * JavaScript para Metabox Principal de Reglas v6.1
 * Sistema modular con ámbitos mejorados
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
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
    // ÁMBITO: CATEGORÍAS
    // =========================================================================

    /**
     * Toggle de "Todas las categorías"
     */
    $('#gdm_todas_categorias').on('change', function() {
        const $wrapper = $('#gdm-categorias-wrapper');
        const $description = $('#gdm-categorias-description');
        
        if ($(this).is(':checked')) {
            $wrapper.slideUp(200);
            $description.slideUp(200);
            $('.gdm-category-checkbox').prop('checked', false);
            updateCategoryCounter();
        } else {
            $wrapper.slideDown(200);
            $description.slideDown(200);
        }
    });

    /**
     * Filtro de búsqueda de categorías
     */
    $('#gdm_category_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        let visibleCount = 0;
        
        $('.gdm-category-list .gdm-checkbox-item').each(function() {
            const categoryName = $(this).text().toLowerCase();
            
            if (categoryName.indexOf(searchTerm) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        // Mostrar mensaje si no hay resultados
        const $list = $('.gdm-category-list');
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
     * Contador de categorías seleccionadas
     */
    $('.gdm-category-checkbox').on('change', function() {
        updateCategoryCounter();
        updateCategoryDescription();
    });

    function updateCategoryCounter() {
        const count = $('.gdm-category-checkbox:checked').length;
        const $counter = $('#gdm-category-counter');
        
        if (count > 0) {
            $counter.text(count).show();
        } else {
            $counter.hide();
        }
    }

    function updateCategoryDescription() {
        const $description = $('#gdm-categorias-description');
        const selectedCategories = [];
        
        $('.gdm-category-checkbox:checked').each(function() {
            selectedCategories.push($(this).closest('.gdm-checkbox-item').find('span').text());
        });

        if (selectedCategories.length > 0) {
            const categoriesText = selectedCategories.join(', ');
            $description.find('.gdm-scope-selected-items').html(
                '<strong>Solo categorías:</strong> ' + categoriesText
            );
            $description.addClass('active');
        } else {
            $description.removeClass('active');
        }
    }

    /**
     * Botón aplicar categorías
     */
    $('#gdm-categorias-apply').on('click', function() {
        const $button = $(this);
        const count = $('.gdm-category-checkbox:checked').length;
        
        if (count === 0) {
            alert('Por favor selecciona al menos una categoría');
            return;
        }

        // Animación de confirmación
        const originalText = $button.html();
        $button.html('✓ Aplicado').css('background', '#46b450');
        
        setTimeout(function() {
            $button.html(originalText).css('background', '');
        }, 1500);

        updateCategoryDescription();
    });

    // =========================================================================
    // ÁMBITO: TAGS
    // =========================================================================

    /**
     * Toggle de "Cualquier Tag"
     */
    $('#gdm_cualquier_tag').on('change', function() {
        const $wrapper = $('#gdm-tags-wrapper');
        const $description = $('#gdm-tags-description');
        
        if ($(this).is(':checked')) {
            $wrapper.slideUp(200);
            $description.slideUp(200);
            $('.gdm-tag-checkbox').prop('checked', false);
            updateTagCounter();
        } else {
            $wrapper.slideDown(200);
            $description.slideDown(200);
        }
    });

    /**
     * Filtro de búsqueda de tags
     */
    $('#gdm_tag_filter').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        let visibleCount = 0;
        
        $('.gdm-tag-list .gdm-checkbox-item').each(function() {
            const tagName = $(this).text().toLowerCase();
            
            if (tagName.indexOf(searchTerm) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        // Mostrar mensaje si no hay resultados
        const $list = $('.gdm-tag-list');
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
     * Contador de tags seleccionados
     */
    $('.gdm-tag-checkbox').on('change', function() {
        updateTagCounter();
        updateTagDescription();
    });

    function updateTagCounter() {
        const count = $('.gdm-tag-checkbox:checked').length;
        const $counter = $('#gdm-tag-counter');
        
        if (count > 0) {
            $counter.text(count).show();
        } else {
            $counter.hide();
        }
    }

    function updateTagDescription() {
        const $description = $('#gdm-tags-description');
        const selectedTags = [];
        
        $('.gdm-tag-checkbox:checked').each(function() {
            selectedTags.push($(this).closest('.gdm-checkbox-item').find('span').text());
        });

        if (selectedTags.length > 0) {
            const tagsText = selectedTags.join(', ');
            $description.find('.gdm-scope-selected-items').html(
                '<strong>Solo etiquetas:</strong> ' + tagsText
            );
            $description.addClass('active');
        } else {
            $description.removeClass('active');
        }
    }

    /**
     * Botón aplicar tags
     */
    $('#gdm-tags-apply').on('click', function() {
        const $button = $(this);
        const count = $('.gdm-tag-checkbox:checked').length;
        
        if (count === 0) {
            alert('Por favor selecciona al menos una etiqueta');
            return;
        }

        // Animación de confirmación
        const originalText = $button.html();
        $button.html('✓ Aplicado').css('background', '#46b450');
        
        setTimeout(function() {
            $button.html(originalText).css('background', '');
        }, 1500);

        updateTagDescription();
    });

    // =========================================================================
    // ÁMBITO: PRODUCTOS ESPECÍFICOS
    // =========================================================================

    /**
     * Toggle de productos específicos
     */
    $('#gdm_productos_especificos_enabled').on('change', function() {
        const $wrapper = $('#gdm-productos-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    /**
     * Búsqueda de productos con AJAX
     */
    let productSearchTimeout;
    $('#gdm_product_search').on('keyup', function() {
        clearTimeout(productSearchTimeout);
        const searchTerm = $(this).val();
        
        if (searchTerm.length < 3) {
            $('.gdm-product-list').html(
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

    function searchProducts(searchTerm) {
        const $list = $('.gdm-product-list');
        
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
                    $('.gdm-product-checkbox').on('change', updateProductCounter);
                    updateProductCounter();
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

    function updateProductCounter() {
        const count = $('.gdm-product-checkbox:checked').length;
        const $counter = $('#gdm-product-counter');
        
        if (count > 0) {
            $counter.text(count).show();
        } else {
            $counter.hide();
        }
    }

    // =========================================================================
    // ÁMBITO: ATRIBUTOS
    // =========================================================================

    $('#gdm_atributos_enabled').on('change', function() {
        const $wrapper = $('#gdm-atributos-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    // =========================================================================
    // ÁMBITO: STOCK
    // =========================================================================

    $('#gdm_stock_enabled').on('change', function() {
        const $wrapper = $('#gdm-stock-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    // =========================================================================
    // ÁMBITO: PRECIO
    // =========================================================================

    $('#gdm_precio_enabled').on('change', function() {
        const $wrapper = $('#gdm-precio-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    /**
     * Toggle de precio "entre"
     */
    $('#gdm_precio_condicion').on('change', function() {
        if ($(this).val() === 'entre') {
            $('#gdm-precio-valor2-wrapper').slideDown(200);
        } else {
            $('#gdm-precio-valor2-wrapper').slideUp(200);
        }
    });

    // =========================================================================
    // ÁMBITO: TÍTULO
    // =========================================================================

    $('#gdm_titulo_enabled').on('change', function() {
        const $wrapper = $('#gdm-titulo-wrapper');
        
        if ($(this).is(':checked')) {
            $wrapper.slideDown(200);
        } else {
            $wrapper.slideUp(200);
        }
    });

    // =========================================================================
    // TOGGLE GENERAL DE SECCIONES DE ÁMBITO
    // =========================================================================

    /**
     * Manejar toggle de todas las secciones de ámbito
     */
    $('.gdm-scope-toggle input[type="checkbox"]').each(function() {
        const $checkbox = $(this);
        const $content = $checkbox.closest('.gdm-scope-group').find('.gdm-scope-content');
        
        // Si el checkbox NO está marcado, mostrar el contenido
        // Si está marcado (ej: "Todas las categorías"), ocultar el contenido
        if (!$checkbox.is(':checked')) {
            $content.show();
        }
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
    
    // Cargar contadores al inicio
    updateCategoryCounter();
    updateCategoryDescription();
    updateTagCounter();
    updateTagDescription();
    updateProductCounter();

    // Debug
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Metabox Principal v6.1: Inicializado');
        console.log('Módulos disponibles:', $('.gdm-module-toggle').length);
        console.log('Módulos activos:', $('.gdm-module-toggle:checked').length);
        console.log('Categorías seleccionadas:', $('.gdm-category-checkbox:checked').length);
        console.log('Tags seleccionados:', $('.gdm-tag-checkbox:checked').length);
        console.log('Productos seleccionados:', $('.gdm-product-checkbox:checked').length);
    }
});