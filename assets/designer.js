(() => {
    'use strict';

    const config = window.VDMDesignerConfig || {};
    const canvas = document.getElementById('vdm-canvas');
    const inspector = document.getElementById('vdm-inspector');
    const saveButton = document.getElementById('vdm-save');
    const saveStatus = document.getElementById('vdm-save-status');
    const undoButton = document.getElementById('vdm-undo');
    const redoButton = document.getElementById('vdm-redo');
    if (!canvas || !inspector || !saveButton || !config.pageId) return;

    const order = ['desktop', 'laptop', 'tablet', 'mobile'];
    const prefixes = {desktop: 'd', laptop: 'l', tablet: 't', mobile: 'm'};
    const HISTORY_LIMIT = 80;
    const DEFAULT_ROW_PX = 8;

    let documentState = config.document || {schemaVersion: 2, nodes: [], settings: {rowPixelSize: DEFAULT_ROW_PX}};
    let selectedId = null;
    let breakpoint = 'desktop';
    let savedSnapshot = serialize(documentState);
    let dirty = false;
    let renderTimer = null;
    let undoStack = [];
    let redoStack = [];
    let interaction = null;
    let suppressClickUntil = 0;

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'vdm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function serialize(value = documentState) {
        return JSON.stringify(value);
    }

    function rowPixelSize() {
        const value = Number.parseInt(documentState?.settings?.rowPixelSize || DEFAULT_ROW_PX, 10);
        return Number.isFinite(value) && value > 0 ? value : DEFAULT_ROW_PX;
    }

    function defaultHeight(type) {
        return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2}[type] || 4;
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
            section: {background: '#ffffff', padding: 0, autoHeight: true, minHeightRows: 36},
            container: {background: 'transparent', padding: 16, autoHeight: true, minHeightRows: 24},
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
        let last = node.responsive?.desktop || {x: 0, y: 0, w: 12, h: defaultHeight(node.type)};
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

    function childrenOf(parentId) {
        return documentState.nodes.filter(node => node.parentId === parentId);
    }

    function depth(node) {
        let value = 0;
        let current = node;
        const seen = new Set();
        while (current && current.parentId && !seen.has(current.parentId)) {
            seen.add(current.parentId);
            current = nodeById(current.parentId);
            value += 1;
        }
        return value;
    }

    function applyAutoHeight(target = breakpoint) {
        const containers = documentState.nodes
            .filter(node => ['section', 'container'].includes(node.type))
            .sort((a, b) => depth(b) - depth(a));

        for (const node of containers) {
            if (node.props?.autoHeight === false) continue;

            const geometry = ensureExplicitGeometry(node, target);
            const padding = Math.max(0, Number.parseInt(node.props?.padding || 0, 10) || 0);
            const paddingRows = Math.ceil((padding * 2) / rowPixelSize());
            const minimum = Math.max(
                1,
                Number.parseInt(node.props?.minHeightRows || defaultHeight(node.type), 10) || defaultHeight(node.type)
            );
            const contentBottom = childrenOf(node.id).reduce((max, child) => {
                const childGeometry = effectiveGeometry(child, target);
                return Math.max(max, childGeometry.y + childGeometry.h);
            }, 0);
            geometry.h = Math.max(minimum, contentBottom + paddingRows);
        }
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

    function pushUndo(snapshot) {
        if (!snapshot || snapshot === serialize()) return;
        if (undoStack[undoStack.length - 1] === snapshot) return;
        undoStack.push(snapshot);
        if (undoStack.length > HISTORY_LIMIT) undoStack.shift();
        redoStack = [];
        updateHistoryButtons();
    }

    function updateHistoryButtons() {
        if (undoButton) undoButton.disabled = undoStack.length === 0;
        if (redoButton) redoButton.disabled = redoStack.length === 0;
    }

    function updateDirtyState() {
        dirty = serialize() !== savedSnapshot;
        saveStatus.textContent = dirty ? 'Ikke gemt' : 'Gemt · version ' + (config.version || 0);
    }

    function afterMutation(before, {render = true} = {}) {
        applyAutoHeight(breakpoint);
        const after = serialize();
        if (after === before) return false;
        pushUndo(before);
        updateDirtyState();
        if (render) scheduleRender();
        renderInspector();
        return true;
    }

    function commitMutation(callback, options = {}) {
        const before = serialize();
        callback();
        afterMutation(before, options);
    }

    function addNode(type) {
        if (type !== 'section' && !documentState.nodes.some(node => node.type === 'section' && !node.parentId)) {
            addNode('section');
        }
        const before = serialize();
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
        afterMutation(before);
    }

    function deleteSelected() {
        if (!selectedId) return;
        const before = serialize();
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
        afterMutation(before);
    }

    function restoreSnapshot(snapshot, targetStack) {
        if (!snapshot) return;
        const current = serialize();
        targetStack.push(current);
        if (targetStack.length > HISTORY_LIMIT) targetStack.shift();
        documentState = JSON.parse(snapshot);
        if (selectedId && !nodeById(selectedId)) selectedId = null;
        applyAutoHeight(breakpoint);
        updateDirtyState();
        updateHistoryButtons();
        renderInspector();
        scheduleRender();
    }

    function undo() {
        const snapshot = undoStack.pop();
        if (!snapshot) return;
        restoreSnapshot(snapshot, redoStack);
    }

    function redo() {
        const snapshot = redoStack.pop();
        if (!snapshot) return;
        restoreSnapshot(snapshot, undoStack);
    }

    function scheduleRender() {
        window.clearTimeout(renderTimer);
        renderTimer = window.setTimeout(renderPreview, 70);
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
            updateDirtyState();
        } catch (error) {
            canvas.innerHTML = '<div class="notice notice-error"><p>' + escapeHtml(error.message || String(error)) + '</p></div>';
        }
    }

    function nodeElement(id) {
        if (!id) return null;
        return canvas.querySelector('[data-vdm-node-id="' + cssEscape(id) + '"]');
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function activePrefix() {
        return prefixes[breakpoint] || 'd';
    }

    function applyLiveGeometry(element, geometry) {
        if (!element) return;
        const prefix = activePrefix();
        element.style.setProperty('--vdm-' + prefix + '-x', String(geometry.x));
        element.style.setProperty('--vdm-' + prefix + '-y', String(geometry.y));
        element.style.setProperty('--vdm-' + prefix + '-w', String(geometry.w));
        element.style.setProperty('--vdm-' + prefix + '-h', String(geometry.h));
    }

    function interactionSurface(node) {
        if (!node.parentId) return canvas.querySelector('.vdm-layout');
        const parent = nodeElement(node.parentId);
        return parent ? parent.querySelector(':scope > .vdm-node-surface') : null;
    }

    function metricsFor(surface) {
        const rect = surface?.getBoundingClientRect();
        if (!rect || rect.width <= 0) return null;
        return {
            rect,
            columnWidth: rect.width / 12,
            rowHeight: rowPixelSize()
        };
    }

    function startDrag(event, element, node) {
        if (event.button !== 0 || event.target.closest('.vdm-resize-handle')) return;
        if (event.target.closest('a,button,input,textarea,select')) return;

        selectedId = node.id;
        bindCanvasSelectionOnly();
        renderInspector();

        const surface = interactionSurface(node);
        const metrics = metricsFor(surface);
        if (!metrics) return;

        const geometry = ensureExplicitGeometry(node, breakpoint);
        interaction = {
            mode: 'drag',
            pointerId: event.pointerId,
            nodeId: node.id,
            before: serialize(),
            startClientX: event.clientX,
            startClientY: event.clientY,
            startGeometry: {...geometry},
            metrics,
            moved: false
        };
        element.classList.add('is-interacting');
        document.body.classList.add('vdm-is-dragging');
        element.setPointerCapture?.(event.pointerId);
        event.preventDefault();
        event.stopPropagation();
    }

    function startResize(event, element, node, direction) {
        if (event.button !== 0) return;
        const surface = interactionSurface(node);
        const metrics = metricsFor(surface);
        if (!metrics) return;

        selectedId = node.id;
        const geometry = ensureExplicitGeometry(node, breakpoint);
        interaction = {
            mode: 'resize',
            direction,
            pointerId: event.pointerId,
            nodeId: node.id,
            before: serialize(),
            startClientX: event.clientX,
            startClientY: event.clientY,
            startGeometry: {...geometry},
            metrics,
            moved: false
        };
        element.classList.add('is-interacting');
        document.body.classList.add('vdm-is-resizing');
        element.setPointerCapture?.(event.pointerId);
        event.preventDefault();
        event.stopPropagation();
    }

    function handlePointerMove(event) {
        if (!interaction || event.pointerId !== interaction.pointerId) return;
        const node = nodeById(interaction.nodeId);
        const element = nodeElement(interaction.nodeId);
        if (!node || !element) return;

        const geometry = ensureExplicitGeometry(node, breakpoint);
        const deltaColumns = Math.round((event.clientX - interaction.startClientX) / interaction.metrics.columnWidth);
        const deltaRows = Math.round((event.clientY - interaction.startClientY) / interaction.metrics.rowHeight);

        if (interaction.mode === 'drag') {
            const maxX = Math.max(0, 12 - interaction.startGeometry.w);
            geometry.x = node.type === 'section' && !node.parentId
                ? 0
                : Math.max(0, Math.min(maxX, interaction.startGeometry.x + deltaColumns));
            geometry.y = Math.max(0, interaction.startGeometry.y + deltaRows);
        } else {
            const direction = interaction.direction || 'se';
            if (direction.includes('e')) {
                geometry.w = Math.max(1, Math.min(12 - geometry.x, interaction.startGeometry.w + deltaColumns));
            }
            if (direction.includes('s')) {
                geometry.h = Math.max(1, Math.min(2000, interaction.startGeometry.h + deltaRows));
                if (['section', 'container'].includes(node.type)) {
                    node.props.minHeightRows = geometry.h;
                }
            }
        }

        interaction.moved = interaction.moved
            || geometry.x !== interaction.startGeometry.x
            || geometry.y !== interaction.startGeometry.y
            || geometry.w !== interaction.startGeometry.w
            || geometry.h !== interaction.startGeometry.h;

        applyLiveGeometry(element, geometry);
        updateInspectorGeometryValues(geometry);
        event.preventDefault();
    }

    function finishInteraction(event) {
        if (!interaction || (event && event.pointerId !== interaction.pointerId)) return;
        const current = interaction;
        const element = nodeElement(current.nodeId);
        if (element) element.classList.remove('is-interacting');
        document.body.classList.remove('vdm-is-dragging', 'vdm-is-resizing');
        interaction = null;

        if (!current.moved) return;

        suppressClickUntil = Date.now() + 250;
        applyAutoHeight(breakpoint);
        const after = serialize();
        if (after !== current.before) {
            pushUndo(current.before);
            updateDirtyState();
            renderInspector();
            scheduleRender();
        }
    }

    function addResizeHandles(element, node) {
        if (element.dataset.vdmHandles === '1') return;
        element.dataset.vdmHandles = '1';
        for (const direction of ['e', 's', 'se']) {
            const handle = document.createElement('span');
            handle.className = 'vdm-resize-handle vdm-resize-handle--' + direction;
            handle.dataset.direction = direction;
            handle.setAttribute('aria-hidden', 'true');
            handle.addEventListener('pointerdown', event => startResize(event, element, node, direction));
            element.append(handle);
        }
    }

    function bindCanvas() {
        canvas.querySelectorAll('[data-vdm-node-id]').forEach(element => {
            const node = nodeById(element.dataset.vdmNodeId);
            if (!node) return;

            element.classList.toggle('is-selected', element.dataset.vdmNodeId === selectedId);
            element.addEventListener('pointerdown', event => startDrag(event, element, node));
            element.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                if (Date.now() < suppressClickUntil) return;
                selectedId = element.dataset.vdmNodeId;
                renderInspector();
                bindCanvasSelectionOnly();
            });

            if (element.dataset.vdmNodeId === selectedId) addResizeHandles(element, node);
        });

        canvas.addEventListener('click', event => {
            if (event.target === canvas || event.target.classList.contains('vdm-layout')) {
                selectedId = null;
                renderInspector();
                bindCanvasSelectionOnly();
            }
        }, {once: true});
    }

    function bindCanvasSelectionOnly() {
        canvas.querySelectorAll('[data-vdm-node-id]').forEach(element => {
            const selected = element.dataset.vdmNodeId === selectedId;
            element.classList.toggle('is-selected', selected);
            element.querySelectorAll(':scope > .vdm-resize-handle').forEach(handle => handle.remove());
            element.dataset.vdmHandles = '0';
            if (selected) {
                const node = nodeById(selectedId);
                if (node) addResizeHandles(element, node);
            }
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

    function numberInput(value, min, max, callback, key = '') {
        const input = document.createElement('input');
        input.type = 'number';
        input.min = String(min);
        input.max = String(max);
        input.value = String(value);
        if (key) input.dataset.geometryKey = key;
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

    function checkboxInput(checked, callback) {
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = Boolean(checked);
        input.addEventListener('change', () => callback(input.checked));
        return input;
    }

    function updateInspectorGeometryValues(geometry) {
        inspector.querySelectorAll('[data-geometry-key]').forEach(input => {
            const key = input.dataset.geometryKey;
            if (Object.prototype.hasOwnProperty.call(geometry, key)) input.value = String(geometry[key]);
        });
    }

    function setGeometryValue(node, geometry, key, value) {
        if (key === 'x') {
            geometry.x = Math.max(0, Math.min(11, value));
            geometry.w = Math.min(geometry.w, 12 - geometry.x);
        } else if (key === 'y') {
            geometry.y = Math.max(0, value);
        } else if (key === 'w') {
            geometry.w = Math.max(1, Math.min(12 - geometry.x, value));
        } else if (key === 'h') {
            geometry.h = Math.max(1, value);
            if (['section', 'container'].includes(node.type)) node.props.minHeightRows = geometry.h;
        }
    }

    function renderInspector() {
        inspector.innerHTML = '';
        const node = nodeById(selectedId);
        if (!node) {
            inspector.innerHTML = '<p>Vælg et element.</p>';
            updateHistoryButtons();
            return;
        }

        const heading = document.createElement('p');
        heading.innerHTML = '<strong>' + escapeHtml(node.type) + '</strong><br><small>' + escapeHtml(breakpoint) + ' · 8 px grid</small>';
        inspector.append(heading);

        const geometry = ensureExplicitGeometry(node, breakpoint);
        const grid = document.createElement('div');
        grid.className = 'vdm-geometry-grid';
        grid.append(
            field('X', numberInput(geometry.x, 0, 11, value => commitMutation(() => setGeometryValue(node, geometry, 'x', value)), 'x')),
            field('Y', numberInput(geometry.y, 0, 2000, value => commitMutation(() => setGeometryValue(node, geometry, 'y', value)), 'y')),
            field('Bredde', numberInput(geometry.w, 1, 12, value => commitMutation(() => setGeometryValue(node, geometry, 'w', value)), 'w')),
            field('Højde', numberInput(geometry.h, 1, 2000, value => commitMutation(() => setGeometryValue(node, geometry, 'h', value)), 'h'))
        );
        inspector.append(grid);

        if (['section', 'container'].includes(node.type)) {
            inspector.append(field('Automatisk højde', checkboxInput(node.props.autoHeight !== false, value => commitMutation(() => {
                node.props.autoHeight = value;
                if (value) node.props.minHeightRows = Math.max(1, geometry.h);
            }))));
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
        updateHistoryButtons();
    }

    function nudgeSelected(dx, dy) {
        const node = nodeById(selectedId);
        if (!node) return;
        commitMutation(() => {
            const geometry = ensureExplicitGeometry(node, breakpoint);
            geometry.x = node.type === 'section' && !node.parentId
                ? 0
                : Math.max(0, Math.min(12 - geometry.w, geometry.x + dx));
            geometry.y = Math.max(0, geometry.y + dy);
        });
    }

    async function save() {
        saveButton.disabled = true;
        saveStatus.textContent = 'Gemmer…';
        try {
            applyAutoHeight(breakpoint);
            const response = await fetch(config.restBase + '/layouts/' + config.pageId, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce},
                body: JSON.stringify({document: documentState})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Layout kunne ikke gemmes.');
            documentState = data.document;
            config.version = data.version;
            savedSnapshot = serialize(documentState);
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

    function isEditableTarget(target) {
        return Boolean(target?.closest?.('input,textarea,select,[contenteditable="true"]'));
    }

    document.querySelectorAll('.vdm-palette-item').forEach(button => {
        button.addEventListener('click', () => addNode(button.dataset.nodeType));
    });

    document.querySelectorAll('.vdm-breakpoint').forEach(button => {
        button.addEventListener('click', () => {
            breakpoint = button.dataset.breakpoint || 'desktop';
            document.querySelectorAll('.vdm-breakpoint').forEach(item => item.classList.toggle('is-active', item === button));
            canvas.dataset.vdmBreakpoint = breakpoint;
            applyAutoHeight(breakpoint);
            renderInspector();
            scheduleRender();
        });
    });

    undoButton?.addEventListener('click', undo);
    redoButton?.addEventListener('click', redo);
    saveButton.addEventListener('click', save);

    window.addEventListener('pointermove', handlePointerMove, {passive: false});
    window.addEventListener('pointerup', finishInteraction);
    window.addEventListener('pointercancel', finishInteraction);

    window.addEventListener('keydown', event => {
        const modifier = event.ctrlKey || event.metaKey;
        if (modifier && event.key.toLowerCase() === 's') {
            event.preventDefault();
            save();
            return;
        }
        if (modifier && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            event.shiftKey ? redo() : undo();
            return;
        }
        if (modifier && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            redo();
            return;
        }
        if (isEditableTarget(event.target)) return;
        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (selectedId) {
                event.preventDefault();
                deleteSelected();
            }
            return;
        }
        const moves = {
            ArrowLeft: [-1, 0],
            ArrowRight: [1, 0],
            ArrowUp: [0, -1],
            ArrowDown: [0, 1]
        };
        if (moves[event.key] && selectedId) {
            event.preventDefault();
            nudgeSelected(moves[event.key][0], moves[event.key][1]);
        }
    });

    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    applyAutoHeight('desktop');
    updateDirtyState();
    updateHistoryButtons();
    renderInspector();
    renderPreview();
})();
