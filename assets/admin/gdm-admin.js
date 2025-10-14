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
/**
 * JS para la administración de Reglas de Contenido (solo admin)
 * - Renderiza la tabla, permite agregar/editar/eliminar reglas
 * - Soporta selección múltiple y eliminación en lote
 * - Guarda los cambios por AJAX
 * - Modal básico de edición de reglas
 */
jQuery(function($){
    "use strict";
    let rules = gdmRulesAdmin.rules || [];
    const $tbody = $('#gdm-rules-table tbody');
    const tmpl = _.template($('#gdm-rule-row-template').html());

    function renderRules() {
        $tbody.empty();
        if (!rules.length) return;
        rules.forEach(function(r, idx){
            $tbody.append(tmpl({
                id: r.id || idx + 1,
                name: r.name || "",
                priority: r.priority || 10,
                conditions: r.conditions || "",
                enabled: r.enabled !== false // default true
            }));
        });
    }
    renderRules();

    // Selección múltiple
    $('#gdm-select-all-rules').on('change', function(){
        $tbody.find('.gdm-rule-row-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
    });
    $tbody.on('change', '.gdm-rule-row-checkbox', function(){
        const selected = $tbody.find('.gdm-rule-row-checkbox:checked').length;
        $('#gdm-delete-selected-rules').prop('disabled', selected === 0);
    });

    // Añadir regla
    $('#gdm-add-rule').on('click', function(){
        rules.push({id: '', name: '', priority: 10, conditions: '', enabled: true});
        renderRules();
    });

    // Eliminar seleccionadas
    $('#gdm-delete-selected-rules').on('click', function(){
        rules = rules.filter(function(r, idx){
            return !$tbody.find('tr').eq(idx).find('.gdm-rule-row-checkbox').is(':checked');
        });
        renderRules();
        $('#gdm-select-all-rules').prop('checked', false);
        $('#gdm-delete-selected-rules').prop('disabled', true);
    });

    // Modal de edición (simple)
    let editingIdx = null;
    $tbody.on('click', '.gdm-edit-rule', function(){
        editingIdx = $(this).closest('tr').index();
        const rule = rules[editingIdx];
        const $modal = $('#gdm-rule-modal');
        $modal.html(`
            <div class="gdm-modal-content">
                <h2>Editar Regla</h2>
                <label>ID: <input type="text" id="gdm-modal-rule-id" value="${rule.id||''}" /></label><br>
                <label>Nombre: <input type="text" id="gdm-modal-rule-name" value="${rule.name||''}" /></label><br>
                <label>Prioridad: <input type="number" id="gdm-modal-rule-priority" value="${rule.priority||10}" /></label><br>
                <label>Condiciones: <input type="text" id="gdm-modal-rule-conditions" value="${rule.conditions||''}" /></label><br>
                <label>Activa: <input type="checkbox" id="gdm-modal-rule-enabled"${rule.enabled!==false?' checked':''} /></label><br>
                <button type="button" class="button button-primary" id="gdm-modal-save-rule">Guardar</button>
                <button type="button" class="button" id="gdm-modal-cancel">Cancelar</button>
            </div>
        `).show();
    });
    $(document).on('click', '#gdm-modal-save-rule', function(){
        if (editingIdx === null) return;
        rules[editingIdx].id = $('#gdm-modal-rule-id').val();
        rules[editingIdx].name = $('#gdm-modal-rule-name').val();
        rules[editingIdx].priority = parseInt($('#gdm-modal-rule-priority').val(), 10) || 10;
        rules[editingIdx].conditions = $('#gdm-modal-rule-conditions').val();
        rules[editingIdx].enabled = $('#gdm-modal-rule-enabled').is(':checked');
        $('#gdm-rule-modal').hide().empty();
        renderRules();
        editingIdx = null;
    });
    $(document).on('click', '#gdm-modal-cancel', function(){
        $('#gdm-rule-modal').hide().empty();
        editingIdx = null;
    });

    // Guardar reglas por AJAX
    $('#gdm-rules-form').on('submit', function(e){
        e.preventDefault();
        $.post(gdmRulesAdmin.ajaxUrl, {
            action: 'gdm_save_rules',
            nonce: gdmRulesAdmin.nonce,
            rules: JSON.stringify(rules)
        }, function(resp){
            if(resp.success){
                $('#gdm-rules-saved').show().delay(2500).fadeOut();
            }
        });
    });
});