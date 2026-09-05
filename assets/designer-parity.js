(() => {
    'use strict';

    const config = window.VDMDesignerConfig || {};
    const api = window.VDMDesignerRuntime;
    const inspector = document.getElementById('vdm-inspector');
    const canvas = document.getElementById('vdm-canvas');
    const palette = document.querySelector('.vdm-palette');
    if (!api || !inspector || !canvas || !palette) return;

    let placement = null;

    function clone(value) { return JSON.parse(JSON.stringify(value)); }
    function uid() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'vdm-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
    }
    function selected(doc = api.getDocument()) { return doc.nodes.find(n => n.id === api.getSelectedId()) || null; }
    function maxOrder(doc) { return doc.nodes.reduce((m, n) => Math.max(m, Number.parseInt(n.order || 0, 10)), 0); }
    function g(x, y, w, h) { return {x, y, w, h, fineX: x * 10, fineW: w * 10}; }
    function sectionNode(id) {
        return {id, type:'section', parentId:null, order:0, props:{background:'#ffffff',padding:0,radius:0,borderWidth:0,borderColor:'#d0d0d0',autoHeight:true,minHeightRows:36},responsive:{desktop:g(0,0,12,36)}};
    }
    function defaultSpec(type) {
        const map = {
            'link': [3,6,{linkType:'url',pageId:0,url:'#',anchor:'',email:'',phone:'',target:'_self',label:'Link',align:'left',color:'#2271b1',hoverColor:'#135e96',fontSize:16,fontWeight:400,underline:true}],
            'icon': [2,8,{symbol:'★',ariaLabel:'',fontSize:36,color:'#222222',background:'transparent',radius:0,align:'center'}],
            'badge': [3,5,{label:'Badge',background:'#2f4858',color:'#ffffff',radius:999,paddingX:10,paddingY:5,fontSize:13,fontWeight:600,align:'left'}],
            'data-list': [6,16,{items:[{label:'Felt',value:'Værdi'}],labelColor:'#555555',valueColor:'#222222',dividerColor:'#d0d0d0',fontSize:16,gap:8,showDividers:true}],
            'table': [12,24,{headers:['Kolonne 1','Kolonne 2'],rows:[['Værdi 1','Værdi 2']],headerBackground:'#f0f0f1',headerColor:'#222222',cellBackground:'#ffffff',cellColor:'#222222',borderColor:'#d0d0d0',borderWidth:1,fontSize:15,striped:false}],
            'event-value': [6,8,{field:'title',label:'',showLabel:false,fontSize:24,fontWeight:700,color:'#222222',align:'left'}],
            'event-image': [12,36,{size:'large',objectFit:'cover',positionX:'center',positionY:'center',radius:0}],
            'event-field': [12,16,{fieldId:'about',showHeading:true,headingColor:'#222222',textColor:'#222222',headingSize:24,bodySize:16,background:'transparent',padding:0,radius:0}],
            'event-facts': [12,16,{showDate:true,showTime:true,showLocation:true,showAddress:true,showContact:true,columns:5,gap:8,accentColor:'#2f4858',background:'#ffffff',textColor:'#222222'}],
            'vehicle-detail': [12,80,{showImage:true,showFacts:true,showDescription:true,accentColor:'#2f4858',imageRatio:'4/3'}],
            'gallery-detail': [12,80,{columns:4,gap:16,showCaptions:true,imageRatio:'4/3'}]
        };
        return map[type] || [6,8,{}];
    }
    function chooseParent(doc, type) {
        const current = selected(doc);
        if (current && ['section','container'].includes(current.type)) return current.id;
        if (current?.parentId && doc.nodes.some(n => n.id === current.parentId)) return current.parentId;
        return doc.nodes.find(n => n.type === 'section' && !n.parentId)?.id || null;
    }
    function nextY(doc, parentId) {
        return doc.nodes.filter(n => n.parentId === parentId).reduce((m,n) => {
            const geo = n.responsive?.desktop || {y:0,h:4};
            return Math.max(m, Number(geo.y||0) + Number(geo.h||4) + 1);
        },0);
    }
    function addParityNode(type) {
        const doc = clone(api.getDocument());
        let parentId = chooseParent(doc, type);
        if (!parentId) {
            const sid = uid();
            const section = sectionNode(sid);
            section.order = maxOrder(doc) + 1;
            doc.nodes.push(section);
            parentId = sid;
        }
        const [w,h,props] = defaultSpec(type);
        const node = {id:uid(), type, parentId, order:maxOrder(doc)+1, props:clone(props), responsive:{desktop:g(0,nextY(doc,parentId),w,h)}};
        doc.nodes.push(node);
        api.replaceDocument(doc,node.id);
    }

    function group(title, entries) {
        const box = document.createElement('details');
        box.className = 'vdm-parity-palette-group';
        box.open = true;
        const summary = document.createElement('summary');
        summary.textContent = title;
        const body = document.createElement('div');
        body.className = 'vdm-parity-palette-items';
        entries.forEach(([type,label]) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'button vdm-parity-palette-item'; b.textContent = label;
            b.addEventListener('click', () => addParityNode(type));
            body.append(b);
        });
        box.append(summary,body);
        return box;
    }

    if (!document.getElementById('vdm-v1-parity-palette')) {
        const root = document.createElement('div');
        root.id = 'vdm-v1-parity-palette';
        root.innerHTML = '<hr><h3>V1-elementer</h3>';
        root.append(group('Generelt', [['link','Link'],['icon','Ikon'],['badge','Badge'],['data-list','Data List'],['table','Tabel']]));
        if (!config.templateSlot) {
            root.append(group('Detaljesider', [['event-value','Eventværdi'],['event-image','Eventbillede'],['event-field','Eventfelt'],['event-facts','Eventfaktabånd'],['vehicle-detail','Køretøjsdetalje'],['gallery-detail','Albumvisning']]));
        }
        palette.append(root);
    }

    function mutate(callback) {
        const doc = clone(api.getDocument());
        const node = selected(doc);
        if (!node) return;
        callback(node,doc);
        api.replaceDocument(doc,node.id);
    }
    function field(label, control) {
        const wrap = document.createElement('label'); wrap.className='vdm-inspector-field vdm-parity-field';
        const span = document.createElement('span'); span.textContent=label; wrap.append(span,control); return wrap;
    }
    function text(value,onchange, type='text') {
        const i=document.createElement('input');i.type=type;i.value=value??'';i.addEventListener('change',()=>onchange(i.value));return i;
    }
    function num(value,min,max,onchange) {
        const i=document.createElement('input');i.type='number';i.min=String(min);i.max=String(max);i.value=String(value??0);i.addEventListener('change',()=>onchange(Number.parseInt(i.value||'0',10)));return i;
    }
    function check(value,onchange) {
        const i=document.createElement('input');i.type='checkbox';i.checked=Boolean(value);i.addEventListener('change',()=>onchange(i.checked));return i;
    }
    function select(values,value,onchange) {
        const s=document.createElement('select');values.forEach(([v,l])=>{const o=document.createElement('option');o.value=v;o.textContent=l;o.selected=String(v)===String(value);s.append(o);});s.addEventListener('change',()=>onchange(s.value));return s;
    }
    function area(value,onchange) {
        const t=document.createElement('textarea');t.rows=6;t.value=value??'';t.addEventListener('change',()=>onchange(t.value));return t;
    }
    function section(title) {
        const box=document.createElement('div');box.className='vdm-parity-inspector-section';box.innerHTML='<h3>'+escapeHtml(title)+'</h3>';return box;
    }
    function escapeHtml(value){const d=document.createElement('div');d.textContent=String(value??'');return d.innerHTML;}
    function add(box,label,control){box.append(field(label,control));}

    function commonLink(box,node) {
        add(box,'Linktype',select([['url','Ekstern URL'],['page','Intern side'],['anchor','Anker'],['email','E-mail'],['tel','Telefon']],node.props.linkType||'url',v=>mutate(n=>n.props.linkType=v)));
        const kind=node.props.linkType||'url';
        if(kind==='page') add(box,'WordPress side-ID',num(node.props.pageId||0,0,999999999,v=>mutate(n=>n.props.pageId=v)));
        else if(kind==='anchor') add(box,'Anker',text(node.props.anchor||'',v=>mutate(n=>n.props.anchor=v)));
        else if(kind==='email') add(box,'E-mail',text(node.props.email||'',v=>mutate(n=>n.props.email=v),'email'));
        else if(kind==='tel') add(box,'Telefon',text(node.props.phone||'',v=>mutate(n=>n.props.phone=v)));
        else add(box,'URL',text(node.props.url||'#',v=>mutate(n=>n.props.url=v),'url'));
        add(box,'Åbn i',select([['_self','Samme vindue'],['_blank','Nyt vindue']],node.props.target||'_self',v=>mutate(n=>n.props.target=v)));
    }

    function inspectorParity() {
        const id=api.getSelectedId(); if(!id) return;
        const node=selected(); if(!node) return;
        if(inspector.querySelector('[data-vdm-parity-for="'+CSS.escape(id)+'"]')) return;
        const root=document.createElement('div');root.dataset.vdmParityFor=id;root.className='vdm-parity-inspector';

        if(node.type==='text'){
            const b=section('V1 typografi / kant');
            add(b,'Bogstavafstand (px)',num(node.props.letterSpacing||0,-5,20,v=>mutate(n=>n.props.letterSpacing=v)));
            add(b,'Kantbredde',num(node.props.borderWidth||0,0,20,v=>mutate(n=>n.props.borderWidth=v)));
            add(b,'Kantfarve (HEX)',text(node.props.borderColor||'#d0d0d0',v=>mutate(n=>n.props.borderColor=v)));
            root.append(b);
        }
        if(node.type==='image'){
            const b=section('V1 billede');
            add(b,'Fit',select([['cover','Beskær / fyld'],['contain','Vis hele'],['fill','Stræk'],['none','Original'],['scale-down','Skalér ned']],node.props.objectFit||'cover',v=>mutate(n=>n.props.objectFit=v)));
            add(b,'Radius',num(node.props.radius||0,0,80,v=>mutate(n=>n.props.radius=v)));
            add(b,'Kantbredde',num(node.props.borderWidth||0,0,20,v=>mutate(n=>n.props.borderWidth=v)));
            add(b,'Kantfarve',text(node.props.borderColor||'#d0d0d0',v=>mutate(n=>n.props.borderColor=v)));
            root.append(b);
        }
        if(['button','link'].includes(node.type)){
            const b=section('Fælles linkmodel'); commonLink(b,node);
            if(node.type==='button'){
                add(b,'Knaptype',select([['normal','Normal'],['floating','Flydende']],node.props.mode||'normal',v=>mutate((n,doc)=>{n.props.mode=v;if(v==='normal'&&!n.parentId){const first=doc.nodes.find(x=>x.type==='section'&&!x.parentId);if(first)n.parentId=first.id;}})));
                if((node.props.mode||'normal')==='floating'){
                    add(b,'Lag / z-index',num(node.props.zIndex||10,1,200,v=>mutate(n=>n.props.zIndex=v)));
                    const rootButton=document.createElement('button');rootButton.type='button';rootButton.className='button';rootButton.textContent='Flyt til Side-root';rootButton.addEventListener('click',()=>mutate(n=>{n.parentId=null;}));b.append(rootButton);
                }
            }
            root.append(b);
        }
        if(node.type==='link'){
            const b=section('Link');add(b,'Tekst',text(node.props.label||'Link',v=>mutate(n=>n.props.label=v)));add(b,'Størrelse',num(node.props.fontSize||16,8,80,v=>mutate(n=>n.props.fontSize=v)));add(b,'Tekstfarve',text(node.props.color||'#2271b1',v=>mutate(n=>n.props.color=v)));add(b,'Hoverfarve',text(node.props.hoverColor||'#135e96',v=>mutate(n=>n.props.hoverColor=v)));add(b,'Understreg',check(node.props.underline!==false,v=>mutate(n=>n.props.underline=v)));root.append(b);
        }
        if(node.type==='icon'){
            const b=section('Ikon');add(b,'Symbol',text(node.props.symbol||'★',v=>mutate(n=>n.props.symbol=v)));add(b,'ARIA-label',text(node.props.ariaLabel||'',v=>mutate(n=>n.props.ariaLabel=v)));add(b,'Størrelse',num(node.props.fontSize||36,8,180,v=>mutate(n=>n.props.fontSize=v)));add(b,'Farve',text(node.props.color||'#222222',v=>mutate(n=>n.props.color=v)));root.append(b);
        }
        if(node.type==='badge'){
            const b=section('Badge');add(b,'Tekst',text(node.props.label||'Badge',v=>mutate(n=>n.props.label=v)));add(b,'Baggrund',text(node.props.background||'#2f4858',v=>mutate(n=>n.props.background=v)));add(b,'Tekstfarve',text(node.props.color||'#ffffff',v=>mutate(n=>n.props.color=v)));add(b,'Radius',num(node.props.radius||999,0,999,v=>mutate(n=>n.props.radius=v)));root.append(b);
        }
        if(node.type==='data-list'){
            const b=section('Data List');add(b,'Rækker (label = værdi)',area((node.props.items||[]).map(r=>(r.label||'')+' = '+(r.value||'')).join('\n'),v=>mutate(n=>{n.props.items=v.split(/\r?\n/).filter(Boolean).map(line=>{const p=line.split('=');return{label:(p.shift()||'').trim(),value:p.join('=').trim()};});})));add(b,'Skillelinjer',check(node.props.showDividers!==false,v=>mutate(n=>n.props.showDividers=v)));root.append(b);
        }
        if(node.type==='table'){
            const b=section('Tabel');add(b,'Overskrifter (semikolon)',text((node.props.headers||[]).join(';'),v=>mutate(n=>n.props.headers=v.split(';').map(x=>x.trim()))));add(b,'Rækker (én linje, semikolon)',area((node.props.rows||[]).map(r=>r.join(';')).join('\n'),v=>mutate(n=>n.props.rows=v.split(/\r?\n/).filter(Boolean).map(line=>line.split(';').map(x=>x.trim())))));add(b,'Stribet',check(Boolean(node.props.striped),v=>mutate(n=>n.props.striped=v)));root.append(b);
        }
        if(node.type==='event-value'){
            const b=section('Eventværdi');add(b,'Felt',select([['title','Titel'],['date','Dato'],['time','Tid'],['location','Sted'],['address','Adresse'],['contact','Kontakt'],['summary','Kort beskrivelse'],['description','Beskrivelse']],node.props.field||'title',v=>mutate(n=>n.props.field=v)));add(b,'Vis label',check(Boolean(node.props.showLabel),v=>mutate(n=>n.props.showLabel=v)));add(b,'Label',text(node.props.label||'',v=>mutate(n=>n.props.label=v)));root.append(b);
        }
        if(node.type==='event-field'){
            const b=section('Eventfelt');add(b,'Felt-ID',text(node.props.fieldId||'about',v=>mutate(n=>n.props.fieldId=v)));add(b,'Vis overskrift',check(node.props.showHeading!==false,v=>mutate(n=>n.props.showHeading=v)));root.append(b);
        }
        if(node.type==='event-facts'){
            const b=section('Eventfaktabånd');[['showDate','Dato'],['showTime','Tid'],['showLocation','Sted'],['showAddress','Adresse'],['showContact','Kontakt']].forEach(([k,l])=>add(b,l,check(node.props[k]!==false,v=>mutate(n=>n.props[k]=v))));add(b,'Kolonner',num(node.props.columns||5,1,5,v=>mutate(n=>n.props.columns=v)));root.append(b);
        }
        if(node.type==='vehicle-detail'){
            const b=section('Køretøjsdetalje');add(b,'Billede',check(node.props.showImage!==false,v=>mutate(n=>n.props.showImage=v)));add(b,'Tekniske data',check(node.props.showFacts!==false,v=>mutate(n=>n.props.showFacts=v)));add(b,'Beskrivelse',check(node.props.showDescription!==false,v=>mutate(n=>n.props.showDescription=v)));root.append(b);
        }
        if(node.type==='gallery-detail'){
            const b=section('Albumvisning');add(b,'Kolonner',num(node.props.columns||4,1,6,v=>mutate(n=>n.props.columns=v)));add(b,'Afstand',num(node.props.gap||16,0,80,v=>mutate(n=>n.props.gap=v)));add(b,'Billedtekster',check(node.props.showCaptions!==false,v=>mutate(n=>n.props.showCaptions=v)));root.append(b);
        }
        if(['contact-form','membership-form'].includes(node.type)){
            const b=section('V1 formulardesign');add(b,'Feltafstand',num(node.props.fieldGap??16,0,80,v=>mutate(n=>n.props.fieldGap=v)));add(b,'Textarea-højde',num(node.props.textareaHeight??168,80,500,v=>mutate(n=>n.props.textareaHeight=v)));add(b,'Samtykkeafstand',num(node.props.consentMargin??18,0,80,v=>mutate(n=>n.props.consentMargin=v)));add(b,'Knap padding X',num(node.props.buttonPaddingX??20,0,80,v=>mutate(n=>n.props.buttonPaddingX=v)));add(b,'Knap padding Y',num(node.props.buttonPaddingY??11,0,60,v=>mutate(n=>n.props.buttonPaddingY=v)));add(b,'Modtager (valgfri)',text(node.props.recipient||'',v=>mutate(n=>n.props.recipient=v),'email'));add(b,'Send kvittering',check(node.props.sendReceipt!==false,v=>mutate(n=>n.props.sendReceipt=v)));root.append(b);
        }

        const placementBox=section('V1 placering');
        const hint=document.createElement('p');hint.className='description';hint.textContent='Vælg en retning og klik derefter på målelementet på canvas.';placementBox.append(hint);
        [['over','Over'],['under','Under'],['left','Venstre'],['right','Højre'],['in','Ind i']].forEach(([mode,label])=>{const b=document.createElement('button');b.type='button';b.className='button';b.textContent=label;b.addEventListener('click',()=>{placement={sourceId:id,mode};canvas.classList.add('vdm-placement-armed');b.blur();});placementBox.append(b,document.createTextNode(' '));});
        root.append(placementBox);
        inspector.append(root);
    }

    function geo(node) {
        const bp=document.querySelector('.vdm-breakpoint.is-active')?.dataset.breakpoint||'desktop';
        return node.responsive?.[bp] || node.responsive?.desktop || g(0,0,12,4);
    }
    function setGeo(node,value) {
        const bp=document.querySelector('.vdm-breakpoint.is-active')?.dataset.breakpoint||'desktop';node.responsive=node.responsive||{};node.responsive[bp]=value;
    }
    function placeRelative(sourceId,targetId,mode) {
        const doc=clone(api.getDocument());const source=doc.nodes.find(n=>n.id===sourceId);const target=doc.nodes.find(n=>n.id===targetId);if(!source||!target||source===target)return;
        const tg=clone(geo(target));const sg=clone(geo(source));
        if(mode==='in'){
            if(!['section','container'].includes(target.type)){window.alert('“Ind i” kræver en Sektion eller Kasse.');return;}
            source.parentId=target.id;sg.x=0;sg.fineX=0;sg.w=Math.min(12,sg.w||12);sg.fineW=sg.w*10;sg.y=nextY(doc,target.id);setGeo(source,sg);
        }else{
            if(!target.parentId && source.type!=='section'){window.alert('Brug “Ind i” for at placere et almindeligt element i en root-Sektion.');return;}
            source.parentId=target.parentId;
            if(mode==='left'||mode==='right'){
                const total=Math.max(2,tg.w);const first=Math.max(1,Math.floor(total/2));const second=Math.max(1,total-first);
                if(mode==='left'){Object.assign(sg,{x:tg.x,y:tg.y,w:first,h:tg.h,fineX:tg.x*10,fineW:first*10});tg.x+=first;tg.w=second;tg.fineX=tg.x*10;tg.fineW=second*10;}
                else{tg.w=first;tg.fineW=first*10;Object.assign(sg,{x:tg.x+first,y:tg.y,w:second,h:tg.h,fineX:(tg.x+first)*10,fineW:second*10});}
            }else{
                const total=Math.max(2,tg.h);const first=Math.max(1,Math.floor(total/2));const second=Math.max(1,total-first);
                if(mode==='over'){Object.assign(sg,{x:tg.x,y:tg.y,w:tg.w,h:first,fineX:tg.fineX??tg.x*10,fineW:tg.fineW??tg.w*10});tg.y+=first;tg.h=second;}
                else{tg.h=first;Object.assign(sg,{x:tg.x,y:tg.y+first,w:tg.w,h:second,fineX:tg.fineX??tg.x*10,fineW:tg.fineW??tg.w*10});}
            }
            setGeo(target,tg);setGeo(source,sg);
        }
        api.replaceDocument(doc,source.id);
    }

    canvas.addEventListener('click',event=>{
        if(!placement)return;const el=event.target.closest('[data-vdm-node-id]');if(!el)return;
        event.preventDefault();event.stopImmediatePropagation();const current=placement;placement=null;canvas.classList.remove('vdm-placement-armed');placeRelative(current.sourceId,el.dataset.vdmNodeId,current.mode);
    },true);

    const observer=new MutationObserver(()=>inspectorParity());
    observer.observe(inspector,{childList:true,subtree:false});
    inspectorParity();

    // V1 diagnostic-link affordance. The Log page can filter by post_id.
    const right=document.querySelector('.vdm-toolbar-right');
    if(right && !document.getElementById('vdm-copy-diagnostic-link')){
        const b=document.createElement('button');b.type='button';b.id='vdm-copy-diagnostic-link';b.className='button';b.textContent='Kopiér diagnose-link';
        b.addEventListener('click',async()=>{
            const pageId=Number.parseInt(config.pageId||'0',10)||0;
            const url=new URL(window.location.href);url.search='';url.pathname=url.pathname.replace(/[^/]*$/,'admin.php');url.searchParams.set('page','vdm-log');if(pageId>0)url.searchParams.set('post_id',String(pageId));
            try{await navigator.clipboard.writeText(url.toString());b.textContent='Diagnose-link kopieret';setTimeout(()=>b.textContent='Kopiér diagnose-link',1800);}catch(error){window.prompt('Kopiér diagnose-link',url.toString());}
        });right.insertBefore(b,right.firstChild);
    }
})();
