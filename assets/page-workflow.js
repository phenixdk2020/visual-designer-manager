(() => {
    'use strict';

    const config = window.VDMDesignerConfig || {};
    const pageId = Number.parseInt(config.pageId || '0', 10) || 0;
    if (pageId <= 0 || config.templateSlot) return;

    const restBase = String(config.restBase || '').replace(/\/$/, '');
    const clipboardKey = 'vdm_node_clipboard_v2';

    function runtime() {
        return window.VDMDesignerRuntime || null;
    }

    function deepClone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function isEditableTarget(target) {
        return Boolean(target?.closest?.('input,textarea,select,[contenteditable="true"]'));
    }

    function uid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'vdm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    async function requestJson(url, method = 'GET', body = null) {
        const options = {
            method,
            headers: {'X-WP-Nonce': config.nonce}
        };
        if (body !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }
        if (!response.ok) {
            throw new Error(data.message || 'Handlingen kunne ikke gennemføres.');
        }
        return data;
    }

    function openPendingWindow() {
        const popup = window.open('about:blank', '_blank');
        if (popup) {
            popup.document.title = 'Visual Designer';
            popup.document.body.innerHTML = '<p style="font-family:sans-serif;padding:24px">Forbereder forhåndsvisning…</p>';
        }
        return popup;
    }

    function navigatePopup(popup, url) {
        if (popup && !popup.closed) {
            popup.location.href = url;
            return;
        }
        window.open(url, '_blank', 'noopener');
    }

    function showToolbarMessage(message, isError = false) {
        const status = document.getElementById('vdm-save-status');
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        window.setTimeout(() => {
            status.classList.remove('is-error');
        }, 4000);
    }

    async function previewUnsaved() {
        const api = runtime();
        if (!api) return;
        const popup = openPendingWindow();
        try {
            const data = await requestJson(restBase + '/pages/' + pageId + '/preview', 'POST', {
                document: api.getDocument()
            });
            navigatePopup(popup, data.url);
        } catch (error) {
            popup?.close();
            showToolbarMessage('Preview-fejl: ' + (error.message || String(error)), true);
        }
    }

    async function saveAndView() {
        const api = runtime();
        if (!api) return;
        const ok = await api.save();
        if (!ok) return;
        const url = String(config.viewUrl || '');
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
        await loadHistory();
    }

    function selectedSubtree(document, selectedId) {
        if (!selectedId || !Array.isArray(document?.nodes)) return null;
        const ids = new Set([selectedId]);
        let changed = true;
        while (changed) {
            changed = false;
            document.nodes.forEach(node => {
                if (node.parentId && ids.has(node.parentId) && !ids.has(node.id)) {
                    ids.add(node.id);
                    changed = true;
                }
            });
        }
        const nodes = document.nodes.filter(node => ids.has(node.id));
        const root = nodes.find(node => node.id === selectedId);
        if (!root) return null;
        return {
            schemaVersion: 1,
            copiedAt: new Date().toISOString(),
            rootId: selectedId,
            nodes: deepClone(nodes)
        };
    }

    function readClipboard() {
        try {
            const raw = window.sessionStorage.getItem(clipboardKey);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.nodes) || !parsed.rootId) return null;
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function writeClipboard(value) {
        try {
            window.sessionStorage.setItem(clipboardKey, JSON.stringify(value));
            return true;
        } catch (error) {
            return false;
        }
    }

    function updatePasteButton() {
        const button = document.getElementById('vdm-paste-node');
        if (button) button.disabled = !readClipboard();
    }

    function copySelected() {
        const api = runtime();
        if (!api) return false;
        const selectedId = api.getSelectedId();
        const clip = selectedSubtree(api.getDocument(), selectedId);
        if (!clip) {
            showToolbarMessage('Vælg et element først.', true);
            return false;
        }
        if (!writeClipboard(clip)) {
            showToolbarMessage('Elementet kunne ikke kopieres til sessionens udklipsholder.', true);
            return false;
        }
        updatePasteButton();
        showToolbarMessage('Element kopieret.');
        return true;
    }

    function choosePasteParent(document, clipRoot) {
        const api = runtime();
        const existingParent = clipRoot.parentId && document.nodes.some(node => node.id === clipRoot.parentId)
            ? clipRoot.parentId
            : null;
        if (clipRoot.type === 'section') return null;
        if (existingParent) return existingParent;

        const selected = document.nodes.find(node => node.id === api?.getSelectedId());
        if (selected && ['section', 'container'].includes(selected.type)) {
            return selected.id;
        }
        if (selected && selected.parentId && document.nodes.some(node => node.id === selected.parentId)) {
            return selected.parentId;
        }
        const firstSection = document.nodes.find(node => node.type === 'section' && !node.parentId);
        return firstSection ? firstSection.id : null;
    }

    function offsetRootGeometry(node) {
        if (!node?.responsive) return;
        Object.keys(node.responsive).forEach(key => {
            const geometry = node.responsive[key];
            if (!geometry || typeof geometry !== 'object') return;
            geometry.y = Math.max(0, Number.parseInt(geometry.y || 0, 10) + 2);
            if (node.type !== 'section') {
                const width = Math.max(1, Number.parseInt(geometry.w || 1, 10));
                geometry.x = Math.max(0, Math.min(12 - width, Number.parseInt(geometry.x || 0, 10) + 1));
                if (Object.prototype.hasOwnProperty.call(geometry, 'fineX')) {
                    geometry.fineX = Math.max(0, Math.min(120 - Math.max(1, Number.parseInt(geometry.fineW || width * 10, 10)), Number.parseInt(geometry.fineX || geometry.x * 10, 10) + 10));
                }
            }
        });
    }

    function pasteClipboard() {
        const api = runtime();
        const clip = readClipboard();
        if (!api || !clip) return false;

        const document = deepClone(api.getDocument());
        const rootSource = clip.nodes.find(node => node.id === clip.rootId);
        if (!rootSource) return false;

        const idMap = new Map();
        clip.nodes.forEach(node => idMap.set(node.id, uid()));
        const newRootId = idMap.get(clip.rootId);
        const parentId = choosePasteParent(document, rootSource);
        const startOrder = document.nodes.reduce((max, node) => Math.max(max, Number.parseInt(node.order || 0, 10)), 0) + 1;

        const clones = clip.nodes.map((source, index) => {
            const clone = deepClone(source);
            clone.id = idMap.get(source.id);
            clone.order = startOrder + index;
            if (source.id === clip.rootId) {
                clone.parentId = parentId;
                offsetRootGeometry(clone);
            } else {
                clone.parentId = source.parentId && idMap.has(source.parentId)
                    ? idMap.get(source.parentId)
                    : parentId;
            }
            return clone;
        });

        document.nodes.push(...clones);
        if (!api.replaceDocument(document, newRootId)) {
            return false;
        }
        showToolbarMessage('Element indsat.');
        return true;
    }

    function duplicateSelected() {
        if (!copySelected()) return;
        pasteClipboard();
    }

    function versionEndpoint(version, action) {
        return restBase + '/pages/' + pageId + '/versions/' + Number.parseInt(version, 10) + '/' + action;
    }

    async function previewVersion(version) {
        const popup = openPendingWindow();
        try {
            const data = await requestJson(versionEndpoint(version, 'preview'), 'POST', {});
            navigatePopup(popup, data.url);
        } catch (error) {
            popup?.close();
            alert('Versionen kunne ikke forhåndsvises: ' + (error.message || String(error)));
        }
    }

    async function restoreVersion(version) {
        if (!window.confirm('Gendan version v' + version + ' som en NY version? Den nuværende version bevares i historikken.')) return;
        try {
            const data = await requestJson(versionEndpoint(version, 'restore'), 'POST', {});
            alert('Version v' + version + ' er gendannet som ny version v' + data.version + '.');
            window.location.reload();
        } catch (error) {
            alert('Versionen kunne ikke gendannes: ' + (error.message || String(error)));
        }
    }

    async function copyVersion(version) {
        try {
            const data = await requestJson(versionEndpoint(version, 'copy'), 'POST', {});
            if (data.designerUrl) {
                window.open(data.designerUrl, '_blank', 'noopener');
            }
        } catch (error) {
            alert('Versionen kunne ikke kopieres: ' + (error.message || String(error)));
        }
    }

    function historyRow(item) {
        const tr = document.createElement('tr');
        const savedAt = item.savedAt ? new Date(item.savedAt) : null;
        const when = savedAt && !Number.isNaN(savedAt.getTime()) ? savedAt.toLocaleString() : (item.savedAt || '');
        tr.innerHTML = '<td><strong>v' + Number.parseInt(item.version || 0, 10) + '</strong></td>'
            + '<td>' + escapeHtml(when) + '</td>'
            + '<td>' + escapeHtml(item.savedByName || '') + '</td>'
            + '<td>' + Number.parseInt(item.nodeCount || 0, 10) + '</td>'
            + '<td class="vdm-version-actions"></td>';
        const actions = tr.querySelector('.vdm-version-actions');
        const preview = button('Forhåndsvis', () => previewVersion(item.version));
        const restore = button('Gendan original', () => restoreVersion(item.version));
        const copy = button('Opret kopi', () => copyVersion(item.version));
        actions.append(preview, document.createTextNode(' '), restore, document.createTextNode(' '), copy);
        return tr;
    }

    function button(label, handler, className = 'button') {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = className;
        element.textContent = label;
        element.addEventListener('click', handler);
        return element;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    async function loadHistory() {
        const target = document.getElementById('vdm-version-history-body');
        const current = document.getElementById('vdm-current-version');
        if (!target) return;
        target.innerHTML = '<tr><td colspan="5">Indlæser versionshistorik…</td></tr>';
        try {
            const data = await requestJson(restBase + '/pages/' + pageId + '/history');
            if (current) current.textContent = 'Aktuel version: v' + Number.parseInt(data.currentVersion || 0, 10);
            target.innerHTML = '';
            const items = Array.isArray(data.history) ? data.history : [];
            if (items.length === 0) {
                target.innerHTML = '<tr><td colspan="5">Der findes endnu ingen tidligere versioner.</td></tr>';
                return;
            }
            items.forEach(item => target.append(historyRow(item)));
        } catch (error) {
            target.innerHTML = '<tr><td colspan="5">Versionshistorikken kunne ikke indlæses: ' + escapeHtml(error.message || String(error)) + '</td></tr>';
        }
    }

    function installToolbar() {
        const api = runtime();
        const left = document.querySelector('.vdm-toolbar-left');
        const right = document.querySelector('.vdm-toolbar-right');
        const save = document.getElementById('vdm-save');
        if (!api || !left || !right || !save) return false;

        if (!document.getElementById('vdm-copy-node')) {
            const copy = button('Kopiér', copySelected);
            copy.id = 'vdm-copy-node';
            copy.title = 'Kopiér valgt element (Ctrl+C)';
            const paste = button('Indsæt', pasteClipboard);
            paste.id = 'vdm-paste-node';
            paste.title = 'Indsæt kopieret element (Ctrl+V)';
            const duplicate = button('Duplikér', duplicateSelected);
            duplicate.id = 'vdm-duplicate-node';
            duplicate.title = 'Duplikér valgt element (Ctrl+D)';
            left.append(copy, paste, duplicate);
        }

        save.textContent = 'Gem som ny version';
        save.title = 'Gem som ny version (Ctrl+S)';

        if (!document.getElementById('vdm-preview-page')) {
            const preview = button('Forhåndsvis', previewUnsaved);
            preview.id = 'vdm-preview-page';
            preview.title = 'Forhåndsvis ugemte ændringer på frontend';
            const saveView = button('Gem & vis', saveAndView, 'button button-primary');
            saveView.id = 'vdm-save-view';
            saveView.title = 'Gem som ny version og åbn den offentlige side';
            right.insertBefore(preview, save);
            right.append(saveView);
        }

        updatePasteButton();
        return true;
    }

    function installHistory() {
        if (document.getElementById('vdm-version-history')) return;
        const workspace = document.querySelector('.vdm-workspace');
        if (!workspace) return;
        const section = document.createElement('section');
        section.id = 'vdm-version-history';
        section.className = 'vdm-version-history';
        section.innerHTML = '<div class="vdm-version-history-heading"><div><h2>Gemte versioner</h2><p id="vdm-current-version">Aktuel version</p></div><button type="button" class="button" id="vdm-refresh-history">Opdatér historik</button></div>'
            + '<table class="widefat striped"><thead><tr><th>Version</th><th>Gemt</th><th>Bruger</th><th>Elementer</th><th>Handlinger</th></tr></thead><tbody id="vdm-version-history-body"></tbody></table>';
        workspace.insertAdjacentElement('afterend', section);
        section.querySelector('#vdm-refresh-history')?.addEventListener('click', loadHistory);
        loadHistory();
    }

    window.addEventListener('keydown', event => {
        if (isEditableTarget(event.target)) return;
        const modifier = event.ctrlKey || event.metaKey;
        if (!modifier) return;
        const key = event.key.toLowerCase();
        if (key === 'c') {
            if (runtime()?.getSelectedId()) {
                event.preventDefault();
                copySelected();
            }
        } else if (key === 'v') {
            if (readClipboard()) {
                event.preventDefault();
                pasteClipboard();
            }
        } else if (key === 'd') {
            if (runtime()?.getSelectedId()) {
                event.preventDefault();
                duplicateSelected();
            }
        }
    }, true);

    function boot() {
        let attempts = 0;
        const timer = window.setInterval(() => {
            attempts += 1;
            if (installToolbar()) {
                window.clearInterval(timer);
                installHistory();
                return;
            }
            if (attempts > 50) window.clearInterval(timer);
        }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
