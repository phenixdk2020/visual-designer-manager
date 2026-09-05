(() => {
    'use strict';

    const api = window.VDMDesignerRuntime;
    const canvas = document.getElementById('vdm-canvas');
    const palette = document.querySelector('.vdm-palette');
    if (!api || !canvas || !palette) return;

    const ROW_PX = 8;
    const BREAKPOINTS = ['desktop', 'laptop', 'tablet', 'mobile'];
    const parityTypesByLabel = new Map([
        ['Link', 'link'],
        ['Ikon', 'icon'],
        ['Badge', 'badge'],
        ['Data List', 'data-list'],
        ['Tabel', 'table'],
        ['Eventværdi', 'event-value'],
        ['Eventbillede', 'event-image'],
        ['Eventfelt', 'event-field'],
        ['Eventfaktabånd', 'event-facts'],
        ['Køretøjsdetalje', 'vehicle-detail'],
        ['Albumvisning', 'gallery-detail'],
    ]);

    const specs = {
        section: [12, 36, {background:'#ffffff',padding:0,radius:0,borderWidth:0,borderColor:'#d0d0d0',autoHeight:true,minHeightRows:36}],
        container: [12, 24, {background:'transparent',padding:16,radius:0,borderWidth:0,borderColor:'#d0d0d0',autoHeight:true,minHeightRows:24}],
        text: [6, 6, {content:'<p>Tekst</p>',color:'#222222',fontSize:18,fontWeight:400,lineHeight:1.5,align:'left',verticalAlign:'top',background:'transparent',padding:0,radius:0,letterSpacing:0,borderWidth:0,borderColor:'#d0d0d0'}],
        image: [6, 18, {attachmentId:0,alt:'',objectFit:'cover',positionX:'center',positionY:'center',radius:0,borderWidth:0,borderColor:'#d0d0d0'}],
        button: [3, 6, {label:'Knap',url:'#',target:'_self',linkType:'url',pageId:0,anchor:'',email:'',phone:'',align:'left',background:'#2f4858',color:'#ffffff',radius:4,paddingX:18,paddingY:10,fontSize:16,fontWeight:600,borderWidth:0,borderColor:'#2f4858',mode:'normal',zIndex:10}],
        spacer: [12, 4, {}],
        divider: [12, 2, {color:'#d0d0d0',thickness:1}],
        events: [12, 60, {count:6,showPast:false,columns:3,gap:20,padding:18,radius:6,cardBackground:'#ffffff',textColor:'#222222',headingColor:'#222222',accentColor:'#2f4858',showImage:true,showSummary:true,showFacts:true}],
        vehicles: [12, 60, {count:12,columns:3,gap:20,padding:18,radius:6,cardBackground:'#ffffff',textColor:'#222222',headingColor:'#222222',accentColor:'#2f4858',showImage:true,showSummary:true,showFacts:true}],
        galleries: [12, 60, {count:12,columns:3,gap:20,padding:16,radius:6,cardBackground:'#ffffff',textColor:'#222222',headingColor:'#222222',accentColor:'#2f4858',showCover:true,showSummary:true}],
        navigation: [12, 8, {menuId:0,orientation:'horizontal',align:'left',gap:24,fontSize:16,fontWeight:600,textColor:'#222222',hoverColor:'#2271b1',background:'transparent',submenuBackground:'#ffffff',submenuTextColor:'#222222',toggleLabel:'Menu'}],
        'contact-form': [12, 100, {heading:'Kontakt os',intro:'Har du spørgsmål, er du velkommen til at kontakte os.',columns:2,gap:16,padding:20,radius:6,background:'#ffffff',fieldBackground:'#ffffff',textColor:'#222222',labelColor:'#222222',borderColor:'#d0d0d0',accentColor:'#2f4858',buttonTextColor:'#ffffff',submitLabel:'Send besked',successMessage:'Tak. Din henvendelse er sendt.',showPhone:true,showSubject:true,showAddress:false,showMessage:true,messageRows:6,requireConsent:true,consentText:'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.'}],
        'membership-form': [12, 128, {heading:'Bliv medlem',intro:'Udfyld formularen, så kontakter vi dig om medlemskab.',columns:2,gap:16,padding:20,radius:6,background:'#ffffff',fieldBackground:'#ffffff',textColor:'#222222',labelColor:'#222222',borderColor:'#d0d0d0',accentColor:'#2f4858',buttonTextColor:'#ffffff',submitLabel:'Send indmeldelse',successMessage:'Tak. Din indmeldelse er sendt.',showPhone:true,showSubject:false,showAddress:true,showMessage:true,messageRows:5,requireConsent:true,consentText:'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.'}],
        link: [3, 6, {linkType:'url',pageId:0,url:'#',anchor:'',email:'',phone:'',target:'_self',label:'Link',align:'left',color:'#2271b1',hoverColor:'#135e96',fontSize:16,fontWeight:400,underline:true}],
        icon: [2, 8, {symbol:'★',ariaLabel:'',fontSize:36,color:'#222222',background:'transparent',radius:0,align:'center'}],
        badge: [3, 5, {label:'Badge',background:'#2f4858',color:'#ffffff',radius:999,paddingX:10,paddingY:5,fontSize:13,fontWeight:600,align:'left'}],
        'data-list': [6, 16, {items:[{label:'Felt',value:'Værdi'}],labelColor:'#555555',valueColor:'#222222',dividerColor:'#d0d0d0',fontSize:16,gap:8,showDividers:true}],
        table: [12, 24, {headers:['Kolonne 1','Kolonne 2'],rows:[['Værdi 1','Værdi 2']],headerBackground:'#f0f0f1',headerColor:'#222222',cellBackground:'#ffffff',cellColor:'#222222',borderColor:'#d0d0d0',borderWidth:1,fontSize:15,striped:false}],
        'event-value': [6, 8, {field:'title',label:'',showLabel:false,fontSize:24,fontWeight:700,color:'#222222',align:'left'}],
        'event-image': [12, 36, {size:'large',objectFit:'cover',positionX:'center',positionY:'center',radius:0}],
        'event-field': [12, 16, {fieldId:'about',showHeading:true,headingColor:'#222222',textColor:'#222222',headingSize:24,bodySize:16,background:'transparent',padding:0,radius:0}],
        'event-facts': [12, 16, {showDate:true,showTime:true,showLocation:true,showAddress:true,showContact:true,columns:5,gap:8,accentColor:'#2f4858',background:'#ffffff',textColor:'#222222'}],
        'vehicle-detail': [12, 80, {showImage:true,showFacts:true,showDescription:true,accentColor:'#2f4858',imageRatio:'4/3'}],
        'gallery-detail': [12, 80, {columns:4,gap:16,showCaptions:true,imageRatio:'4/3'}],
    };

    let drag = null;
    let dropTarget = null;
    let hint = null;

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function uid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        return 'vdm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function breakpoint() {
        const value = String(canvas.dataset.vdmBreakpoint || 'desktop');
        return BREAKPOINTS.includes(value) ? value : 'desktop';
    }

    function effectiveGeometry(node, target = breakpoint()) {
        let current = node?.responsive?.desktop || {x:0,y:0,w:12,h:4};
        for (const key of BREAKPOINTS) {
            if (node?.responsive?.[key]) current = node.responsive[key];
            if (key === target) break;
        }
        return {...current};
    }

    function nodeById(doc, id) {
        return doc.nodes.find(node => node.id === id) || null;
    }

    function maxOrder(doc) {
        return doc.nodes.reduce((max, node) => Math.max(max, Number.parseInt(node.order || 0, 10) || 0), 0);
    }

    function nextRootY(doc) {
        return doc.nodes.filter(node => !node.parentId && node.type === 'section').reduce((max, node) => {
            const geo = effectiveGeometry(node, 'desktop');
            return Math.max(max, Number(geo.y || 0) + Number(geo.h || 36) + 2);
        }, 0);
    }

    function nextChildY(doc, parentId) {
        return doc.nodes.filter(node => node.parentId === parentId).reduce((max, node) => {
            const geo = effectiveGeometry(node);
            return Math.max(max, Number(geo.y || 0) + Number(geo.h || 4) + 1);
        }, 0);
    }

    function paletteType(button) {
        let type = String(button?.dataset?.nodeType || '').trim();
        if (!type) type = parityTypesByLabel.get(String(button?.textContent || '').trim()) || '';
        if (type && specs[type]) button.dataset.nodeType = type;
        return specs[type] ? type : '';
    }

    function enhancePalette(root = palette) {
        root.querySelectorAll('.vdm-palette-item,.vdm-parity-palette-item').forEach(button => {
            if (!paletteType(button)) return;
            button.draggable = true;
            button.setAttribute('aria-grabbed', 'false');
            button.classList.add('vdm-palette-drag-source');
            if (!button.title) button.title = 'Klik eller træk elementet ind på canvas';
        });
    }

    function selectedParent(doc) {
        const selected = nodeById(doc, api.getSelectedId());
        if (selected && ['section','container'].includes(selected.type)) return selected.id;
        if (selected?.parentId) {
            const parent = nodeById(doc, selected.parentId);
            if (parent && ['section','container'].includes(parent.type)) return parent.id;
        }
        return doc.nodes.find(node => node.type === 'section' && !node.parentId)?.id || null;
    }

    function parentFromPointer(doc, event) {
        const element = event.target instanceof Element ? event.target.closest('[data-vdm-node-id]') : null;
        const node = element ? nodeById(doc, element.getAttribute('data-vdm-node-id')) : null;
        if (node && ['section','container'].includes(node.type)) return node.id;
        if (node?.parentId) {
            const parent = nodeById(doc, node.parentId);
            if (parent && ['section','container'].includes(parent.type)) return parent.id;
        }
        return selectedParent(doc);
    }

    function sectionNode(doc, y) {
        const id = uid();
        const [w,h,props] = specs.section;
        return {
            id,
            type:'section',
            parentId:null,
            order:maxOrder(doc) + 1,
            props:clone(props),
            responsive:{desktop:{x:0,y:Math.max(0,y),w,h,fineX:0,fineW:120}},
        };
    }

    function pointerGeometry(doc, type, parentId, event) {
        const [defaultW,defaultH] = specs[type];
        const w = Math.max(1, Math.min(12, Number(defaultW) || 1));
        const h = Math.max(1, Number(defaultH) || 4);
        const currentBreakpoint = breakpoint();

        if (type === 'section') {
            const targetElement = event.target instanceof Element ? event.target.closest('[data-vdm-node-id]') : null;
            const targetNode = targetElement ? nodeById(doc, targetElement.getAttribute('data-vdm-node-id')) : null;
            if (targetNode && targetNode.type === 'section' && !targetNode.parentId) {
                const targetRect = targetElement.getBoundingClientRect();
                const targetGeo = effectiveGeometry(targetNode, currentBreakpoint);
                const before = event.clientY < targetRect.top + (targetRect.height / 2);
                return {x:0,y:Math.max(0, before ? targetGeo.y : targetGeo.y + targetGeo.h + 2),w:12,h,fineX:0,fineW:120};
            }
            const rect = canvas.getBoundingClientRect();
            const y = Math.max(0, Math.round((event.clientY - rect.top) / ROW_PX));
            return {x:0,y,w:12,h,fineX:0,fineW:120};
        }

        const parentElement = parentId ? canvas.querySelector('[data-vdm-node-id="' + cssEscape(parentId) + '"]') : null;
        if (!parentElement) return {x:0,y:nextChildY(doc,parentId),w,h,fineX:0,fineW:w*10};

        const rect = parentElement.getBoundingClientRect();
        const ratio = rect.width > 0 ? (event.clientX - rect.left) / rect.width : 0;
        const rawX = Math.floor(Math.max(0, Math.min(0.9999, ratio)) * 12);
        const x = Math.max(0, Math.min(12 - w, rawX));
        const y = Math.max(0, Math.round((event.clientY - rect.top) / ROW_PX));
        return {x,y,w,h,fineX:x*10,fineW:w*10};
    }

    function shiftRootSections(doc, insertionY, height, skipId) {
        const amount = Math.max(1, height) + 2;
        doc.nodes.forEach(node => {
            if (node.id === skipId || node.parentId || node.type !== 'section') return;
            Object.keys(node.responsive || {}).forEach(key => {
                const geo = node.responsive[key];
                if (geo && Number(geo.y || 0) >= insertionY) geo.y = Number(geo.y || 0) + amount;
            });
        });
    }

    function addDropped(type, event) {
        if (!specs[type]) return false;
        const doc = clone(api.getDocument());
        const [w,h,props] = specs[type];
        let parentId = type === 'section' ? null : parentFromPointer(doc, event);

        if (type !== 'section' && !parentId) {
            const section = sectionNode(doc, nextRootY(doc));
            doc.nodes.push(section);
            parentId = section.id;
        }

        const geometry = pointerGeometry(doc, type, parentId, event);
        if (type === 'section') shiftRootSections(doc, geometry.y, geometry.h, '');

        const node = {
            id:uid(),
            type,
            parentId,
            order:maxOrder(doc) + 1,
            props:clone(props),
            responsive:{desktop:geometry},
        };
        doc.nodes.push(node);
        return api.replaceDocument(doc, node.id) !== false;
    }

    function clearTarget() {
        if (dropTarget) dropTarget.classList.remove('is-palette-drop-target');
        dropTarget = null;
    }

    function setTarget(event) {
        clearTarget();
        const candidate = event.target instanceof Element ? event.target.closest('[data-vdm-node-id]') : null;
        if (candidate && canvas.contains(candidate)) {
            dropTarget = candidate;
            dropTarget.classList.add('is-palette-drop-target');
        }
    }

    function ensureHint() {
        if (hint) return hint;
        hint = document.createElement('div');
        hint.className = 'vdm-palette-drop-hint';
        hint.setAttribute('role', 'status');
        document.body.append(hint);
        return hint;
    }

    function moveHint(event) {
        const node = ensureHint();
        node.textContent = 'Slip for at indsætte ' + (drag?.label || 'element');
        node.style.left = Math.min(window.innerWidth - 220, event.clientX + 14) + 'px';
        node.style.top = Math.min(window.innerHeight - 50, event.clientY + 14) + 'px';
        node.hidden = false;
    }

    function cleanup() {
        clearTarget();
        canvas.classList.remove('is-palette-drop-active');
        if (drag?.source) {
            drag.source.classList.remove('is-dragging');
            drag.source.setAttribute('aria-grabbed', 'false');
        }
        if (hint) hint.hidden = true;
        drag = null;
    }

    document.addEventListener('dragstart', event => {
        const source = event.target instanceof Element ? event.target.closest('.vdm-palette-item,.vdm-parity-palette-item') : null;
        if (!source || !palette.contains(source)) return;
        const type = paletteType(source);
        if (!type) return;

        drag = {type, source, label:String(source.textContent || type).trim()};
        source.classList.add('is-dragging');
        source.setAttribute('aria-grabbed', 'true');
        canvas.classList.add('is-palette-drop-active');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('application/x-vdm-node-type', type);
            event.dataTransfer.setData('text/plain', type);
        }
    });

    document.addEventListener('dragend', event => {
        const source = event.target instanceof Element ? event.target.closest('.vdm-palette-item,.vdm-parity-palette-item') : null;
        if (!source || !palette.contains(source)) return;
        cleanup();
    });

    canvas.addEventListener('dragenter', event => {
        if (!drag) return;
        event.preventDefault();
        canvas.classList.add('is-palette-drop-active');
    });

    canvas.addEventListener('dragover', event => {
        if (!drag) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
        setTarget(event);
        moveHint(event);
    });

    canvas.addEventListener('drop', event => {
        let type = drag?.type || '';
        if (!type && event.dataTransfer) {
            type = event.dataTransfer.getData('application/x-vdm-node-type') || event.dataTransfer.getData('text/plain') || '';
        }
        if (!specs[type]) return;
        event.preventDefault();
        event.stopPropagation();
        addDropped(type, event);
        cleanup();
    });

    canvas.addEventListener('dragleave', event => {
        if (!drag) return;
        const related = event.relatedTarget;
        if (related instanceof Node && canvas.contains(related)) return;
        clearTarget();
    });

    enhancePalette();
    const observer = new MutationObserver(records => {
        for (const record of records) {
            record.addedNodes.forEach(node => {
                if (node instanceof Element) enhancePalette(node.matches('.vdm-palette') ? node : palette);
            });
        }
    });
    observer.observe(palette, {childList:true,subtree:true});
})();
