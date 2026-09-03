(() => {
    'use strict';

    function targetInput(button) {
        const id = button?.dataset?.target || '';
        return id ? document.getElementById(id) : null;
    }

    function previewFor(input) {
        return input?.closest('td')?.querySelector('[data-vdm-media-preview]') || null;
    }

    document.addEventListener('click', event => {
        const select = event.target.closest?.('.vdm-site-media-select');
        if (select) {
            const input = targetInput(select);
            if (!input || !window.wp || !wp.media) return;

            const frame = wp.media({
                title: 'Vælg billede',
                button: {text: 'Brug billede'},
                library: {type: 'image'},
                multiple: false
            });
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first()?.toJSON();
                if (!attachment?.id) return;
                input.value = String(attachment.id);
                const preview = previewFor(input);
                if (!preview) return;
                const source = attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url || '';
                preview.innerHTML = source
                    ? '<img src="' + String(source).replace(/"/g, '&quot;') + '" alt="" style="display:block;max-width:220px;max-height:120px;width:auto;height:auto;border:1px solid #dcdcde;background:#fff;padding:4px">'
                    : '<span class="description">Billedet er valgt.</span>';
            });
            frame.open();
            return;
        }

        const clear = event.target.closest?.('.vdm-site-media-clear');
        if (clear) {
            const input = targetInput(clear);
            if (!input) return;
            input.value = '0';
            const preview = previewFor(input);
            if (preview) preview.innerHTML = '<span class="description">Intet billede valgt.</span>';
        }
    });
})();
