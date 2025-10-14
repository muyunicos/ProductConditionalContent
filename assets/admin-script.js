jQuery(document).ready(function($) {
    'use strict';

    function handleRepeaterRowVisibility(row) {
        let condType = row.find('.gdm-cond-type').val();
        let keyCell = row.find('.gdm-cond-key-cell'); 
        let valueCell = row.find('.gdm-cond-value-cell'); 
        let keyField = keyCell.find('.gdm-cond-key');

        if (condType === 'tag') {
            keyCell.show();
            keyField.attr('placeholder', 'slug-del-tag');
            valueCell.hide(); 
        } else if (condType === 'meta') {
            keyCell.show();
            keyField.attr('placeholder', 'meta_key');
            valueCell.show();
        } else { 
            keyCell.hide();
            valueCell.hide();
        }
    }

    function updateAdminUI() {
        $('#gdm_category_list_wrapper').toggle(!$('#gdm_todas_categorias').is(':checked'));
        $('#gdm_tag_list_wrapper').toggle(!$('#gdm_cualquier_tag').is(':checked'));

        $('#gdm-repeater-tbody .gdm-repeater-row').each(function() {
            handleRepeaterRowVisibility($(this));
        });
    }
    
    function updateRepeaterIndexes() {
        $('#gdm-repeater-tbody .gdm-repeater-row').each(function(index) {
            $(this).find('input, select, textarea').each(function() {
                if (this.name) {
                    this.name = this.name.replace(/\[(\d+|__INDEX__)\]/, '[' + index + ']');
                }
            });
        });
    }

    $('#gdm-add-repeater-row').on('click', function() {
        let newIndex = $('#gdm-repeater-tbody .gdm-repeater-row').length;
        let template = $('#gdm-repeater-template').html().replace(/__INDEX__/g, newIndex);
        let newRow = $(template);
        $('#gdm-repeater-tbody').append(newRow);
        handleRepeaterRowVisibility(newRow);
        updateRepeaterIndexes();
    });

    $('#gdm-repeater-tbody').on('click', '.gdm-remove-repeater-row', function(e) {
        e.preventDefault();
        $(this).closest('.gdm-repeater-row').remove();
        updateRepeaterIndexes();
    });

    $('#gdm-repeater-tbody').sortable({
        handle: '.sort-handle',
        placeholder: 'gdm-sortable-placeholder',
        forcePlaceholderSize: true,
        update: function() {
            updateRepeaterIndexes();
        }
    });

    $('#gdm_todas_categorias, #gdm_cualquier_tag').on('change', updateAdminUI);

    $('#gdm-repeater-tbody').on('change', '.gdm-cond-type', function() {
        let row = $(this).closest('.gdm-repeater-row');
        handleRepeaterRowVisibility(row);
    });

    updateAdminUI();
});