(() => {
    'use strict';

    const config = window.VDMDesignerConfig || {};
    const canvas = document.getElementById('vdm-canvas');
    const inspector = document.getElementById('vdm-inspector');
    const saveButton = document.getElementById('vdm-save');
    const saveStatus = document.getElementById('vdm-save-status');
    if (!canvas || !inspector || !saveButton || !config.pageId) return;

    const order = ['desktop', 'laptop', 'tablet', 'mobile'];
    let documentState = config.document || {schemaVersion: 2, nodes: [], settings: {rowPixelSize: 8}};
    let selectedId = null;
    let breakpoint = 'desktop';
    let dirty = false;
    let renderTimer = null;

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'vdm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function defaults(type) {
        const geometry = {
            section: {x: 0, y: nextRootY(), w: 12, h: 36},
            container: {x: 0, y: 0, w: 12, h: 24},
            text: {x: 0, y: 0, w: 6, h: 6},
            image: {x: 0, y: 0, w: 6, h: 18},
            button: {x: 0, y: 0, w: 3, h: 6},
            spacer: {x: 0, y: 0, w: 12, h: 4},
            divider: {x: 0, y: 0, w: 12, h: 2}
        }[type];
        const props = {
            section: {background: '#ffffff', padding: 0},
            container: {background: 'transparent', padding: 16},
            text: {content: '<p>Tekst</p>', color: '#222222', fontSize: 18},
            image: {attachmentId: 0, alt: '', objectFit: 'cover'},
            button: {label: 'Knap', url: '#', background: '#2f4858', color: '#ffffff', radius: 4},
            spacer: {},
            divider: {color: '#d0d0d0', thickness: 1}
        }[type] || {};
        return {geometry, props};
    }

    function nextRootY() {
        return documentState.nodes
            .filter(node => !node.parentId && node.type === 'section')
            .reduce((max, node) => {
                const g = effectiveGeometry(node, 'desktop');
                return Math.max(max, g.y + g.h + 2);
            }, 0);
    }

    function nodeById(id) {
        return documentState.nodes.find(node => node.id === id) || null;
    }

    function effectiveGeometry(node, target) {
        let last = node.responsive?.desktop || {x: 0, y: 0, w: 12, h: 4};
        for (const key of order) {
            if (node.responsive && node.responsive[key]) last = node.responsive[key];
            if (key === target) break;
        }
        return {...last};
    }

    function ensureExplicitGeometry(node, target) {
        node.responsive = node.responsive || {};
        if (!node.responsive[target]) node.responsive[target] = effectiveGeometry(node, target);
        return node.responsive[target];
    }

    function selectedParentFor(type) {
        if (type === 'section') return null;
        const selected = nodeById(selectedId);
        if (selected && ['section', 'container'].includes(selected.type)) return selected.id;
        if (selected && selected.parentId) {
            const parent = nodeById(selected.parentId);
            if (parent && ['section', 'container'].includes(parent.type)) return parent.id;
        }
        const firstSection = documentState.nodes.find(node => node.type === 'section' && !node.parentId);
        return firstSection ? firstSection.id : null;
    }

    function addNode(type) {
        if (type !== 'section' && !documentState.nodes.some(node => node.type === 'section' && !node.parentId)) {
            addNode('section');
        }
        const base = defaults(type);
        const parentId = selectedParentFor(type);
        const node = {
            id: uuid(),
            type,
            parentId,
            order: documentState.nodes.length,
            props: base.props,
            responsive: {desktop: base.geometry}
        };
        documentState.nodes.push(node);
        selectedId = node.id;
        markDirty();
        scheduleRender();
        renderInspector();
    }

    function deleteSelected() {
        if (!selectedId) return;
        const ids = new Set([selectedId]);
        let changed = true;
        while (changed) {
            changed = false;
            for (const node of documentState.nodes) {
                if (node.parentId && ids.has(node.parentId) && !ids.has(node.id)) {
                    ids.add(node.id);
                    changed = true;
                }
            }
        }
        documentState.nodes = documentState.nodes.filter(node => !ids.has(node.id));
        selectedId = null;
        markDirty();
        scheduleRender();
        renderInspector();
    }

    function markDirty() {
        dirty = true;
        saveStatus.textContent = 'Ikke gemt';
    }

    function scheduleRender() {
        window.clearTimeout(renderTimer);
        renderTimer = window.setTimeout(renderPreview, 80);
    }

    async function renderPreview() {
        try {
            const response = await fetch(config.restBase + '/render', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce},
                body: JSON.stringify({document: documentState})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Preview kunne ikke renderes.');
            documentState = data.document;
            canvas.innerHTML = data.html || '<div class="vdm-empty">Tilføj en sektion.</div>';
            canvas.dataset.vdmBreakpoint = breakpoint;
            bindCanvas();
        } catch (error) {
            canvas.innerHTML = '<div class="notice notice-error"><p>' + escapeHtml(error.message || String(error)) + '</p></div>';
        }
    }

    function bindCanvas() {
        canvas.querySelectorAll('[data-vdm-node-id]').forEach(element => {
            if (element.dataset.vdmNodeId === selectedId) element.classList.add('is-selected');
            element.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                selectedId = element.dataset.vdmNodeId;
                renderInspector();
                bindCanvasSelectionOnly();
            });
        });
        canvas.addEventListener('click', event => {
            if (event.target === canvas) {
                selectedId = null;
                renderInspector();
                bindCanvasSelectionOnly();
            }
        }, {once: true});
    }

    function bindCanvasSelectionOnly() {
        canvas.querySelectorAll('[data-vdm-node-id]').forEach(element => {
            element.classList.toggle('is-selected', element.dataset.vdmNodeId === selectedId);
        });
    }

    function field(label, control) {
        const wrapper = document.createElement('label');
        wrapper.className = 'vdm-inspector-field';
        const title = document.createElement('span');
        title.textContent = label;
        wrapper.append(title, control);
        return wrapper;
    }

    function numberInput(value, min, max, callback) {
        const input = document.createElement('input');
        input.type = 'number';
        input.min = String(min);
        input.max = String(max);
        input.value = String(value);
        input.addEventListener('input', () => callback(Number.parseInt(input.value || '0', 10)));
        return input;
    }

    function textInput(value, callback) {
        const input = document.createElement('input');
        input.type = 'text';
        input.value = value ?? '';
        input.addEventListener('input', () => callback(input.value));
        return input;
    }

    function colorInput(value, callback) {
        const input = document.createElement('input');
        input.type = 'color';
        input.value = /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#ffffff';
        input.addEventListener('input', () => callback(input.value));
        return input;
    }

    function commitMutation(callback) {
        callback();
        markDirty();
        scheduleRender();
    }

    function renderInspector() {
        inspector.innerHTML = '';
        const node = nodeById(selectedId);
        if (!node) {
            inspector.innerHTML = '<p>Vælg et element.</p>';
            return;
        }

        const heading = document.createElement('p');
        heading.innerHTML = '<strong>' + escapeHtml(node.type) + '</strong><br><small>' + escapeHtml(breakpoint) + '</small>';
        inspector.append(heading);

        const geometry = ensureExplicitGeometry(node, breakpoint);
        const grid = document.createElement('div');
        grid.className = 'vdm-geometry-grid';
        grid.append(
            field('X', numberInput(geometry.x, 0, 11, value => commitMutation(() => { geometry.x = Math.max(0, Math.min(11, value)); geometry.w = Math.min(geometry.w, 12 - geometry.x); }))),
            field('Y', numberInput(geometry.y, 0, 2000, value => commitMutation(() => { geometry.y = Math.max(0, value); }))),
            field('Bredde', numberInput(geometry.w, 1, 12, value => commitMutation(() => { geometry.w = Math.max(1, Math.min(12 - geometry.x, value)); }))),
            field('Højde', numberInput(geometry.h, 1, 2000, value => commitMutation(() => { geometry.h = Math.max(1, value); })))
        );
        inspector.append(grid);

        if (['section', 'container'].includes(node.type)) {
            inspector.append(field('Baggrund', colorInput(node.props.background === 'transparent' ? '#ffffff' : node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
        }

        if (node.type === 'text') {
            const textarea = document.createElement('textarea');
            textarea.rows = 7;
            textarea.value = node.props.content || '';
            textarea.addEventListener('input', () => commitMutation(() => { node.props.content = textarea.value; }));
            inspector.append(field('Indhold (HTML)', textarea));
            inspector.append(field('Tekstfarve', colorInput(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 18, 8, 120, value => commitMutation(() => { node.props.fontSize = value; }))));
        }

        if (node.type === 'image') {
            inspector.append(field('Medie-ID', numberInput(node.props.attachmentId || 0, 0, 999999999, value => commitMutation(() => { node.props.attachmentId = value; }))));
            inspector.append(field('Alt-tekst', textInput(node.props.alt || '', value => commitMutation(() => { node.props.alt = value; }))));
            const select = document.createElement('select');
            ['cover', 'contain'].forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                option.selected = node.props.objectFit === value;
                select.append(option);
            });
            select.addEventListener('change', () => commitMutation(() => { node.props.objectFit = select.value; }));
            inspector.append(field('Billedtilpasning', select));
        }

        if (node.type === 'button') {
            inspector.append(field('Tekst', textInput(node.props.label || '', value => commitMutation(() => { node.props.label = value; }))));
            inspector.append(field('Link', textInput(node.props.url || '#', value => commitMutation(() => { node.props.url = value; }))));
            inspector.append(field('Baggrund', colorInput(node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Tekstfarve', colorInput(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
        }

        if (node.type === 'divider') {
            inspector.append(field('Farve', colorInput(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Tykkelse', numberInput(node.props.thickness || 1, 1, 20, value => commitMutation(() => { node.props.thickness = value; }))));
        }

        const actions = document.createElement('div');
        actions.className = 'vdm-inspector-actions';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button vdm-danger';
        remove.textContent = 'Slet element';
        remove.addEventListener('click', deleteSelected);
        actions.append(remove);
        inspector.append(actions);
    }

    async function save() {
        saveButton.disabled = true;
        saveStatus.textContent = 'Gemmer…';
        try {
            const response = await fetch(config.restBase + '/layouts/' + config.pageId, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce},
                body: JSON.stringify({document: documentState})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Layout kunne ikke gemmes.');
            documentState = data.document;
            config.version = data.version;
            dirty = false;
            saveStatus.textContent = 'Gemt · version ' + data.version;
            renderInspector();
            scheduleRender();
        } catch (error) {
            saveStatus.textContent = 'Fejl: ' + (error.message || String(error));
        } finally {
            saveButton.disabled = false;
        }
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    document.querySelectorAll('.vdm-palette-item').forEach(button => {
        button.addEventListener('click', () => addNode(button.dataset.nodeType));
    });

    document.querySelectorAll('.vdm-breakpoint').forEach(button => {
        button.addEventListener('click', () => {
            breakpoint = button.dataset.breakpoint || 'desktop';
            document.querySelectorAll('.vdm-breakpoint').forEach(item => item.classList.toggle('is-active', item === button));
            canvas.dataset.vdmBreakpoint = breakpoint;
            renderInspector();
        });
    });

    saveButton.addEventListener('click', save);
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    renderInspector();
    renderPreview();
})();
