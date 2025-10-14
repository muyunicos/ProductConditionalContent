/**
 * JS para la administración de Reglas de Contenido
 * - Renderiza la tabla de reglas, permite agregar/editar/eliminar (con modal)
 * - Soporta selección múltiple y eliminación en lote
 * - Guarda los cambios por AJAX
 * - Prepara el modal para edición avanzada de reglas/variantes
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

    // Seleccionar/deseleccionar todos
    $('#gdm-select-all-rules').on('change', function(){
        $tbody.find('.gdm-rule-row-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
    });

    // Actualiza estado del botón eliminar
    $tbody.on('change', '.gdm-rule-row-checkbox', function(){
        const selected = $tbody.find('.gdm-rule-row-checkbox:checked').length;
        $('#gdm-delete-selected-rules').prop('disabled', selected === 0);
    });

    // Añadir regla
    $('#gdm-add-rule').on('click', function(){
        rules.push({id: '', name: '', priority: 10, conditions: '', enabled: true});
        renderRules();
    });

    // Eliminar seleccionados
    $('#gdm-delete-selected-rules').on('click', function(){
        rules = rules.filter(function(r, idx){
            return !$tbody.find('tr').eq(idx).find('.gdm-rule-row-checkbox').is(':checked');
        });
        renderRules();
        $('#gdm-select-all-rules').prop('checked', false);
        $('#gdm-delete-selected-rules').prop('disabled', true);
    });

    // Modal de edición avanzada (básico, personaliza según tus necesidades)
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

    // Guardar cambios del modal
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

    // Cancelar modal
    $(document).on('click', '#gdm-modal-cancel', function(){
        $('#gdm-rule-modal').hide().empty();
        editingIdx = null;
    });

    // Guardar reglas
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