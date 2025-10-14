/**
 * JS para la administración de Campos Personalizados (solo admin)
 * - Renderiza la tabla, permite agregar/editar/eliminar campos
 * - Soporta selección múltiple y eliminación en lote
 * - Guarda los cambios por AJAX
 * - Modal básico de edición (personalizar en producción)
 */
jQuery(function($){
    "use strict";
    let fields = gdmFieldsAdmin.fields || [];
    const $tbody = $('#gdm-fields-table tbody');
    const tmpl = _.template($('#gdm-field-row-template').html());

    function renderFields() {
        $tbody.empty();
        if (!fields.length) return;
        fields.forEach(function(f, idx){
            $tbody.append(tmpl({
                id: f.id || idx + 1,
                label: f.label || "",
                type: f.type || "text",
                price: f.price || "",
                required: f.required || false
            }));
        });
    }
    renderFields();

    // Selección múltiple
    $('#gdm-select-all-fields').on('change', function(){
        $tbody.find('.gdm-field-row-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
    });
    $tbody.on('change', '.gdm-field-row-checkbox', function(){
        const selected = $tbody.find('.gdm-field-row-checkbox:checked').length;
        $('#gdm-delete-selected-fields').prop('disabled', selected === 0);
    });

    // Añadir campo
    $('#gdm-add-field').on('click', function(){
        fields.push({id: '', label: '', type: 'text', price: '', required: false});
        renderFields();
    });

    // Eliminar seleccionados
    $('#gdm-delete-selected-fields').on('click', function(){
        fields = fields.filter(function(f, idx){
            return !$tbody.find('tr').eq(idx).find('.gdm-field-row-checkbox').is(':checked');
        });
        renderFields();
        $('#gdm-select-all-fields').prop('checked', false);
        $('#gdm-delete-selected-fields').prop('disabled', true);
    });

    // Modal de edición (simple)
    let editingIdx = null;
    $tbody.on('click', '.gdm-edit-field', function(){
        editingIdx = $(this).closest('tr').index();
        const field = fields[editingIdx];
        const $modal = $('#gdm-field-modal');
        $modal.html(`
            <div class="gdm-modal-content">
                <h2>Editar Campo</h2>
                <label>ID: <input type="text" id="gdm-modal-field-id" value="${field.id||''}" /></label><br>
                <label>Nombre: <input type="text" id="gdm-modal-field-label" value="${field.label||''}" /></label><br>
                <label>Tipo:
                    <select id="gdm-modal-field-type">
                        <option value="text"${field.type==='text'?' selected':''}>Texto</option>
                        <option value="textarea"${field.type==='textarea'?' selected':''}>Área de texto</option>
                        <option value="select"${field.type==='select'?' selected':''}>Select</option>
                        <option value="checkbox"${field.type==='checkbox'?' selected':''}>Checkbox</option>
                        <option value="radio"${field.type==='radio'?' selected':''}>Radio</option>
                    </select>
                </label><br>
                <label>Precio: <input type="text" id="gdm-modal-field-price" value="${field.price||''}" /></label><br>
                <label>Requerido: <input type="checkbox" id="gdm-modal-field-required"${field.required?' checked':''} /></label><br>
                <button type="button" class="button button-primary" id="gdm-modal-save-field">Guardar</button>
                <button type="button" class="button" id="gdm-modal-cancel">Cancelar</button>
            </div>
        `).show();
    });
    $(document).on('click', '#gdm-modal-save-field', function(){
        if (editingIdx === null) return;
        fields[editingIdx].id = $('#gdm-modal-field-id').val();
        fields[editingIdx].label = $('#gdm-modal-field-label').val();
        fields[editingIdx].type = $('#gdm-modal-field-type').val();
        fields[editingIdx].price = $('#gdm-modal-field-price').val();
        fields[editingIdx].required = $('#gdm-modal-field-required').is(':checked');
        $('#gdm-field-modal').hide().empty();
        renderFields();
        editingIdx = null;
    });
    $(document).on('click', '#gdm-modal-cancel', function(){
        $('#gdm-field-modal').hide().empty();
        editingIdx = null;
    });

    // Guardar campos por AJAX
    $('#gdm-fields-form').on('submit', function(e){
        e.preventDefault();
        $.post(gdmFieldsAdmin.ajaxUrl, {
            action: 'gdm_save_fields',
            nonce: gdmFieldsAdmin.nonce,
            fields: JSON.stringify(fields)
        }, function(resp){
            if(resp.success){
                $('#gdm-fields-saved').show().delay(2500).fadeOut();
            }
        });
    });
});