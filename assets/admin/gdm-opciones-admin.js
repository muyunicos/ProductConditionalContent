/**
 * Admin Script para Opciones de Producto
 * Versión: 1.0.0
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * Fecha: 2025-10-14
 */
jQuery(document).ready(function($) {
    'use strict';

    // =========================================================================
    // MANEJO DINÁMICO DE TIPOS DE OPCIÓN
    // =========================================================================

    /**
     * Mostrar/ocultar secciones según tipo de opción seleccionado
     */
    function handleTipoOpcionChange() {
        const tipoSeleccionado = $('#gdm_opcion_tipo').val();
        
        // Ocultar todas las configuraciones específicas
        $('.gdm-tipo-config').hide();
        
        // Mostrar la configuración correspondiente
        const tiposTexto = ['text', 'textarea', 'email', 'tel', 'number', 'url', 'date'];
        const tiposArchivo = ['file', 'file_multi'];
        const tiposSeleccion = ['select', 'radio', 'checkbox'];
        
        if (tiposTexto.includes(tipoSeleccionado)) {
            $('#gdm-config-text').show();
        } else if (tiposArchivo.includes(tipoSeleccionado)) {
            $('#gdm-config-file').show();
        } else if (tiposSeleccion.includes(tipoSeleccionado)) {
            $('#gdm-config-choices').show();
        }
    }

    // Ejecutar al cambiar tipo y al cargar
    $('#gdm_opcion_tipo').on('change', handleTipoOpcionChange);
    handleTipoOpcionChange();

    // =========================================================================
    // MANEJO DE OPCIONES DE SELECCIÓN (CHOICES)
    // =========================================================================

    /**
     * Actualizar índices de filas de choices
     */
    function updateChoiceIndexes() {
        $('#gdm-choices-tbody .gdm-choice-row').each(function(index) {
            $(this).attr('data-index', index);
            $(this).find('input').each(function() {
                if (this.name) {
                    this.name = this.name.replace(/\[(\d+|__INDEX__)\]/, '[' + index + ']');
                }
            });
        });
    }

    /**
     * Añadir nueva opción de selección
     */
    $('#gdm-add-choice').on('click', function(e) {
        e.preventDefault();
        
        const template = $('#gdm-choice-template').html();
        if (!template) {
            console.error('Plantilla de choice no encontrada');
            return;
        }
        
        const newIndex = $('#gdm-choices-tbody .gdm-choice-row').length;
        const newRowHTML = template.replace(/__INDEX__/g, newIndex);
        const newRow = $(newRowHTML);
        
        $('#gdm-choices-tbody').append(newRow);
        updateChoiceIndexes();
        
        // Scroll suave
        $('html, body').animate({
            scrollTop: newRow.offset().top - 100
        }, 300);
    });

    /**
     * Eliminar opción de selección
     */
    $(document).on('click', '.gdm-remove-choice', function(e) {
        e.preventDefault();
        
        if (!confirm('¿Eliminar esta opción?')) {
            return;
        }
        
        $(this).closest('.gdm-choice-row').fadeOut(300, function() {
            $(this).remove();
            updateChoiceIndexes();
        });
    });

    /**
     * Inicializar sortable para choices
     */
    if ($.fn.sortable) {
        $('#gdm-choices-tbody').sortable({
            handle: '.gdm-sort-handle',
            placeholder: 'gdm-sortable-placeholder',
            opacity: 0.6,
            cursor: 'move',
            axis: 'y',
            update: function() {
                updateChoiceIndexes();
            }
        });
    }

    // =========================================================================
    // VALIDACIÓN DE SLUG
    // =========================================================================

    /**
     * Validar y formatear slug en tiempo real
     */
    $('#gdm_opcion_slug').on('input', function() {
        let slug = $(this).val();
        slug = slug.toLowerCase()
                   .replace(/[^a-z0-9-]/g, '-')
                   .replace(/-+/g, '-')
                   .replace(/^-|-$/g, '');
        $(this).val(slug);
        
        // Actualizar ejemplo de uso
        const ejemplo = slug || 'mi-opcion';
        $(this).closest('td').find('code').text('[opcion ' + ejemplo + ']');
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
        
        if (!title) {
            alert('Por favor ingresa un título para la opción.');
            $('#title').focus();
            e.preventDefault();
            return false;
        }
        
        if (!slug) {
            alert('Por favor ingresa un slug único.');
            $('#gdm_opcion_slug').focus();
            e.preventDefault();
            return false;
        }
        
        if (!label) {
            alert('Por favor ingresa una etiqueta visible.');
            $('#gdm_opcion_label').focus();
            e.preventDefault();
            return false;
        }
        
        // Validar que choices tenga al menos una opción si es tipo selección
        const tipo = $('#gdm_opcion_tipo').val();
        if (['select', 'radio', 'checkbox'].includes(tipo)) {
            const choicesCount = $('#gdm-choices-tbody .gdm-choice-row').length;
            if (choicesCount === 0) {
                alert('Por favor añade al menos una opción de selección.');
                $('#gdm-add-choice').focus();
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });

    // =========================================================================
    // SELECT2 PARA CATEGORÍAS
    // =========================================================================

    /**
     * Inicializar Select2 si está disponible
     */
    if ($.fn.select2) {
        $('#gdm_condicion_categorias').select2({
            placeholder: 'Selecciona categorías (opcional)',
            allowClear: true,
            width: '100%'
        });
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Formatear números de precio
     */
    $('input[name^="gdm_choices"][name$="[precio]"], #gdm_opcion_precio_base').on('blur', function() {
        let val = $(this).val().trim();
        if (val && !isNaN(val)) {
            $(this).val(parseFloat(val).toFixed(2));
        }
    });

    /**
     * Validar extensiones de archivo
     */
    $('#gdm_file_tipos').on('blur', function() {
        let val = $(this).val().trim();
        val = val.toLowerCase()
                 .replace(/\s+/g, '')
                 .replace(/\.+/g, '')
                 .split(',')
                 .filter(ext => ext.length > 0)
                 .join(',');
        $(this).val(val);
    });

    /**
     * Console log para debugging
     */
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log('GDM Opciones Admin Script v1.0.0 cargado correctamente');
        console.log('Tipo actual:', $('#gdm_opcion_tipo').val());
    }
});