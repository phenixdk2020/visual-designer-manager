(() => {
    'use strict';

    const list = document.getElementById('vdm-gallery-image-list');
    const hidden = document.getElementById('vdm-gallery-image-ids');
    const selectButton = document.getElementById('vdm-gallery-select-images');
    const clearButton = document.getElementById('vdm-gallery-clear-images');
    if (!list || !hidden || !selectButton || !clearButton) return;

    let dragged = null;

    function ids() {
        return Array.from(list.querySelectorAll('[data-attachment-id]'))
            .map(item => Number.parseInt(item.dataset.attachmentId || '0', 10))
            .filter(id => id > 0);
    }

    function sync() {
        hidden.value = JSON.stringify(ids());
    }

    function bind(item) {
        item.querySelector('.vdm-gallery-remove-image')?.addEventListener('click', () => {
            item.remove();
            sync();
        });

        item.addEventListener('dragstart', event => {
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('is-dragging');
            dragged = null;
            sync();
        });
        item.addEventListener('dragover', event => {
            if (!dragged || dragged === item) return;
            event.preventDefault();
            const rect = item.getBoundingClientRect();
            const before = event.clientX < rect.left + rect.width / 2;
            list.insertBefore(dragged, before ? item : item.nextSibling);
        });
    }

    function itemFor(attachment) {
        const item = document.createElement('div');
        item.className = 'vdm-gallery-admin-image';
        item.draggable = true;
        item.dataset.attachmentId = String(attachment.id);

        const image = document.createElement('img');
        const sizes = attachment.sizes || {};
        image.src = sizes.thumbnail?.url || sizes.medium?.url || attachment.url || '';
        image.alt = attachment.alt || '';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link-delete vdm-gallery-remove-image';
        remove.textContent = 'Fjern';

        item.append(image, remove);
        bind(item);
        return item;
    }

    list.querySelectorAll('.vdm-gallery-admin-image').forEach(bind);

    selectButton.addEventListener('click', () => {
        if (!window.wp || !wp.media) {
            window.alert('WordPress Mediebibliotek er ikke tilgængeligt.');
            return;
        }

        const frame = wp.media({
            title: 'Vælg billeder til album',
            button: {text: 'Brug valgte billeder'},
            library: {type: 'image'},
            multiple: 'add'
        });

        frame.on('select', () => {
            const existing = new Set(ids());
            frame.state().get('selection').each(model => {
                const attachment = model.toJSON();
                const id = Number.parseInt(attachment.id || '0', 10);
                if (id <= 0 || existing.has(id)) return;
                existing.add(id);
                list.append(itemFor(attachment));
            });
            sync();
        });
        frame.open();
    });

    clearButton.addEventListener('click', () => {
        list.innerHTML = '';
        sync();
    });

    sync();
})();
