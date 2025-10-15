/**
 * JS Frontend para Campos Personalizados (solo se carga en productos con campos personalizados)
 * - Muestra/oculta campos según condiciones (implementación simple, extender según reglas)
 * - Valida campos requeridos antes de añadir al carrito
 */
jQuery(function($){
    "use strict";
    let fields = window.gdmFieldsFrontend && window.gdmFieldsFrontend.fields ? window.gdmFieldsFrontend.fields : [];

    function checkConditions() {
        // Implementa lógica de condiciones aquí (ejemplo básico)
        $('.gdm-field').each(function(){
            const $field = $(this);
            const conditional = $field.data('conditional');
            if (!conditional || !conditional.show_if) {
                $field.show();
                return;
            }
            let show = true;
            $.each(conditional.show_if, function(key, value){
                const $dep = $('[name="gdm_'+key+'"]');
                if ($dep.length && $dep.val() != value) show = false;
            });
            $field.toggle(show);
        });
    }

    // Chequear condiciones al cargar y al cambiar campos dependientes
    checkConditions();
    $(document).on('change', '.gdm-field input, .gdm-field select, .gdm-field textarea', checkConditions);

    // Validar campos requeridos antes de añadir al carrito
    $('form.cart').on('submit', function(e){
        let valid = true;
        $('.gdm-field [required]').each(function(){
            if ($(this).is(':checkbox')) {
                if (!$(this).is(':checked')) valid = false;
            } else if (!$(this).val()) {
                valid = false;
            }
        });
        if (!valid) {
            alert('Por favor, completa todos los campos requeridos.');
            e.preventDefault();
        }
    });
});
