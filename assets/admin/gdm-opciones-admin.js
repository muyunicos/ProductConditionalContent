/**
 * Admin Script para Opciones de Producto
 * Versión: 1.0.0
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 */
jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // CAMBIO DINÁMICO DE CONFIGURACIÓN SEGÚN TIPO DE OPCIÓN
    // =========================================================================

    /**
     * Actualiza la visibilidad de secciones de configuración según el tipo seleccionado
     */
    function updateTypeConfig() {
        const tipo = $('#gdm_opcion_tipo').val();
        
        // Ocultar todas las secciones de configuración específica
        $('.gdm-tipo-config').hide();
        
        // Mostrar la sección correspondiente según el tipo
        if (['text', 'textarea', 'email', 'tel', 'number', 'url', 'date'].includes(tipo)) {
            $('#gdm-config-text').show();
        } else if (['file', 'file_multi'].includes(tipo)) {
            $('#gdm-config-file').show();
        } else if (['select', 'radio', 'checkbox'].includes(tipo)) {
            $('#gdm-config-choices').show();
        }
    }

    // Ejecutar al cargar y al cambiar
    updateTypeConfig();
    $('#gdm_opcion_tipo').on('change', updateTypeConfig);

    // =========================================================================
    // GESTIÓN DE OPCIONES DE SELECCIÓN (Choices)
    // =========================================================================

    let choiceIndex = $('#gdm-choices-tbody tr').length || 0;

    /**
     * Añadir nueva opción de selección
     */
    $('#gdm-add-choice').on('click', function(e) {
        e.preventDefault();
        
        const template = $('#gdm-choice-template').html();
        const newRow = template.replace(/__INDEX__/g, choiceIndex);
        
        $('#gdm-choices-tbody').append(newRow);
        choiceIndex++;
        
        // Reiniciar sortable
        initSortable();
    });

    /**
     * Eliminar opción de selección
     */
    $(document).on('click', '.gdm-remove-choice', function(e) {
        e.preventDefault();
        
        if (confirm('¿Eliminar esta opción?')) {
            $(this).closest('tr').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });

    /**
     * Inicializar drag & drop para reordenar opciones
     */
    function initSortable() {
        if ($.fn.sortable) {
            $('#gdm-choices-tbody').sortable({
                handle: '.gdm-sort-handle',
                axis: 'y',
                cursor: 'move',
                placeholder: 'gdm-sortable-placeholder',
                opacity: 0.7,
                update: function(event, ui) {
                    updateChoiceIndexes();
                }
            });
        }
    }

    /**
     * Actualizar índices después de reordenar
     */
    function updateChoiceIndexes() {
        $('#gdm-choices-tbody tr').each(function(newIndex) {
            $(this).attr('data-index', newIndex);
            $(this).find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    }

    // Inicializar sortable al cargar
    initSortable();

    // =========================================================================
    // VALIDACIÓN DE SLUG ÚNICO
    // =========================================================================

    /**
     * Validar que el slug sea único
     */
    $('#gdm_opcion_slug').on('blur', function() {
        const slug = $(this).val();
        const postId = gdmOpcionesAdmin.currentPostId;
        
        if (!slug) return;
        
        // Aquí podrías hacer una llamada AJAX para verificar si el slug existe
        // Por ahora solo validamos el formato
        const slugPattern = /^[a-z0-9-]+$/;
        if (!slugPattern.test(slug)) {
            alert('El slug solo puede contener letras minúsculas, números y guiones.');
            $(this).focus();
        }
    });

    /**
     * Auto-generar slug desde el título si está vacío
     */
    $('#title').on('blur', function() {
        const slugField = $('#gdm_opcion_slug');
        
        // Solo auto-generar si el slug está vacío
        if (slugField.val() === '') {
            const title = $(this).val();
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Eliminar caracteres especiales
                .replace(/\s+/g, '-') // Espacios a guiones
                .replace(/-+/g, '-') // Múltiples guiones a uno
                .replace(/^-|-$/g, ''); // Eliminar guiones al inicio/fin
            
            slugField.val(slug);
        }
    });

    // =========================================================================
    // VALIDACIÓN ANTES DE PUBLICAR
    // =========================================================================

    /**
     * Validar formulario antes de submit
     */
    $('#post').on('submit', function(e) {
        const title = $('#title').val().trim();
        const slug = $('#gdm_opcion_slug').val().trim();
        const label = $('#gdm_opcion_label').val().trim();
        const tipo = $('#gdm_opcion_tipo').val();
        
        // Validar campos requeridos
        if (!title) {
            alert('Por favor ingresa un título para la opción.');
            $('#title').focus();
            e.preventDefault();
            return false;
        }
        
        if (!slug) {
            alert('Por favor ingresa un slug (identificador) para la opción.');
            $('#gdm_opcion_slug').focus();
            e.preventDefault();
            return false;
        }
        
        if (!label) {
            alert('Por favor ingresa una etiqueta visible para la opción.');
            $('#gdm_opcion_label').focus();
            e.preventDefault();
            return false;
        }
        
        // Validar opciones de selección si es select/radio/checkbox
        if (['select', 'radio', 'checkbox'].includes(tipo)) {
            const choicesCount = $('#gdm-choices-tbody tr').length;
            if (choicesCount === 0) {
                alert('Por favor agrega al menos una opción de selección.');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });

    // =========================================================================
    // HELPERS Y UTILIDADES
    // =========================================================================

    /**
     * Mostrar/ocultar secciones con efectos
     */
    $('.gdm-section h3').on('click', function() {
        $(this).toggleClass('collapsed');
        $(this).next().slideToggle(200);
    });

    /**
     * Console log para debugging (solo en desarrollo)
     */
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('GDM Opciones Admin Script v1.0.0 cargado correctamente');
        console.log('Current Post ID:', gdmOpcionesAdmin.currentPostId);
    }

});
