/**
 * Quick Edit para Reglas
 * Compatible con WordPress 6.8.3
 * 
 * @package ProductConditionalContent
 * @since 5.0.2
 */

jQuery(document).ready(function($) {
    
    // Copiar datos al Quick Edit
    const $wp_inline_edit = inlineEditPost.edit;
    
    inlineEditPost.edit = function(id) {
        // Llamar función original
        $wp_inline_edit.apply(this, arguments);
        
        const post_id = 0;
        if (typeof(id) === 'object') {
            post_id = parseInt(this.getId(id));
        }
        
        if (post_id > 0) {
            // Obtener datos vía AJAX
            $.ajax({
                url: gdmQuickEdit.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gdm_get_regla_data',
                    nonce: gdmQuickEdit.nonce,
                    post_id: post_id
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        
                        // Setear estado
                        if (data.status) {
                            let status = data.status;
                            // Mapear 'publish' a 'habilitada'
                            if (status === 'publish') {
                                status = 'habilitada';
                            }
                            $('select[name="gdm_regla_status"]').val(status);
                        }
                        
                        // Setear fecha inicio
                        if (data.fecha_inicio) {
                            const fechaInicio = data.fecha_inicio.replace(' ', 'T').substring(0, 16);
                            $('input[name="gdm_fecha_inicio"]').val(fechaInicio);
                        }
                        
                        // Setear habilitar fecha fin
                        if (data.habilitar_fin === '1') {
                            $('input[name="gdm_habilitar_fecha_fin"]').prop('checked', true);
                        }
                        
                        // Setear fecha fin
                        if (data.fecha_fin) {
                            const fechaFin = data.fecha_fin.replace(' ', 'T').substring(0, 16);
                            $('input[name="gdm_fecha_fin"]').val(fechaFin);
                        }
                    }
                }
            });
        }
    };
    
    // Ocultar campos de visibilidad
    $('.inline-edit-row .inline-edit-password-input, .inline-edit-row .inline-edit-private').hide();
});