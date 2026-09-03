(() => {
    'use strict';

    const table = document.querySelector('#vdm-vehicle-specs tbody');
    const addButton = document.getElementById('vdm-add-vehicle-spec');
    const template = document.getElementById('vdm-vehicle-spec-template');
    if (!table || !addButton || !template) return;

    let nextIndex = table.querySelectorAll('.vdm-vehicle-spec-row').length;

    function bindRow(row) {
        row.querySelector('.vdm-remove-vehicle-spec')?.addEventListener('click', () => {
            const rows = table.querySelectorAll('.vdm-vehicle-spec-row');
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach(input => { input.value = ''; });
                return;
            }
            row.remove();
        });
    }

    table.querySelectorAll('.vdm-vehicle-spec-row').forEach(bindRow);

    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.vdm-vehicle-spec-row');
        if (!row) return;
        row.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace('[999999]', '[' + nextIndex + ']');
        });
        nextIndex += 1;
        bindRow(row);
        table.append(row);
        row.querySelector('input')?.focus();
    });
})();
