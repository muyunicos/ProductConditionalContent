jQuery(function($){
    var fieldsConfig = $('#gdm-product-fields').data('fields');
     Aquí puedes programar la lógica condicional según 'conditional'
     Ejemplo simple
    $('[name=gdm_option_color]').on('change', function() {
        var color = $(this).val();
         Si el color es 'rojo', mostrar el campo de grabado
        if(color === 'rojo') {
            $('.gdm-field[data-conditional=engraving]').show();
        } else {
            $('.gdm-field[data-conditional=engraving]').hide();
        }
    });
});