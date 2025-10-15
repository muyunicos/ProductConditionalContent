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
        
        // ✅ FIX: Inicializar estado de resúmenes al cargar página
        initScopeSummaries();
        
        /**
         * ✅ NUEVO: Inicializar estado de resúmenes existentes
         */
        function initScopeSummaries() {
            $('.gdm-scope-group').each(function() {
                const $group = $(this);
                const $checkbox = $group.find('.gdm-scope-checkbox');
                const $summary = $group.find('.gdm-scope-summary');
                const $content = $group.find('.gdm-scope-content');
                
                // Si hay resumen con contenido, mostrarlo
                if ($checkbox.is(':checked') && $summary.find('.gdm-summary-text').text().trim()) {
                    $summary.show();
                    $content.hide();
                } else if ($checkbox.is(':checked')) {
                    // Si checkbox está marcado pero no hay resumen, abrir contenido
                    $content.show().addClass('active');
                    $summary.hide();
                } else {
                    // Si no está marcado, ocultar todo
                    $summary.hide();
                    $content.hide();
                }
            });
        }
        
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
                
                // Verificar si ya existe un resumen
                if ($summary.find('.gdm-summary-text').text().trim()) {
                    // Si hay resumen, mostrarlo en vez del contenido
                    $summary.fadeIn(200);
                    $content.hide().removeClass('active');
                } else {
                    // Si no hay resumen, abrir contenido para configurar
                    $content.addClass('active').slideDown(300);
                    $summary.hide();
                }
            } else {
                // Desmarcar y ocultar todo
                $content.removeClass('active').slideUp(300);
                $summary.slideUp(200);
                
                // Limpiar datos al desactivar
                $content.find('input[type="checkbox"]').prop('checked', false);
                $content.find('input[type="text"], input[type="number"]').val('');
                $content.find('select').prop('selectedIndex', 0);
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
            $summary.slideUp(200);
            $content.addClass('active').slideDown(300);
        });
        
        // Botón "Guardar"
        $(document).on('click', '.gdm-scope-save', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $button = $(this);
    const $scopeGroup = $button.closest('.gdm-scope-group');
    const $content = $scopeGroup.find('.gdm-scope-content');
    const $summary = $scopeGroup.find('.gdm-scope-summary');
    const $summaryText = $summary.find('.gdm-summary-text');
    const $checkbox = $scopeGroup.find('.gdm-scope-checkbox');
    const $counter = $scopeGroup.find('.gdm-selection-counter');
    const scopeId = $scopeGroup.data('scope');
    
    // Verificar si hay selección REAL
    const hasSelection = checkHasSelection($content, scopeId);
    
    if (!hasSelection) {
        // Si no hay selección, desactivar
        $checkbox.prop('checked', false);
        $content.removeClass('active').slideUp(300);
        $summary.hide();
        $counter.text('Ninguno seleccionado');
        return false; // ✅ NUEVO: Return explícito
    }
    
    // Generar resumen dinámico
    const summary = generateSummary(scopeId, $content);
    $summaryText.html(summary);
    
    // Actualizar contador
    const counterText = generateCounterText(scopeId, $content);
    $counter.text(counterText);
    
    // Activar checkbox y mostrar resumen
    $checkbox.prop('checked', true);
    $content.removeClass('active').slideUp(300, function() {
        $summary.fadeIn(200);
    });
    
    const originalHtml = $button.html();
    $button.html('<span class="dashicons dashicons-yes"></span> Guardado')
           .css('background', '#46b450')
           .prop('disabled', true);
    
    setTimeout(function() {
        $button.html(originalHtml)
               .css('background', '')
               .prop('disabled', false);
    }, 1500);
    
    return false;
});

// Botón "Cancelar"
$(document).on('click', '.gdm-scope-cancel', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $scopeGroup = $(this).closest('.gdm-scope-group');
    const $content = $scopeGroup.find('.gdm-scope-content');
    const $summary = $scopeGroup.find('.gdm-scope-summary');
    const $checkbox = $scopeGroup.find('.gdm-scope-checkbox');
    const scopeId = $scopeGroup.data('scope');
    
    // Restaurar estado original
    restoreOriginalState(scopeId);
    
    // Cerrar contenido
    $content.removeClass('active').slideUp(300);
    
    // Si había resumen antes, mostrarlo
    if ($checkbox.is(':checked') && $summary.find('.gdm-summary-text').text().trim()) {
        $summary.fadeIn(200);
    }
    
    return false;
});
        
        /**
         * Verificar si hay selección real
         */
        function checkHasSelection($content, scopeId) {
            // Checkboxes marcados
            const checkedBoxes = $content.find('input[type="checkbox"]:checked').length;
            
            // Inputs con texto
            const textInputs = $content.find('input[type="text"]').filter(function() { 
                return $(this).val().trim() !== ''; 
            }).length;
            
            // Inputs numéricos con valor > 0
            const numInputs = $content.find('input[type="number"]').filter(function() { 
                return parseFloat($(this).val()) > 0; 
            }).length;
            
            // Selects con valor
            const selects = $content.find('select').filter(function() {
                return $(this).val() && $(this).val() !== '';
            }).length;
            
            return (checkedBoxes > 0 || textInputs > 0 || numInputs > 0 || selects > 0);
        }
        
        /**
         * ✅ Generar resumen dinámico según tipo de ámbito
         */
        function generateSummary(scopeId, $content) {
            let summary = '';
            
            // Categorías, Tags, Productos
            if (['categorias', 'tags', 'productos'].includes(scopeId)) {
                const $checked = $content.find('input[type="checkbox"]:checked');
                const count = $checked.length;
                
                if (count > 0) {
                    const names = [];
                    $checked.slice(0, 3).each(function() {
                        const label = $(this).closest('label').find('span:first').text().trim();
                        // Limpiar contador "(X)"
                        const cleanLabel = label.replace(/\(\d+\)$/, '').trim();
                        names.push(cleanLabel);
                    });
                    
                    summary = names.join(', ');
                    if (count > 3) {
                        summary += ' <em>y ' + (count - 3) + ' más</em>';
                    }
                }
            }
            
            // Atributos
            else if (scopeId === 'atributos') {
                const groups = {};
                $content.find('.gdm-attribute-group').each(function() {
                    const attrName = $(this).find('.gdm-attribute-title').text().replace(':', '').trim();
                    const count = $(this).find('input:checked').length;
                    if (count > 0) {
                        groups[attrName] = count;
                    }
                });
                
                const parts = [];
                for (const [attr, count] of Object.entries(groups)) {
                    parts.push('<strong>' + attr + '</strong> (' + count + ')');
                }
                summary = parts.join(', ');
            }
            
            // Stock
            else if (scopeId === 'stock') {
                const statuses = [];
                $content.find('input:checked').each(function() {
                    const badge = $(this).closest('label').find('.gdm-status-badge').text().trim();
                    statuses.push(badge);
                });
                summary = statuses.join(', ');
            }
            
            // Precio
            else if (scopeId === 'precio') {
                const condicion = $content.find('select[name*="_condicion"]').val();
                const min = $content.find('input[name*="_min"]').val();
                const max = $content.find('input[name*="_max"]').val();
                
                // ✅ USAR EL TEXTO DEL PREVIEW EN VEZ DE FORMATEAR MANUALMENTE
                const minFormatted = $content.find('.gdm-preview-min').text();
                const maxFormatted = $content.find('.gdm-preview-max').text();
                
                const labels = {
                    'mayor_que': 'Mayor que',
                    'menor_que': 'Menor que',
                    'entre': 'Entre',
                    'igual_a': 'Igual a'
                };
                
                if (condicion === 'entre') {
                    summary = labels[condicion] + ' <strong>' + minFormatted + '</strong> y <strong>' + maxFormatted + '</strong>';
                } else {
                    summary = labels[condicion] + ' <strong>' + minFormatted + '</strong>';
                }
            }
            
            // Título
            else if (scopeId === 'titulo') {
                const condicion = $content.find('select[name*="_condicion"]').val();
                const texto = $content.find('input[name*="_texto"]').val();
                
                const labels = {
                    'contiene': 'Contiene',
                    'no_contiene': 'No contiene',
                    'empieza_con': 'Empieza con',
                    'termina_con': 'Termina con',
                    'regex': 'Regex'
                };
                
                summary = labels[condicion] + ': <em>"' + texto + '"</em>';
            }
            
            return summary || 'Configurado';
        }
        
        /**
         * ✅ Generar texto del contador
         */
        function generateCounterText(scopeId, $content) {
            if (['categorias', 'tags', 'productos'].includes(scopeId)) {
                const count = $content.find('input[type="checkbox"]:checked').length;
                const labels = {
                    'categorias': 'seleccionadas',
                    'tags': 'seleccionadas',
                    'productos': 'seleccionados'
                };
                return count > 0 ? count + ' ' + labels[scopeId] : 'Ninguno seleccionado';
            }
            
            if (scopeId === 'atributos') {
                const count = $content.find('input[type="checkbox"]:checked').length;
                return count > 0 ? count + ' valores seleccionados' : 'Ninguno seleccionado';
            }
            
            if (scopeId === 'stock') {
                const count = $content.find('input[type="checkbox"]:checked').length;
                return count > 0 ? count + ' estados seleccionados' : 'Ninguno seleccionado';
            }
            
            if (['precio', 'titulo'].includes(scopeId)) {
                return checkHasSelection($content, scopeId) ? 'Configurado' : 'Sin configurar';
            }
            
            return 'Sin configurar';
        }
        
        /**
         * Guardar estado original de un ámbito
         */
        function saveOriginalState(scopeId) {
            const $scopeGroup = $('[data-scope="' + scopeId + '"]');
            const $content = $scopeGroup.find('.gdm-scope-content');
            
            originalData[scopeId] = {
                checkboxes: [],
                inputs: {},
                selects: {}
            };
            
            // Guardar checkboxes
            $content.find('input[type="checkbox"]').each(function() {
                const name = $(this).attr('name');
                const value = $(this).val();
                const checked = $(this).is(':checked');
                if (name) {
                    originalData[scopeId].checkboxes.push({ name, value, checked });
                }
            });
            
            // Guardar inputs de texto/número
            $content.find('input[type="text"], input[type="number"]').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    originalData[scopeId].inputs[name] = $(this).val();
                }
            });
            
            // Guardar selects
            $content.find('select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    originalData[scopeId].selects[name] = $(this).val();
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
            
            // Restaurar selects
            for (let name in originalData[scopeId].selects) {
                $content.find('select[name="' + name + '"]').val(originalData[scopeId].selects[name]);
            }
        }
    }

    // =========================================================================
    // INICIALIZACIÓN
    // =========================================================================

if (window.gdmMetaboxInitialized) {
    return;
}
window.gdmMetaboxInitialized = true;

initModuleToggles();
initScopeSystem();

    // =========================================================================
    // DEBUG
    // =========================================================================

    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('✅ GDM Metabox v6.2: Inicializado');
        console.log('📦 Módulos disponibles:', $('.gdm-module-toggle').length);
        console.log('✔️ Módulos activos:', $('.gdm-module-toggle:checked').length);
        console.log('🎯 Ámbitos disponibles:', $('.gdm-scope-group').length);
        console.log('📊 Ámbitos con selección:', $('.gdm-scope-checkbox:checked').length);
    }
});