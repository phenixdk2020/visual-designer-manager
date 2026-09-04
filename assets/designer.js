(() => {
    'use strict';

    const config = window.VDMDesignerConfig || {};
    const canvas = document.getElementById('vdm-canvas');
    const inspector = document.getElementById('vdm-inspector');
    const saveButton = document.getElementById('vdm-save');
    const saveStatus = document.getElementById('vdm-save-status');
    const undoButton = document.getElementById('vdm-undo');
    const redoButton = document.getElementById('vdm-redo');
    const hasDesignerTarget = Number.parseInt(config.pageId || '0', 10) > 0 || Boolean(config.templateSlot) || Boolean(config.ready);
    if (!canvas || !inspector || !saveButton || !hasDesignerTarget) return;

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
        return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60, 'contact-form': 100, 'membership-form': 128, navigation: 8}[type] || 4;
    }

    function defaults(type) {
        const geometry = {
            section: {x: 0, y: nextRootY(), w: 12, h: 36},
            container: {x: 0, y: 0, w: 12, h: 24},
            text: {x: 0, y: 0, w: 6, h: 6},
            image: {x: 0, y: 0, w: 6, h: 18},
            button: {x: 0, y: 0, w: 3, h: 6},
            spacer: {x: 0, y: 0, w: 12, h: 4},
            divider: {x: 0, y: 0, w: 12, h: 2},
            events: {x: 0, y: 0, w: 12, h: 60},
            vehicles: {x: 0, y: 0, w: 12, h: 60},
            galleries: {x: 0, y: 0, w: 12, h: 60},
            'contact-form': {x: 0, y: 0, w: 12, h: 100},
            'membership-form': {x: 0, y: 0, w: 12, h: 128},
            navigation: {x: 0, y: 0, w: 12, h: 8}
        }[type];
        const props = {
            section: {background: '#ffffff', padding: 0, radius: 0, borderWidth: 0, borderColor: '#d0d0d0', autoHeight: true, minHeightRows: 36},
            container: {background: 'transparent', padding: 16, radius: 0, borderWidth: 0, borderColor: '#d0d0d0', autoHeight: true, minHeightRows: 24},
            text: {content: '<p>Tekst</p>', color: '#222222', fontSize: 18, fontWeight: 400, lineHeight: 1.5, align: 'left', verticalAlign: 'top', background: 'transparent', padding: 0, radius: 0},
            image: {attachmentId: 0, alt: '', objectFit: 'cover', positionX: 'center', positionY: 'center'},
            button: {label: 'Knap', url: '#', target: '_self', align: 'left', background: '#2f4858', color: '#ffffff', radius: 4, paddingX: 18, paddingY: 10, fontSize: 16, fontWeight: 600, borderWidth: 0, borderColor: '#2f4858'},
            spacer: {},
            divider: {color: '#d0d0d0', thickness: 1},
            events: {count: 6, showPast: false, columns: 3, gap: 20, padding: 18, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showImage: true, showSummary: true, showFacts: true},
            vehicles: {count: 12, columns: 3, gap: 20, padding: 18, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showImage: true, showSummary: true, showFacts: true},
            galleries: {count: 12, columns: 3, gap: 20, padding: 16, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showCover: true, showSummary: true},
            'contact-form': {heading: 'Kontakt os', intro: 'Har du spørgsmål, er du velkommen til at kontakte os.', columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send besked', successMessage: 'Tak. Din henvendelse er sendt.', showPhone: true, showSubject: true, showAddress: false, showMessage: true, messageRows: 6, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.'},
            'membership-form': {heading: 'Bliv medlem', intro: 'Udfyld formularen, så kontakter vi dig om medlemskab.', columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send indmeldelse', successMessage: 'Tak. Din indmeldelse er sendt.', showPhone: true, showSubject: false, showAddress: true, showMessage: true, messageRows: 5, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.'},
            navigation: {menuId: 0, orientation: 'horizontal', align: 'left', gap: 24, fontSize: 16, fontWeight: 600, textColor: '#222222', hoverColor: '#2271b1', background: 'transparent', submenuBackground: '#ffffff', submenuTextColor: '#222222', toggleLabel: 'Menu'}
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

    function afterMutation(before, {render = true, inspectorRefresh = true} = {}) {
        applyAutoHeight(breakpoint);
        const after = serialize();
        if (after === before) return false;
        pushUndo(before);
        updateDirtyState();
        if (render) scheduleRender();
        if (inspectorRefresh) {
            renderInspector();
        } else {
            updateHistoryButtons();
        }
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
            const renderUrl = config.renderUrl || (config.restBase + '/render');
            const response = await fetch(renderUrl, {
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

    let activeColorPopover = null;

    function normalizeHex(value, fallback = '#ffffff') {
        const text = String(value || '').trim();
        return /^#[0-9a-f]{6}$/i.test(text) ? text.toLowerCase() : fallback;
    }

    function hexToRgb(hex) {
        const value = normalizeHex(hex).slice(1);
        return {
            r: Number.parseInt(value.slice(0, 2), 16),
            g: Number.parseInt(value.slice(2, 4), 16),
            b: Number.parseInt(value.slice(4, 6), 16)
        };
    }

    function rgbToHex(r, g, b) {
        const part = value => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');
        return '#' + part(r) + part(g) + part(b);
    }

    function rgbToHsv({r, g, b}) {
        const rr = r / 255;
        const gg = g / 255;
        const bb = b / 255;
        const max = Math.max(rr, gg, bb);
        const min = Math.min(rr, gg, bb);
        const delta = max - min;
        let h = 0;
        if (delta !== 0) {
            if (max === rr) h = 60 * (((gg - bb) / delta) % 6);
            else if (max === gg) h = 60 * (((bb - rr) / delta) + 2);
            else h = 60 * (((rr - gg) / delta) + 4);
        }
        if (h < 0) h += 360;
        return {h, s: max === 0 ? 0 : delta / max, v: max};
    }

    function hsvToHex(h, sValue, vValue) {
        const c = vValue * sValue;
        const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
        const m = vValue - c;
        let rgb = [0, 0, 0];
        if (h < 60) rgb = [c, x, 0];
        else if (h < 120) rgb = [x, c, 0];
        else if (h < 180) rgb = [0, c, x];
        else if (h < 240) rgb = [0, x, c];
        else if (h < 300) rgb = [x, 0, c];
        else rgb = [c, 0, x];
        return rgbToHex((rgb[0] + m) * 255, (rgb[1] + m) * 255, (rgb[2] + m) * 255);
    }

    function recentColors() {
        try {
            const value = JSON.parse(window.localStorage.getItem('vdm_recent_colors_v2') || '[]');
            return Array.isArray(value) ? value.filter(color => /^#[0-9a-f]{6}$/i.test(color)).slice(0, 12) : [];
        } catch (error) {
            return [];
        }
    }

    function rememberColor(color) {
        try {
            const value = normalizeHex(color);
            const colors = [value, ...recentColors().filter(item => item !== value)].slice(0, 12);
            window.localStorage.setItem('vdm_recent_colors_v2', JSON.stringify(colors));
        } catch (error) {
            // Browser storage may be unavailable; the picker still works.
        }
    }

    function setColorControl(button, color) {
        const value = normalizeHex(color);
        button.dataset.color = value;
        const swatch = button.querySelector('.vdm-color-trigger-swatch');
        const text = button.querySelector('.vdm-color-trigger-value');
        if (swatch) swatch.style.backgroundColor = value;
        if (text) text.textContent = value.toUpperCase();
    }

    function closeColorPopover() {
        if (!activeColorPopover) return;
        const {element, outsideHandler, keyHandler} = activeColorPopover;
        document.removeEventListener('pointerdown', outsideHandler, true);
        document.removeEventListener('keydown', keyHandler, true);
        element.remove();
        activeColorPopover = null;
    }

    function colorSwatch(color, current, callback) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vdm-color-swatch';
        button.style.backgroundColor = color;
        button.title = color.toUpperCase();
        button.classList.toggle('is-active', normalizeHex(color) === normalizeHex(current));
        button.addEventListener('click', () => callback(normalizeHex(color)));
        return button;
    }

    function openColorPopover(trigger, initialValue, callback) {
        closeColorPopover();

        let current = normalizeHex(initialValue);
        let hsv = rgbToHsv(hexToRgb(current));
        let mode = 'picker';

        const popover = document.createElement('div');
        popover.className = 'vdm-color-popover';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', 'Farvevælger');

        const body = document.createElement('div');
        body.className = 'vdm-color-popover-body';
        popover.append(body);

        const actions = document.createElement('div');
        actions.className = 'vdm-color-popover-actions';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'button';
        cancel.textContent = 'Annuller';
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'button';
        toggle.textContent = 'Tema';
        const apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'button button-primary';
        apply.textContent = 'Anvend';
        actions.append(cancel, toggle, apply);
        popover.append(actions);

        function updateCurrent(value) {
            current = normalizeHex(value, current);
            hsv = rgbToHsv(hexToRgb(current));
            renderBody();
        }

        function renderPicker() {
            const plane = document.createElement('div');
            plane.className = 'vdm-color-plane';
            const cursor = document.createElement('span');
            cursor.className = 'vdm-color-plane-cursor';
            plane.append(cursor);

            const hue = document.createElement('input');
            hue.type = 'range';
            hue.className = 'vdm-color-hue';
            hue.min = '0';
            hue.max = '359';
            hue.setAttribute('aria-label', 'Farvetone');

            const hexRow = document.createElement('div');
            hexRow.className = 'vdm-color-hex-row';
            const preview = document.createElement('span');
            preview.className = 'vdm-color-current';
            const hex = document.createElement('input');
            hex.type = 'text';
            hex.maxLength = 7;
            hex.setAttribute('aria-label', 'HEX-farve');
            hexRow.append(preview, hex);

            const standards = document.createElement('div');
            standards.className = 'vdm-color-swatches';
            const swatches = [];
            ['#000000', '#ffffff', '#6a6963', '#2271b1', '#2f4858', '#d63638', '#00a32a', '#f0b849'].forEach(color => {
                const swatch = colorSwatch(color, current, value => {
                    current = normalizeHex(value, current);
                    hsv = rgbToHsv(hexToRgb(current));
                    syncPicker();
                });
                swatches.push(swatch);
                standards.append(swatch);
            });

            function syncPicker() {
                plane.style.setProperty('--vdm-picker-hue', String(hsv.h));
                cursor.style.left = (hsv.s * 100) + '%';
                cursor.style.top = ((1 - hsv.v) * 100) + '%';
                hue.value = String(Math.round(hsv.h));
                preview.style.backgroundColor = current;
                hex.value = current.toUpperCase();
                swatches.forEach(swatch => {
                    swatch.classList.toggle('is-active', normalizeHex(swatch.title) === current);
                });
            }

            plane.addEventListener('pointerdown', event => {
                const rect = plane.getBoundingClientRect();
                const applyPoint = pointEvent => {
                    const x = Math.max(0, Math.min(rect.width, pointEvent.clientX - rect.left));
                    const y = Math.max(0, Math.min(rect.height, pointEvent.clientY - rect.top));
                    hsv.s = rect.width > 0 ? x / rect.width : 0;
                    hsv.v = rect.height > 0 ? 1 - (y / rect.height) : 0;
                    current = hsvToHex(hsv.h, hsv.s, hsv.v);
                    syncPicker();
                };
                applyPoint(event);
                const move = moveEvent => applyPoint(moveEvent);
                const end = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', end);
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', end, {once: true});
                event.preventDefault();
            });

            hue.addEventListener('input', () => {
                hsv.h = Number.parseInt(hue.value, 10) || 0;
                current = hsvToHex(hsv.h, hsv.s, hsv.v);
                syncPicker();
            });

            hex.addEventListener('change', () => {
                if (/^#[0-9a-f]{6}$/i.test(hex.value.trim())) {
                    current = normalizeHex(hex.value.trim(), current);
                    hsv = rgbToHsv(hexToRgb(current));
                    syncPicker();
                } else {
                    hex.value = current.toUpperCase();
                }
            });

            body.append(plane, hue, hexRow, standards);
            syncPicker();
        }

        function renderTheme() {
            const themeTitle = document.createElement('strong');
            themeTitle.textContent = 'Temafarver';
            body.append(themeTitle);

            const theme = document.createElement('div');
            theme.className = 'vdm-color-swatches vdm-color-swatches--theme';
            const colors = Array.isArray(config.themeColors) ? config.themeColors : [];
            if (colors.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = 'Temaet har ikke angivet en farvepalette.';
                body.append(empty);
            } else {
                colors.forEach(color => theme.append(colorSwatch(color, current, updateCurrent)));
                body.append(theme);
            }

            const recent = recentColors();
            const recentTitle = document.createElement('strong');
            recentTitle.textContent = 'Senest brugt';
            body.append(recentTitle);
            const recentGrid = document.createElement('div');
            recentGrid.className = 'vdm-color-swatches';
            if (recent.length === 0) {
                const empty = document.createElement('span');
                empty.className = 'vdm-color-empty';
                empty.textContent = 'Ingen endnu';
                recentGrid.append(empty);
            } else {
                recent.forEach(color => recentGrid.append(colorSwatch(color, current, updateCurrent)));
            }
            body.append(recentGrid);
        }

        function renderBody() {
            body.innerHTML = '';
            if (mode === 'theme') renderTheme();
            else renderPicker();
            toggle.textContent = mode === 'theme' ? 'Farvevælger' : 'Tema';
        }

        toggle.addEventListener('click', () => {
            mode = mode === 'theme' ? 'picker' : 'theme';
            renderBody();
        });
        cancel.addEventListener('click', closeColorPopover);
        apply.addEventListener('click', () => {
            const selected = current;
            rememberColor(selected);
            closeColorPopover();
            callback(selected);
        });

        renderBody();
        document.body.append(popover);
        const rect = trigger.getBoundingClientRect();
        const popoverRect = popover.getBoundingClientRect();
        const left = Math.max(8, Math.min(window.innerWidth - popoverRect.width - 8, rect.left));
        const top = Math.max(8, Math.min(window.innerHeight - popoverRect.height - 8, rect.bottom + 6));
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';

        const outsideHandler = event => {
            if (!popover.contains(event.target) && event.target !== trigger) closeColorPopover();
        };
        const keyHandler = event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeColorPopover();
            }
        };
        activeColorPopover = {element: popover, outsideHandler, keyHandler};
        window.setTimeout(() => document.addEventListener('pointerdown', outsideHandler, true), 0);
        document.addEventListener('keydown', keyHandler, true);
    }

    function colorControl(value, callback) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vdm-color-trigger';
        button.innerHTML = '<span class="vdm-color-trigger-swatch"></span><span class="vdm-color-trigger-value"></span>';
        setColorControl(button, value);
        button.addEventListener('click', () => openColorPopover(button, button.dataset.color, color => {
            setColorControl(button, color);
            callback(color);
        }));
        return button;
    }

    function checkboxInput(checked, callback) {
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = Boolean(checked);
        input.addEventListener('change', () => callback(input.checked));
        return input;
    }

    function richTextControl(node) {
        const wrapper = document.createElement('div');
        wrapper.className = 'vdm-rich-text-control';

        const toolbar = document.createElement('div');
        toolbar.className = 'vdm-rich-text-toolbar';
        const editor = document.createElement('div');
        editor.className = 'vdm-rich-text-editor';
        editor.contentEditable = 'true';
        editor.innerHTML = node.props.content || '<p>Tekst</p>';

        const actions = [
            ['P', 'formatBlock', 'p'],
            ['H2', 'formatBlock', 'h2'],
            ['H3', 'formatBlock', 'h3'],
            ['B', 'bold', null],
            ['I', 'italic', null],
        ];
        actions.forEach(([label, command, argument]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.textContent = label;
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => {
                editor.focus();
                document.execCommand(command, false, argument);
                commitMutation(() => { node.props.content = editor.innerHTML; }, {inspectorRefresh: false});
            });
            toolbar.append(button);
        });

        const link = document.createElement('button');
        link.type = 'button';
        link.className = 'button';
        link.textContent = 'Link';
        link.addEventListener('mousedown', event => event.preventDefault());
        link.addEventListener('click', () => {
            editor.focus();
            const url = window.prompt('Linkadresse', 'https://');
            if (!url) return;
            document.execCommand('createLink', false, url);
            commitMutation(() => { node.props.content = editor.innerHTML; }, {inspectorRefresh: false});
        });
        toolbar.append(link);

        editor.addEventListener('input', () => {
            commitMutation(() => { node.props.content = editor.innerHTML; }, {inspectorRefresh: false});
        });

        wrapper.append(toolbar, editor);
        return wrapper;
    }

    function openMediaLibrary(node) {
        if (!window.wp || !wp.media) {
            window.alert('WordPress Mediebibliotek er ikke tilgængeligt.');
            return;
        }

        const frame = wp.media({
            title: 'Vælg billede',
            button: {text: 'Brug billede'},
            library: {type: 'image'},
            multiple: false
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first()?.toJSON();
            if (!attachment?.id) return;
            commitMutation(() => {
                node.props.attachmentId = Number.parseInt(attachment.id, 10) || 0;
                if (!node.props.alt && attachment.alt) node.props.alt = String(attachment.alt);
            });
        });
        frame.open();
    }

    function selectInput(values, selectedValue, callback) {
        const select = document.createElement('select');
        values.forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            option.selected = value === selectedValue;
            select.append(option);
        });
        select.addEventListener('change', () => callback(select.value));
        return select;
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
            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kantbredde', numberInput(node.props.borderWidth || 0, 0, 20, value => commitMutation(() => { node.props.borderWidth = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || '#d0d0d0', value => commitMutation(() => { node.props.borderColor = value; }))));
        }

        if (node.type === 'text') {
            inspector.append(field('Tekst', richTextControl(node)));
            inspector.append(field('Tekstfarve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 18, 8, 120, value => commitMutation(() => { node.props.fontSize = value; }))));
            inspector.append(field('Skriftvægt', selectInput([['400','Normal'],['500','Medium'],['600','Semibold'],['700','Fed']], String(node.props.fontWeight || 400), value => commitMutation(() => { node.props.fontWeight = Number.parseInt(value, 10); }))));
            inspector.append(field('Linjehøjde ×100', numberInput(Math.round((node.props.lineHeight || 1.5) * 100), 80, 300, value => commitMutation(() => { node.props.lineHeight = value / 100; }))));
            inspector.append(field('Justering', selectInput([['left','Venstre'],['center','Centreret'],['right','Højre']], node.props.align || 'left', value => commitMutation(() => { node.props.align = value; }))));
            inspector.append(field('Lodret placering', selectInput([['top','Top'],['center','Centreret'],['bottom','Bund']], node.props.verticalAlign || 'top', value => commitMutation(() => { node.props.verticalAlign = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : (node.props.background || '#ffffff'), value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
        }

        if (node.type === 'image') {
            const mediaActions = document.createElement('div');
            mediaActions.className = 'vdm-media-actions';
            const choose = document.createElement('button');
            choose.type = 'button';
            choose.className = 'button button-primary';
            choose.textContent = node.props.attachmentId ? 'Skift billede' : 'Vælg billede';
            choose.addEventListener('click', () => openMediaLibrary(node));
            const removeImage = document.createElement('button');
            removeImage.type = 'button';
            removeImage.className = 'button';
            removeImage.textContent = 'Fjern';
            removeImage.disabled = !node.props.attachmentId;
            removeImage.addEventListener('click', () => commitMutation(() => { node.props.attachmentId = 0; }));
            mediaActions.append(choose, removeImage);
            inspector.append(field('Mediebibliotek', mediaActions));
            inspector.append(field('Medie-ID', numberInput(node.props.attachmentId || 0, 0, 999999999, value => commitMutation(() => { node.props.attachmentId = value; }))));
            inspector.append(field('Alt-tekst', textInput(node.props.alt || '', value => commitMutation(() => { node.props.alt = value; }))));
            inspector.append(field('Billedtilpasning', selectInput([
                ['cover', 'Beskær / fyld'],
                ['contain', 'Vis hele billedet']
            ], node.props.objectFit || 'cover', value => commitMutation(() => { node.props.objectFit = value; }))));
            inspector.append(field('Vandret placering', selectInput([
                ['left', 'Venstre'],
                ['center', 'Centreret'],
                ['right', 'Højre']
            ], node.props.positionX || 'center', value => commitMutation(() => { node.props.positionX = value; }))));
            inspector.append(field('Lodret placering', selectInput([
                ['top', 'Top'],
                ['center', 'Centreret'],
                ['bottom', 'Bund']
            ], node.props.positionY || 'center', value => commitMutation(() => { node.props.positionY = value; }))));
        }

        if (node.type === 'button') {
            inspector.append(field('Tekst', textInput(node.props.label || '', value => commitMutation(() => { node.props.label = value; }))));
            inspector.append(field('Link', textInput(node.props.url || '#', value => commitMutation(() => { node.props.url = value; }))));
            inspector.append(field('Åbn link', selectInput([
                ['_self', 'Samme vindue'],
                ['_blank', 'Nyt vindue']
            ], node.props.target || '_self', value => commitMutation(() => { node.props.target = value; }))));
            inspector.append(field('Placering', selectInput([
                ['left', 'Venstre'],
                ['center', 'Centreret'],
                ['right', 'Højre'],
                ['stretch', 'Fyld bredden']
            ], node.props.align || 'left', value => commitMutation(() => { node.props.align = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 16, 8, 80, value => commitMutation(() => { node.props.fontSize = value; }))));
            inspector.append(field('Skriftvægt', selectInput([
                ['400', 'Normal'],
                ['500', 'Medium'],
                ['600', 'Semibold'],
                ['700', 'Fed']
            ], String(node.props.fontWeight || 600), value => commitMutation(() => { node.props.fontWeight = Number.parseInt(value, 10); }))));
            inspector.append(field('Padding vandret', numberInput(node.props.paddingX || 18, 0, 120, value => commitMutation(() => { node.props.paddingX = value; }))));
            inspector.append(field('Padding lodret', numberInput(node.props.paddingY || 10, 0, 80, value => commitMutation(() => { node.props.paddingY = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kantbredde', numberInput(node.props.borderWidth || 0, 0, 20, value => commitMutation(() => { node.props.borderWidth = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || node.props.background || '#2f4858', value => commitMutation(() => { node.props.borderColor = value; }))));
        }

        if (node.type === 'events') {
            inspector.append(field('Antal events', numberInput(node.props.count || 6, 1, 24, value => commitMutation(() => { node.props.count = value; }))));
            inspector.append(field('Vis tidligere events', checkboxInput(Boolean(node.props.showPast), value => commitMutation(() => { node.props.showPast = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner'],
                ['3', '3 kolonner'],
                ['4', '4 kolonner']
            ], String(node.props.columns || 3), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 20, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Kort-padding', numberInput(node.props.padding ?? 18, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 60, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kortbaggrund', colorControl(node.props.cardBackground || '#ffffff', value => commitMutation(() => { node.props.cardBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Overskriftsfarve', colorControl(node.props.headingColor || '#222222', value => commitMutation(() => { node.props.headingColor = value; }))));
            inspector.append(field('Accentfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Vis billede', checkboxInput(node.props.showImage !== false, value => commitMutation(() => { node.props.showImage = value; }))));
            inspector.append(field('Vis kort beskrivelse', checkboxInput(node.props.showSummary !== false, value => commitMutation(() => { node.props.showSummary = value; }))));
            inspector.append(field('Vis eventfakta', checkboxInput(node.props.showFacts !== false, value => commitMutation(() => { node.props.showFacts = value; }))));
        }

        if (node.type === 'vehicles') {
            inspector.append(field('Antal køretøjer', numberInput(node.props.count || 12, 1, 50, value => commitMutation(() => { node.props.count = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner'],
                ['3', '3 kolonner'],
                ['4', '4 kolonner']
            ], String(node.props.columns || 3), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 20, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Kort-padding', numberInput(node.props.padding ?? 18, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 60, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kortbaggrund', colorControl(node.props.cardBackground || '#ffffff', value => commitMutation(() => { node.props.cardBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Overskriftsfarve', colorControl(node.props.headingColor || '#222222', value => commitMutation(() => { node.props.headingColor = value; }))));
            inspector.append(field('Accentfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Vis billede', checkboxInput(node.props.showImage !== false, value => commitMutation(() => { node.props.showImage = value; }))));
            inspector.append(field('Vis kort beskrivelse', checkboxInput(node.props.showSummary !== false, value => commitMutation(() => { node.props.showSummary = value; }))));
            inspector.append(field('Vis tekniske data', checkboxInput(node.props.showFacts !== false, value => commitMutation(() => { node.props.showFacts = value; }))));
        }

        if (node.type === 'galleries') {
            inspector.append(field('Antal albummer', numberInput(node.props.count || 12, 1, 50, value => commitMutation(() => { node.props.count = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner'],
                ['3', '3 kolonner'],
                ['4', '4 kolonner']
            ], String(node.props.columns || 3), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 20, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Kort-padding', numberInput(node.props.padding ?? 16, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 60, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kortbaggrund', colorControl(node.props.cardBackground || '#ffffff', value => commitMutation(() => { node.props.cardBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Overskriftsfarve', colorControl(node.props.headingColor || '#222222', value => commitMutation(() => { node.props.headingColor = value; }))));
            inspector.append(field('Accentfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Vis cover', checkboxInput(node.props.showCover !== false, value => commitMutation(() => { node.props.showCover = value; }))));
            inspector.append(field('Vis kort beskrivelse', checkboxInput(node.props.showSummary !== false, value => commitMutation(() => { node.props.showSummary = value; }))));
        }

        if (['contact-form', 'membership-form'].includes(node.type)) {
            const isMembershipForm = node.type === 'membership-form';
            inspector.append(field('Overskrift', textInput(node.props.heading || (isMembershipForm ? 'Bliv medlem' : 'Kontakt os'), value => commitMutation(() => { node.props.heading = value; }))));
            inspector.append(field('Introduktion', textInput(node.props.intro || '', value => commitMutation(() => { node.props.intro = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner']
            ], String(node.props.columns || 2), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 16, 0, 60, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Formular-padding', numberInput(node.props.padding ?? 20, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 30, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background || '#ffffff', value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Feltbaggrund', colorControl(node.props.fieldBackground || '#ffffff', value => commitMutation(() => { node.props.fieldBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Labelfarve', colorControl(node.props.labelColor || '#222222', value => commitMutation(() => { node.props.labelColor = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || '#d0d0d0', value => commitMutation(() => { node.props.borderColor = value; }))));
            inspector.append(field('Knapfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Knaptekstfarve', colorControl(node.props.buttonTextColor || '#ffffff', value => commitMutation(() => { node.props.buttonTextColor = value; }))));
            inspector.append(field('Knaptekst', textInput(node.props.submitLabel || (isMembershipForm ? 'Send indmeldelse' : 'Send besked'), value => commitMutation(() => { node.props.submitLabel = value; }))));
            inspector.append(field('Tak-besked', textInput(node.props.successMessage || '', value => commitMutation(() => { node.props.successMessage = value; }))));
            inspector.append(field('Vis telefon', checkboxInput(node.props.showPhone !== false, value => commitMutation(() => { node.props.showPhone = value; }))));
            if (isMembershipForm) {
                inspector.append(field('Vis adresse', checkboxInput(node.props.showAddress !== false, value => commitMutation(() => { node.props.showAddress = value; }))));
            } else {
                inspector.append(field('Vis emne', checkboxInput(node.props.showSubject !== false, value => commitMutation(() => { node.props.showSubject = value; }))));
            }
            inspector.append(field('Vis besked', checkboxInput(node.props.showMessage !== false, value => commitMutation(() => { node.props.showMessage = value; }))));
            inspector.append(field('Tekstlinjer i besked', numberInput(node.props.messageRows || (isMembershipForm ? 5 : 6), 3, 12, value => commitMutation(() => { node.props.messageRows = value; }))));
            inspector.append(field('Kræv samtykke', checkboxInput(node.props.requireConsent !== false, value => commitMutation(() => { node.props.requireConsent = value; }))));
            inspector.append(field('Samtykketekst', textInput(node.props.consentText || '', value => commitMutation(() => { node.props.consentText = value; }))));
        }

        if (node.type === 'navigation') {
            const menuValues = [['0', 'Vælg menu']];
            if (Array.isArray(config.navigationMenus)) {
                config.navigationMenus.forEach(menu => {
                    const count = Number.parseInt(menu.count || 0, 10) || 0;
                    menuValues.push([String(menu.id || 0), String(menu.name || 'Menu') + ' (' + count + ')']);
                });
            }
            inspector.append(field('WordPress-menu', selectInput(menuValues, String(node.props.menuId || 0), value => commitMutation(() => { node.props.menuId = Number.parseInt(value, 10) || 0; }))));
            inspector.append(field('Retning', selectInput([
                ['horizontal', 'Vandret'],
                ['vertical', 'Lodret']
            ], node.props.orientation || 'horizontal', value => commitMutation(() => { node.props.orientation = value; }))));
            inspector.append(field('Justering', selectInput([
                ['left', 'Venstre'],
                ['center', 'Centreret'],
                ['right', 'Højre']
            ], node.props.align || 'left', value => commitMutation(() => { node.props.align = value; }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 24, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 16, 10, 40, value => commitMutation(() => { node.props.fontSize = value; }))));
            inspector.append(field('Skriftvægt', selectInput([
                ['400', 'Normal'],
                ['500', 'Medium'],
                ['600', 'Semibold'],
                ['700', 'Fed']
            ], String(node.props.fontWeight || 600), value => commitMutation(() => { node.props.fontWeight = Number.parseInt(value, 10); }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Hoverfarve', colorControl(node.props.hoverColor || '#2271b1', value => commitMutation(() => { node.props.hoverColor = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : (node.props.background || '#ffffff'), value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Undermenu-baggrund', colorControl(node.props.submenuBackground || '#ffffff', value => commitMutation(() => { node.props.submenuBackground = value; }))));
            inspector.append(field('Undermenu-tekst', colorControl(node.props.submenuTextColor || '#222222', value => commitMutation(() => { node.props.submenuTextColor = value; }))));
            inspector.append(field('Mobilknap tekst', textInput(node.props.toggleLabel || 'Menu', value => commitMutation(() => { node.props.toggleLabel = value; }))));
        }

        if (node.type === 'divider') {
            inspector.append(field('Farve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
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
            const saveUrl = config.saveUrl || (config.restBase + '/layouts/' + config.pageId);
            const response = await fetch(saveUrl, {
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
    canvas.addEventListener('submit', event => event.preventDefault());

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
