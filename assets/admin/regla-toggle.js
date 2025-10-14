/**
 * Toggle Status Handler para Reglas
 * Manejo de estado habilitada/deshabilitada con AJAX
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 5.0.3
 */

(function($) {
    'use strict';

    // =========================================================================
    // VARIABLES GLOBALES
    // =========================================================================

    let activeToggles = new Set(); // Prevenir múltiples clics

    // =========================================================================
    // TOGGLE HANDLER
    // =========================================================================

    /**
     * Inicializar todos los toggles en la página
     */
    function initToggles() {
        $('.gdm-toggle-switch').each(function() {
            const $toggle = $(this);
            const postId = $toggle.data('post-id');
            
            if (!postId) {
                console.warn('Toggle sin post-id:', $toggle);
                return;
            }
            
            // Bind event
            $toggle.off('click').on('click', handleToggleClick);
        });
    }

    /**
     * Manejar clic en toggle
     */
    function handleToggleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $toggle = $(this);
        const $input = $toggle.find('input[type="checkbox"]');
        const postId = $toggle.data('post-id');
        const currentStatus = $toggle.data('current-status');
        
        // Prevenir clics múltiples
        if (activeToggles.has(postId)) {
            return;
        }
        
        // Calcular nuevo estado
        const isEnabled = currentStatus === 'habilitada';
        const newStatus = isEnabled ? 'deshabilitada' : 'habilitada';
        
        // Confirmar si está deshabilitando y tiene programación
        const hasProgramacion = $toggle.data('has-programacion') === true;
        
        if (isEnabled && hasProgramacion) {
            const confirmMessage = gdmToggle.i18n.confirmDisable || 
                '¿Estás seguro? Esta regla tiene programación activa que será ignorada.';
            
            if (!confirm(confirmMessage)) {
                return;
            }
        }
        
        // Ejecutar cambio
        toggleStatus(postId, newStatus, $toggle);
    }

    /**
     * Cambiar estado vía AJAX
     */
    function toggleStatus(postId, newStatus, $toggle) {
        // Marcar como activo
        activeToggles.add(postId);
        
        // UI: Estado loading
        $toggle.addClass('loading');
        const $row = $toggle.closest('tr');
        
        // AJAX Request
        $.ajax({
            url: gdmToggle.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdm_toggle_status',
                nonce: gdmToggle.nonce,
                post_id: postId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar UI
                    updateToggleUI($toggle, newStatus, response.data);
                    
                    // Actualizar fila completa
                    updateRowStatus($row, newStatus, response.data);
                    
                    // Mostrar notificación
                    showNotification(response.data.message, 'success');
                } else {
                    // Error del servidor
                    showNotification(response.data.message || gdmToggle.i18n.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Toggle AJAX Error:', error);
                showNotification(gdmToggle.i18n.ajaxError || 'Error de conexión', 'error');
            },
            complete: function() {
                // Remover loading
                $toggle.removeClass('loading');
                activeToggles.delete(postId);
            }
        });
    }

    /**
     * Actualizar UI del toggle
     */
    function updateToggleUI($toggle, newStatus, data) {
        const $input = $toggle.find('input[type="checkbox"]');
        const isEnabled = newStatus === 'habilitada';
        
        // Actualizar checkbox
        $input.prop('checked', isEnabled);
        
        // Actualizar data attributes
        $toggle.attr('data-current-status', newStatus);
        
        // Actualizar aria-label
        $toggle.attr('aria-label', 
            isEnabled ? gdmToggle.i18n.enabled : gdmToggle.i18n.disabled
        );
        
        // Animar el cambio
        $toggle.addClass('changing');
        setTimeout(function() {
            $toggle.removeClass('changing');
        }, 300);
    }

    /**
     * Actualizar toda la fila de la tabla
     */
    function updateRowStatus($row, newStatus, data) {
        const $statusCell = $row.find('.column-gdm_estado');
        
        if ($statusCell.length && data.sub_status) {
            // Actualizar badge de estado
            const badgeHTML = generateStatusBadge(newStatus, data.sub_status);
            $statusCell.html(badgeHTML);
        }
        
        // Highlight temporal
        $row.addClass('gdm-row-updated');
        setTimeout(function() {
            $row.removeClass('gdm-row-updated');
        }, 2000);
    }

    /**
     * Generar HTML del badge de estado
     */
    function generateStatusBadge(status, subStatus) {
        if (!subStatus) {
            return '';
        }
        
        const statusClass = subStatus.class || 'status-disabled';
        const statusLabel = subStatus.label || status;
        const statusDesc = subStatus.description || '';
        
        let html = '<div class="gdm-status-with-description">';
        html += '<span class="gdm-status-badge ' + statusClass + '">';
        html += '<span class="gdm-status-icon"></span>';
        html += statusLabel;
        html += '</span>';
        
        if (statusDesc) {
            html += '<span class="gdm-status-description">' + statusDesc + '</span>';
        }
        
        html += '</div>';
        
        return html;
    }

    /**
     * Mostrar notificación temporal
     */
    function showNotification(message, type) {
        // Remover notificaciones anteriores
        $('.gdm-toggle-notification').remove();
        
        // Crear notificación
        const $notification = $('<div>', {
            class: 'gdm-toggle-notification ' + type,
            html: '<span class="dashicons dashicons-' + 
                  (type === 'success' ? 'yes-alt' : 'warning') + 
                  '"></span> ' + message
        });
        
        // Agregar al DOM
        $('body').append($notification);
        
        // Auto-ocultar después de 3 segundos
        setTimeout(function() {
            $notification.addClass('hiding');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }

    // =========================================================================
    // METABOX TOGGLE
    // =========================================================================

    /**
     * Inicializar toggle en metabox de edición
     */
    function initMetaboxToggle() {
        const $metaboxToggle = $('#gdm-metabox-toggle');
        
        if (!$metaboxToggle.length) {
            return;
        }
        
        $metaboxToggle.on('change', function() {
            const isEnabled = $(this).is(':checked');
            updateMetaboxUI(isEnabled);
        });
        
        // Estado inicial
        const isEnabled = $metaboxToggle.is(':checked');
        updateMetaboxUI(isEnabled);
    }

    /**
     * Actualizar UI del metabox según estado
     */
    function updateMetaboxUI(isEnabled) {
        const $indicator = $('.gdm-status-indicator');
        const $programacionSection = $('.gdm-schedule-section');
        
        // Actualizar indicador visual
        $indicator
            .removeClass('status-disabled status-active')
            .addClass(isEnabled ? 'status-active' : 'status-disabled');
        
        // Actualizar texto del indicador
        const $statusText = $indicator.find('.gdm-status-display');
        if ($statusText.length) {
            $statusText.text(
                isEnabled ? gdmToggle.i18n.enabled : gdmToggle.i18n.disabled
            );
        }
        
        // Habilitar/deshabilitar sección de programación
        if (!isEnabled) {
            $programacionSection.find('input, select').prop('disabled', true);
            $programacionSection.css('opacity', '0.5');
        } else {
            $programacionSection.find('input, select').prop('disabled', false);
            $programacionSection.css('opacity', '1');
        }
    }

    // =========================================================================
    // QUICK EDIT
    // =========================================================================

    /**
     * Popular Quick Edit con datos del toggle
     */
    function populateQuickEdit(postId) {
        const $row = $('#post-' + postId);
        const $toggle = $row.find('.gdm-toggle-switch');
        
        if (!$toggle.length) {
            return;
        }
        
        const currentStatus = $toggle.data('current-status');
        const isEnabled = currentStatus === 'habilitada';
        
        // Actualizar checkbox en Quick Edit
        const $quickEditToggle = $('.inline-edit-row').find('input[name="gdm_quick_toggle"]');
        if ($quickEditToggle.length) {
            $quickEditToggle.prop('checked', isEnabled);
        }
    }

    // =========================================================================
    // FILTROS Y BÚSQUEDA
    // =========================================================================

    /**
     * Agregar filtro rápido por estado
     */
    function addStatusFilter() {
        const $subsubsub = $('.subsubsub');
        
        if (!$subsubsub.length || $subsubsub.find('.gdm-filter-added').length) {
            return;
        }
        
        // Marcar como agregado
        $subsubsub.addClass('gdm-filter-added');
        
        // Agregar separador si hay otros filtros
        if ($subsubsub.find('li').length > 0) {
            $subsubsub.append(' | ');
        }
        
        // Filtro "Solo activas"
        const currentUrl = window.location.href;
        const hasActiveFilter = currentUrl.includes('gdm_filter=active');
        
        $subsubsub.append(
            '<li class="gdm-filter-active">' +
            '<a href="' + addQueryArg('gdm_filter', 'active') + '" ' +
            (hasActiveFilter ? 'class="current"' : '') + '>' +
            gdmToggle.i18n.onlyActive +
            '</a></li>'
        );
    }

    /**
     * Helper: Agregar parámetro a URL
     */
    function addQueryArg(key, value) {
        const url = new URL(window.location.href);
        url.searchParams.set(key, value);
        return url.toString();
    }

    // =========================================================================
    // KEYBOARD SHORTCUTS
    // =========================================================================

    /**
     * Atajos de teclado para toggle
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + Shift + T = Toggle seleccionados
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                toggleSelectedRows();
            }
        });
    }

    /**
     * Toggle en filas seleccionadas (bulk action)
     */
    function toggleSelectedRows() {
        const $checkedRows = $('input[name="post[]"]:checked');
        
        if ($checkedRows.length === 0) {
            showNotification(gdmToggle.i18n.noSelection || 'Selecciona al menos una regla', 'error');
            return;
        }
        
        const confirmMessage = gdmToggle.i18n.confirmBulkToggle || 
            '¿Cambiar el estado de ' + $checkedRows.length + ' regla(s)?';
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $checkedRows.each(function() {
            const postId = $(this).val();
            const $row = $('#post-' + postId);
            const $toggle = $row.find('.gdm-toggle-switch');
            
            if ($toggle.length) {
                $toggle.trigger('click');
            }
        });
    }

    // =========================================================================
    // INIT
    // =========================================================================

    $(document).ready(function() {
        // Solo en la página de listado de reglas
        if ($('body').hasClass('post-type-gdm_regla')) {
            initToggles();
            initMetaboxToggle();
            addStatusFilter();
            initKeyboardShortcuts();
            
            console.log('✅ GDM Toggle: Initialized');
        }
    });

    // =========================================================================
    // COMPATIBILIDAD CON QUICK EDIT
    // =========================================================================

    // Hook para cuando se abre Quick Edit
    $(document).on('click', '.editinline', function() {
        const postId = $(this).closest('tr').attr('id').replace('post-', '');
        setTimeout(function() {
            populateQuickEdit(postId);
        }, 100);
    });

    // =========================================================================
    // EXPORT PÚBLICO (para extensiones)
    // =========================================================================

    window.GDM_Toggle = {
        init: initToggles,
        toggle: toggleStatus,
        notify: showNotification
    };

})(jQuery);