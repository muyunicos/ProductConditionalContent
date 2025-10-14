jQuery(function($){
    /** Cargar campos existentes */
    var fields = <?php echo json_encode(GDM_Fields_Admin::get_fields()); ?>;
    var $tbody = $('#gdm-fields-table tbody');
    var tmpl = _.template($('#gdm-field-row-template').html());

    function renderFields() {
        $tbody.empty();
        if (!fields.length) return;
        fields.forEach(function(f, idx){
            $tbody.append(tmpl({
                label: f.label||"",
                id: f.id||"",
                type: f.type||"text",
                options: (f.options||[]).map(function(opt){ return opt.value+':'+opt.label; }).join(','),
                prices: (f.options||[]).map(function(opt){ return opt.value+':'+(opt.price||0); }).join(','),
                conditional: JSON.stringify(f.conditional||{}),
                required: f.required||false
            }));
        });
    }
    renderFields();

    $('#gdm-add-field').on('click', function(){
        fields.push({label:"",id:"",type:"text",options:[],conditional:{},required:false});
        renderFields();
    });

    $tbody.on('click','.gdm-delete-field',function(){
        var idx = $(this).closest('tr').index();
        fields.splice(idx,1);
        renderFields();
    });

    $('#gdm-fields-form').on('submit', function(e){
        e.preventDefault();
        // Recopilar campos del DOM
        var newFields = [];
        $tbody.find('tr').each(function(){
            var $tr = $(this);
            var type = $tr.find('.gdm-field-type').val();
            var optionsArr = [];
            // Opciones y precios
            if(type==="select" || type==="radio" || type==="checkbox"){
                var opts = $tr.find('.gdm-field-options').val().split(',');
                var prices = $tr.find('.gdm-field-prices').val().split(',');
                opts.forEach(function(optStr,i){
                    var parts = optStr.split(':');
                    if(parts.length<2) return;
                    var val = parts[0], label = parts[1];
                    var price = 0;
                    if(prices[i]){
                        var priceParts = prices[i].split(':');
                        if(priceParts[0]===val) price = parseFloat(priceParts[1]);
                    }
                    optionsArr.push({value:val,label:label,price:price});
                });
            }
            // Parse condicional
            var conditional = {};
            try { conditional = JSON.parse($tr.find('.gdm-field-conditional').val()||"{}"); } catch(e){}
            newFields.push({
                label: $tr.find('.gdm-field-label').val(),
                id: $tr.find('.gdm-field-id').val(),
                type: type,
                options: optionsArr,
                conditional: conditional,
                required: $tr.find('.gdm-field-required').is(':checked')
            });
        });
        // Guardar por AJAX
        $.post(gdmFieldsAdmin.ajaxUrl, {
            action: 'gdm_save_fields',
            nonce: gdmFieldsAdmin.nonce,
            fields: JSON.stringify(newFields)
        }, function(resp){
            if(resp.success){
                $('#gdm-fields-saved').show().delay(2500).fadeOut();
                fields = newFields;
            }
        });
    });
});