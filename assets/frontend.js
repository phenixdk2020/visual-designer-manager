(() => {
    'use strict';

    function closeNavigation(nav) {
        if (!nav) return;
        nav.classList.remove('is-open');
        const toggle = nav.querySelector('[data-vdm-navigation-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', event => {
        const toggle = event.target.closest?.('[data-vdm-navigation-toggle]');
        if (toggle) {
            const nav = toggle.closest('.vdm-navigation');
            if (!nav) return;
            const open = !nav.classList.contains('is-open');
            document.querySelectorAll('.vdm-navigation.is-open').forEach(item => {
                if (item !== nav) closeNavigation(item);
            });
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        document.querySelectorAll('.vdm-navigation.is-open').forEach(nav => {
            if (!nav.contains(event.target)) closeNavigation(nav);
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.vdm-navigation.is-open').forEach(nav => {
            const toggle = nav.querySelector('[data-vdm-navigation-toggle]');
            closeNavigation(nav);
            toggle?.focus();
        });
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 783px)').matches) {
            document.querySelectorAll('.vdm-navigation.is-open').forEach(closeNavigation);
        }
    });
})();
