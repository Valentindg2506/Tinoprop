/**
 * CAS Captación — Módulo IIFE
 * UI íntegra del original, datos via CasAPI en vez de localStorage.
 * Ámbito privado: S, render, ld, sv no colisionan con Seguimiento.
 */
const Captacion = (function () {
    'use strict';

    // ── Constantes (idénticas al original) ────────────────────
    const CAL_MAP = {'Muy caliente':'q-mhot','Caliente':'q-hot','Tibio':'q-warm','Frío':'q-cold'};
    const ESTADOS = ['Nuevo','Interesado','Visita confirmada','Visitó','Arrendatario','Rechazó','Sin respuesta','Descalificado'];
    const PROPS_DEF = [
        {id:'p1',name:'San Pío X',sub:'1.200€/mes · No fumadores',addr:'Calle San Pío X 29, Valencia'},
        {id:'p2',name:'Altobuey',sub:'',addr:'Calle Campillo de Altobuey 22, puerta 8, piso 3, Valencia'},
        {id:'p3',name:'Duque de Gaeta',sub:'',addr:'Duque de Gaeta 39, puerta 6, piso 3, Valencia'},
        {id:'p4',name:'Picaio',sub:'',addr:'Calle Picaio 25, puerta 8, escalera B, piso 2, Valencia'}
    ];
    const TPL_REC = {'Nuevo':'T1','Interesado':'T5','Visita confirmada':'T5','Visitó':'T6','Rechazó':'T7','Sin respuesta':'T8','Descalificado':'T9'};
    const TPL_WHY = {'T1':'Primer contacto — presentate y calificá','T2':'Aclarar presupuesto y entrada','T3':'Resolver objeción del ascensor','T4':'Confirmar equipamiento incluido','T5':'Acordar la visita','T6':'Cerrar después de la visita','T7':'Cierre amigable si no le interesó','T8':'Reactivar sin respuesta (48h)','T9':'Último intento (1 semana)'};
    const TPLS_DEF = [
        {id:'T1',n:'1',t:'Primer contacto',txt:`Hola [NOMBRE],\n\nGracias por escribir. Soy Mariano de CAS Real Estate.\n\nTe hago unas preguntas rápidas para saber si encajamos:\n\n1. ¿Cuál es tu ocupación?\n2. ¿Dónde trabajas en Valencia?\n3. ¿Vienes solo o acompañado?\n4. ¿Cuándo necesitas entrar?\n5. ¿11 meses exactos o flexible?\n\nCon eso vemos si el piso te encaja y coordinamos visita.\n\nSaludos,\nMariano`},
        {id:'T2',n:'2',t:'Presupuesto',txt:`El piso está a 1.200€/mes + fianza 2 meses (2.400€).\n\nTotal para entrar: 3.600€.\n\n¿Eso entra en tu budget?`},
        {id:'T3',n:'3',t:'Sin ascensor',txt:`Buena pregunta. Quinta planta a mano, es verdad. PERO:\n\n- Vistas completamente despejadas\n- Luz natural todo el día\n- Muy tranquilo, sin ruido de calle\n- Si teletrabajas, lo vas a agradecer\n\n¿Trabajarías desde el piso o salís mucho?`},
        {id:'T4',n:'4',t:'WiFi / equipamiento',txt:`Sí, WiFi incluido y configurado.\n\nCocina completa: vitrocerámica, horno, microondas, nevera. También lavadora y aire acondicionado en salón y dormitorio.\n\nLlegás con tu maleta y ya está.`},
        {id:'T5',n:'5',t:'Confirmar visita',txt:`Perfecto, vemos la visita.\n\n¿Qué día/hora te va mejor?\n\n- Hoy tarde\n- Mañana\n- Este fin de semana\n\nDime y queda confirmado.`},
        {id:'T6',n:'6',t:'Post-visita (interesado)',txt:`¿Qué te pareció el piso?\n\nSi te interesa avanzar, necesitamos:\n- DNI + nómina o justificante de ingresos\n- Fianza: 2.400€ (2 meses)\n- Primer mes: 1.200€\n\nTotal entrada: 3.600€\n\n¿Lo cerramos?`},
        {id:'T7',n:'7',t:'Post-visita (no interesado)',txt:`Sin problema, te agradezco el tiempo.\n\n¿Hay algo que no te convenció? Por si tenemos otra opción.\n\nSi en el futuro necesitas algo en Valencia, aquí estoy.`},
        {id:'T8',n:'8',t:'Seguimiento 48h',txt:`Hola [NOMBRE], no tuve respuesta de tu parte.\n\nSin drama. Si te sigue interesando el piso, avisame. Tenemos varios prospectos mirándolo.\n\n¿Qué tal estás?`},
        {id:'T9',n:'9',t:'Seguimiento 1 semana',txt:`[NOMBRE], último mensaje de mi parte.\n\nSi en algún momento el piso te interesa o necesitás algo en Valencia, aquí estoy.\n\nUn saludo.`}
    ];

    // ── Estado privado ────────────────────────────────────────
    let _ct; // contenedor DOM
    let _toast, _toastTimer;
    let tplData = TPLS_DEF.map(t => ({...t, edit: t.txt}));

    let S = {
        tab:'dash', fil:'todos', sr:'',
        pros:[], visitas:[], tplEdits:{}, props:PROPS_DEF, propAct:'p1',
        visDia:_today(),
        openPros:{}, prosTabs:{}, selTpls:{}, openTpls:{}, openVis:{},
        tags:{}, newBud:'', newDur:'', newMas:'', newFum:''
    };

    // ── Cache local (offline) ─────────────────────────────────
    function ldCache(k, d) { try { const v = localStorage.getItem('cas4-'+k); return v ? JSON.parse(v) : d; } catch(e) { return d; } }
    function svCache(k, v) { try { localStorage.setItem('cas4-'+k, JSON.stringify(v)); } catch(e) {} }

    // ── Persistencia ──────────────────────────────────────────
    function persist() {
        svCache('pros',     S.pros);
        svCache('visitas',  S.visitas);
        svCache('tplEdits', S.tplEdits);
        svCache('props',    S.props);
        svCache('propAct',  S.propAct);
    }

    // Guarda un prospecto en API (fire-and-forget)
    function _savePro(p)     { CasAPI.save('inquilinos', p).catch(() => {}); }
    function _saveVisita(v)  { CasAPI.save('visitas', v).catch(() => {}); }
    function _saveProp(p)    { CasAPI.save('propiedades', p).catch(() => {}); }
    function _saveTpl(t)     { CasAPI.save('plantillas', {id:t.id,n:t.n,t:t.t,txt:t.edit}).catch(() => {}); }

    // ── Helpers ───────────────────────────────────────────────
    function _today()  { return new Date().toISOString().split('T')[0]; }
    function toast(msg) {
        if (!_toast) return;
        clearTimeout(_toastTimer);
        _toast.textContent = msg;
        _toast.classList.add('show');
        _toastTimer = setTimeout(() => _toast.classList.remove('show'), 2400);
    }
    function initials(n) { return (n||'?').split(' ').map(w=>w[0]).filter(Boolean).slice(0,2).join('').toUpperCase(); }
    function fmtDate(d) { if(!d)return'—'; const[y,m,dy]=d.split('-'); return dy+'/'+m; }
    function diffDays(d) { if(!d)return null; return Math.floor((new Date(d+'T12:00:00')-new Date(_today()+'T12:00:00'))/86400000); }
    function fmtFullDate() { return new Date().toLocaleDateString('es-ES',{weekday:'long',day:'numeric',month:'long'}); }
    function formatDateHuman(ds) { if(!ds)return''; const[y,m,d]=ds.split('-'); const months=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']; return d+' '+months[parseInt(m)-1]; }
    function addMinutes(hora,mins) { const[h,m]=hora.split(':').map(Number); const total=h*60+m+parseInt(mins); return String(Math.floor(total/60)).padStart(2,'0')+':'+String(total%60).padStart(2,'0'); }

    function autoCalif(bud,dur,mas,fum) {
        let s=0;
        if(bud&&parseInt(bud)>=1200)s+=3; else if(bud&&parseInt(bud)>900)s+=1;
        if(dur==='Exacto 11 meses'||dur==='Flexible')s+=2;
        if(mas==='Sin mascotas')s+=1;
        if(fum==='No fuma')s+=1;
        if(s>=6)return'Muy caliente'; if(s>=4)return'Caliente'; if(s>=2)return'Tibio'; return'Frío';
    }

    function getStats(propId) {
        const p=propId?S.pros.filter(x=>x.propId===propId):S.pros;
        const tod=_today();
        return {
            total:p.length, hot:p.filter(x=>x.cal==='Muy caliente').length,
            vis:p.filter(x=>['Visita confirmada','Visitó'].includes(x.estado)).length,
            cerr:p.filter(x=>x.estado==='Arrendatario').length,
            nuevo:p.filter(x=>x.estado==='Nuevo').length, inter:p.filter(x=>x.estado==='Interesado').length,
            vcnf:p.filter(x=>x.estado==='Visita confirmada').length, visi:p.filter(x=>x.estado==='Visitó').length,
            arr:p.filter(x=>x.estado==='Arrendatario').length,
            conv:p.length?Math.round(p.filter(x=>x.estado==='Arrendatario').length/p.length*100):0,
            vencidos:p.filter(x=>x.seguimiento&&x.seguimiento<tod&&!['Arrendatario','Rechazó','Descalificado'].includes(x.estado)),
            hoy:p.filter(x=>x.seguimiento===tod&&!['Arrendatario','Rechazó','Descalificado'].includes(x.estado)),
            proximos:p.filter(x=>x.seguimiento>tod&&!['Arrendatario','Rechazó','Descalificado'].includes(x.estado)),
            calientes:p.filter(x=>(x.cal==='Muy caliente'||x.cal==='Caliente')&&!['Arrendatario','Rechazó','Descalificado'].includes(x.estado))
        };
    }

    // ── Init ──────────────────────────────────────────────────
    async function init(containerEl) {
        _ct = containerEl;
        _ct.innerHTML = '<div class="cas-loading">⏳ Cargando Captación...</div>';

        // Toast y modal creados dentro del contenedor
        _toast = document.createElement('div');
        _toast.className = 'toast';
        _ct.appendChild(_toast);

        try {
            const [pros, visitas, props, plantillas] = await Promise.all([
                CasAPI.get('inquilinos'),
                CasAPI.get('visitas'),
                CasAPI.get('propiedades'),
                CasAPI.get('plantillas')
            ]);
            S.pros    = pros    || [];
            S.visitas = visitas || [];
            S.props   = (props && props.length) ? props : PROPS_DEF;
            // Cargar textos editados de plantillas
            if (plantillas) {
                plantillas.forEach(p => { S.tplEdits[p.id] = p.txt; });
                tplData = TPLS_DEF.map(t => ({...t, edit: S.tplEdits[t.id] || t.txt}));
            }
            S.propAct = ldCache('propAct', S.props[0]?.id || 'p1');
            persist();
        } catch (err) {
            // Offline — usar cache
            S.pros    = ldCache('pros',    []);
            S.visitas = ldCache('visitas', []);
            S.props   = ldCache('props',   PROPS_DEF);
            S.propAct = ldCache('propAct', 'p1');
            tplData   = TPLS_DEF.map(t => ({...t, edit: S.tplEdits[t.id] || t.txt}));
            toast('📵 Sin conexión — datos locales');
        }
        render();

        // Al reconectar, recargar datos
        CasAPI.onReconnect(async () => {
            try {
                const [pros,vis] = await Promise.all([CasAPI.get('inquilinos'),CasAPI.get('visitas')]);
                S.pros=pros||S.pros; S.visitas=vis||S.visitas; persist(); render();
                toast('🔄 Sincronizado');
            } catch(_) {}
        });
    }

    // ── RENDER ────────────────────────────────────────────────
    function render() {
        const app = _ct;
        // Limpiar excepto toast
        Array.from(app.children).forEach(c => { if(!c.classList.contains('toast')) c.remove(); });

        const propAct = S.props.find(p=>p.id===S.propAct) || S.props[0];
        const st = getStats(S.propAct);

        // HEADER
        const hdr=document.createElement('div'); hdr.className='hdr';
        const htop=document.createElement('div'); htop.className='hdr-top';
        const brand=document.createElement('div'); brand.style.cssText='display:flex;align-items:center;gap:10px';
        brand.innerHTML=`<div class="logo">CAS</div><div><div class="hdr-name">CAS Real Estate</div><div class="hdr-sub">${propAct?propAct.sub:'—'}</div></div>`;
        const right=document.createElement('div'); right.style.cssText='display:flex;align-items:center;gap:7px';
        const psel=document.createElement('select'); psel.className='prop-sel';
        S.props.forEach(p=>{const o=document.createElement('option');o.value=p.id;o.textContent=p.name;o.selected=p.id===S.propAct;psel.append(o);});
        psel.onchange=()=>{S.propAct=psel.value;svCache('propAct',S.propAct);render();};
        const badge=document.createElement('div'); badge.className='hdr-badge'; badge.textContent=st.total+' pros';
        right.append(psel,badge); htop.append(brand,right); hdr.append(htop);

        const tabs=document.createElement('div'); tabs.className='tabs';
        [['dash','📊 Hoy'],['nuevo','➕ Nuevo'],['pros','👥 Lista'],['vis','🏠 Visitas'],['tpls','💬 Plantillas'],['set','⚙️ Ajustes']].forEach(([id,label])=>{
            const btn=document.createElement('button'); btn.className='tab'+(S.tab===id?' on':''); btn.textContent=label;
            btn.onclick=()=>{S.tab=id;render();};tabs.append(btn);
        });
        hdr.append(tabs); app.prepend(hdr);

        const ct=document.createElement('div'); ct.className='ct';
        if(S.tab==='dash')    vDash(ct,st);
        else if(S.tab==='nuevo')  vNuevo(ct);
        else if(S.tab==='pros')   vPros(ct);
        else if(S.tab==='vis')    vVisitas(ct);
        else if(S.tab==='tpls')   vTpls(ct);
        else if(S.tab==='set')    vSet(ct,st);
        app.append(ct);

        const fab=document.createElement('button'); fab.className='fab'; fab.textContent='+'; fab.title='Nuevo prospecto';
        fab.onclick=()=>{S.tab='nuevo';render();_ct.scrollTop=0;};
        app.append(fab);
    }

    // ── DASHBOARD ─────────────────────────────────────────────
    function vDash(el,st) {
        const banner=document.createElement('div'); banner.className='day-banner';
        const btop=document.createElement('div'); btop.className='day-banner-top';
        btop.innerHTML=`<div><div class="day-date">${fmtFullDate()}</div><div class="day-title">Buenos días, Mariano 👋</div></div>`;
        banner.append(btop);
        const pills=document.createElement('div'); pills.className='day-pills';
        [{n:st.vencidos.length,label:'Vencidos',cls:'red',fil:'vencidos'},{n:st.hoy.length,label:'Hoy',cls:'yellow',fil:'hoy'},{n:st.proximos.length,label:'Próximos',cls:'green',fil:'proximos'},{n:st.calientes.length,label:'Calientes',cls:'purple',fil:'calientes'}].forEach(({n,label,cls,fil})=>{
            const pill=document.createElement('div'); pill.className='day-pill '+cls;
            pill.innerHTML=`<strong>${n}</strong> ${label}`;
            pill.onclick=()=>{S.tab='pros';S.fil=fil;render();};
            pills.append(pill);
        });
        banner.append(pills); el.append(banner);

        const sg=document.createElement('div'); sg.className='sg';
        [[st.total,'👥','CONTACTOS'],[st.hot,'🔥','CALIENTES'],[st.vis,'🏠','VISITAS'],[st.cerr,'🔑','CERRADOS']].forEach(([n,ic,l])=>{
            const sc=document.createElement('div'); sc.className='sc';
            sc.innerHTML=`<div class="sc-ic">${ic}</div><div class="sc-n">${n}</div><div class="sc-l">${l}</div>`;
            sg.append(sc);
        });
        el.append(sg);

        const cb=document.createElement('div'); cb.className='conv-box';
        cb.innerHTML=`<div class="conv-n">${st.conv}%</div><div class="conv-bar-wrap"><div class="conv-l">TASA DE CONVERSIÓN</div><div class="conv-bar"><div class="conv-fill" style="width:${st.conv}%"></div></div></div>`;
        el.append(cb);

        const psec=document.createElement('div'); psec.className='sec-t'; psec.textContent='Pipeline'; el.append(psec);
        const pipe=document.createElement('div'); pipe.className='pipe-row';
        [['Nuevo',st.nuevo],['Interesado',st.inter],['Visita',st.vcnf],['Visitó',st.visi],['Cerrado',st.arr]].forEach(([l,n])=>{
            const ps=document.createElement('div'); ps.className='ps'+(n>0?' act':'');
            ps.innerHTML=`<div class="ps-n">${n}</div><div class="ps-l">${l}</div>`; pipe.append(ps);
        });
        el.append(pipe);

        const visHoy=S.visitas.filter(v=>v.fecha===_today()&&v.estado!=='Cancelada').sort((a,b)=>a.hora.localeCompare(b.hora));
        if(visHoy.length){
            const vsec=document.createElement('div'); vsec.className='sec-t'; vsec.textContent='🏠 Visitas de hoy'; el.append(vsec);
            visHoy.forEach(v=>{
                const prop=S.props.find(x=>x.id===v.propId);
                const a=document.createElement('div'); a.className='alert-item yellow';
                a.innerHTML=`<div class="alert-ic">🏠</div><div style="flex:1;min-width:0"><div class="alert-name">${v.hora} · ${v.proNombre}</div><div class="alert-meta">${prop?prop.name:'—'} · ${v.duracion} min · ${v.estado}</div></div>`;
                a.onclick=()=>{S.tab='vis';S.visDia=_today();render();};
                el.append(a);
            });
        }

        if(!st.vencidos.length&&!st.hoy.length&&!st.calientes.length){
            el.innerHTML+='<div class="empty"><span class="empty-ic">✅</span>Todo al día. Sin pendientes.</div>'; return;
        }
        if(st.vencidos.length){const sec=document.createElement('div');sec.className='sec-t';sec.textContent='⚠️ Vencidos — actuar ahora';el.append(sec);st.vencidos.forEach(p=>el.append(mkAlertItem(p,'red','🔴',`Seg. vencido: ${fmtDate(p.seguimiento)}`,'mensajes')));}
        if(st.hoy.length){const sec=document.createElement('div');sec.className='sec-t';sec.textContent='📅 Para hoy';el.append(sec);st.hoy.forEach(p=>el.append(mkAlertItem(p,'yellow','📅',`${p.segHora?p.segHora+' · ':''}${p.estado}`,'mensajes')));}
        if(st.calientes.length){const sec=document.createElement('div');sec.className='sec-t';sec.textContent='🔥 Calientes sin seguimiento';el.append(sec);st.calientes.filter(p=>!p.seguimiento).forEach(p=>el.append(mkAlertItem(p,'purple','🔥',`${p.t} · ${p.estado}`,'mensajes')));}
    }

    function mkAlertItem(p,cls,ic,desc,tab) {
        const a=document.createElement('div'); a.className='alert-item '+cls;
        const prop=S.props.find(x=>x.id===p.propId);
        a.innerHTML=`<div class="alert-ic">${ic}</div><div style="flex:1;min-width:0"><div class="alert-name">${p.n}</div><div class="alert-meta">${desc}${prop?' · '+prop.name:''}</div></div><span class="qbadge ${CAL_MAP[p.cal]||'q-warm'}">${p.cal}</span>`;
        a.onclick=()=>{S.tab='pros';S.openPros[p.id]=true;S.prosTabs[p.id]=tab;S.fil='todos';render();setTimeout(()=>{const el=document.getElementById('prow-'+p.id);if(el)el.scrollIntoView({behavior:'smooth',block:'center'});},100);};
        return a;
    }

    // ── NUEVO PROSPECTO ───────────────────────────────────────
    function vNuevo(el) {
        S.tags={}; S.newBud=''; S.newDur=''; S.newMas=''; S.newFum='';
        function addSec(t){const d=document.createElement('div');d.className='sec-t';d.textContent=t;el.append(d);}
        function addFld(l,child){const d=document.createElement('div');d.className='field';const lb=document.createElement('label');lb.textContent=l;d.append(lb,child);el.append(d);}

        addSec('Datos de contacto');
        const fn=document.createElement('input');fn.type='text';fn.id='cap-fn';fn.placeholder='Ej: Juan García';addFld('Nombre',fn);
        const fr1=document.createElement('div');fr1.className='f-row';
        const ft=document.createElement('input');ft.type='tel';ft.id='cap-ft';ft.placeholder='722 123 456';
        const fe=document.createElement('input');fe.type='email';fe.id='cap-fe';fe.placeholder='juan@mail.com';
        const fd1=document.createElement('div');fd1.className='field';const l1=document.createElement('label');l1.textContent='Teléfono';fd1.append(l1,ft);
        const fd2=document.createElement('div');fd2.className='field';const l2=document.createElement('label');l2.textContent='Email';fd2.append(l2,fe);
        fr1.append(fd1,fd2);el.append(fr1);

        addSec('Perfil');
        const fo=document.createElement('input');fo.type='text';fo.id='cap-fo';fo.placeholder='Ej: Médico, Consultor';addFld('Ocupación',fo);
        const fw=document.createElement('input');fw.type='text';fw.id='cap-fw';fw.placeholder='Ej: Hospital La Fe, remoto';addFld('Dónde trabaja',fw);
        const fb=document.createElement('input');fb.type='number';fb.id='cap-fb';fb.placeholder='1200';
        fb.addEventListener('input',()=>{S.newBud=fb.value;updCal();});addFld('Budget mensual (€)',fb);

        addSec('Estancia');
        const fr2=document.createElement('div');fr2.className='f-row';
        const fent=document.createElement('input');fent.type='date';fent.id='cap-fd';
        // Duración: select + campo manual libre
        const fduWrap=document.createElement('div');
        const fdu=document.createElement('select');fdu.id='cap-fdu';
        ['Selecciona','Exacto 11 meses','Flexible','Menos de 6 meses','Dudoso','Otro (manual)'].forEach(o=>{const op=document.createElement('option');op.textContent=o;fdu.append(op);});
        const fduManual=document.createElement('input');fduManual.id='cap-fdu-manual';fduManual.placeholder='Especificar duración...';
        fduManual.style.cssText='display:none;margin-top:5px';
        fdu.addEventListener('change',()=>{
            S.newDur=fdu.value==='Otro (manual)'?fduManual.value:fdu.value;
            fduManual.style.display=fdu.value==='Otro (manual)'?'':'none';
            updCal();
        });
        fduManual.addEventListener('input',()=>{S.newDur=fduManual.value;updCal();});
        fduWrap.append(fdu,fduManual);
        const fde1=document.createElement('div');fde1.className='field';const le1=document.createElement('label');le1.textContent='Fecha entrada';fde1.append(le1,fent);
        const fde2=document.createElement('div');fde2.className='field';const le2=document.createElement('label');le2.textContent='Duración';fde2.append(le2,fduWrap);
        fr2.append(fde1,fde2);el.append(fr2);

        const dondeFld=document.createElement('div');dondeFld.className='field';
        const dondeL=document.createElement('label');dondeL.textContent='De dónde viene';
        dondeFld.append(dondeL,mkTags('d',['Rotación empresa','Mudanza','Estudio','Otro']));el.append(dondeFld);

        addSec('Convivencia');
        const acFld=document.createElement('div');acFld.className='field';const acL=document.createElement('label');acL.textContent='Acompañado / solo';acFld.append(acL,mkTags('a',['Solo','Pareja','Familia']));el.append(acFld);
        const masFld=document.createElement('div');masFld.className='field';const masL=document.createElement('label');masL.textContent='Mascotas';masFld.append(masL,mkTags('m',['Sin mascotas','Perro','Gato','Otro'],v=>{S.newMas=v;updCal();}));el.append(masFld);
        const fumFld=document.createElement('div');fumFld.className='field';const fumL=document.createElement('label');fumL.textContent='¿Fuma?';fumFld.append(fumL,mkTags('f',['No fuma','Solo en balcón','Sí'],v=>{S.newFum=v;updCal();}));el.append(fumFld);

        addSec('Perfil demográfico');
        const frDemo=document.createElement('div');frDemo.className='f-row';
        const fEdad=document.createElement('div');fEdad.className='field';const lEdad=document.createElement('label');lEdad.textContent='Edad';
        const iEdad=document.createElement('input');iEdad.type='number';iEdad.id='cap-edad';iEdad.placeholder='32';iEdad.min='18';iEdad.max='99';
        fEdad.append(lEdad,iEdad);
        const fNac=document.createElement('div');fNac.className='field';const lNac=document.createElement('label');lNac.textContent='Nacionalidad';
        const iNac=document.createElement('input');iNac.type='text';iNac.id='cap-nac';iNac.placeholder='Española, Italiana...';
        fNac.append(lNac,iNac);
        frDemo.append(fEdad,fNac);el.append(frDemo);
        const sexFld=document.createElement('div');sexFld.className='field';const sexL=document.createElement('label');sexL.textContent='Sexo';
        sexFld.append(sexL,mkTags('sx',['Hombre','Mujer','No especifica']));el.append(sexFld);
        const estRow=document.createElement('div');estRow.style.cssText='display:flex;gap:16px;margin-bottom:10px;flex-wrap:wrap';
        const chkEst=document.createElement('div');chkEst.className='chk-row';
        const cbEst=document.createElement('input');cbEst.type='checkbox';cbEst.id='cap-estudia';
        const lbEst=document.createElement('label');lbEst.htmlFor='cap-estudia';lbEst.textContent='Estudia';
        chkEst.append(cbEst,lbEst);
        const chkTrab=document.createElement('div');chkTrab.className='chk-row';
        const cbTrab=document.createElement('input');cbTrab.type='checkbox';cbTrab.id='cap-trabaja';
        const lbTrab=document.createElement('label');lbTrab.htmlFor='cap-trabaja';lbTrab.textContent='Trabaja (contrato fijo)';
        chkTrab.append(cbTrab,lbTrab);
        estRow.append(chkEst,chkTrab);el.append(estRow);

        addSec('Calificación');
        const calEl=document.createElement('div');calEl.id='cap-cal-auto';calEl.className='cal-auto cold';calEl.textContent='⭐ Calificación auto: completá más datos';el.append(calEl);
        const calManFld=document.createElement('div');calManFld.className='field';
        const calManL=document.createElement('label');calManL.textContent='Calificación manual (opcional)';
        const calManSel=document.createElement('select');calManSel.id='cap-calman';
        ['— (automática)','Muy caliente','Caliente','Tibio','Frío'].forEach(o=>{const op=document.createElement('option');op.textContent=o;calManSel.append(op);});
        calManFld.append(calManL,calManSel);el.append(calManFld);

        addSec('Seguimiento inicial');
        const fr3=document.createElement('div');fr3.className='f-row';
        const fseg=document.createElement('input');fseg.type='date';fseg.id='cap-fseg';fseg.min=_today();
        const fhsel=mkHoraSel('cap-fhora');
        const fd3a=document.createElement('div');fd3a.className='field';const l3a=document.createElement('label');l3a.textContent='Fecha';fd3a.append(l3a,fseg);
        const fd3b=document.createElement('div');fd3b.className='field';const l3b=document.createElement('label');l3b.textContent='Hora';fd3b.append(l3b,fhsel);
        fr3.append(fd3a,fd3b);el.append(fr3);

        const fno=document.createElement('textarea');fno.id='cap-fno';fno.placeholder='Notas, observaciones...';addFld('Notas',fno);
        const btn=document.createElement('button');btn.className='btn-primary';btn.textContent='✅ Guardar prospecto';btn.onclick=guardar;el.append(btn);
    }

    function mkHoraSel(id) {
        const sel=document.createElement('select');sel.id=id;
        const def=document.createElement('option');def.value='';def.textContent='Sin hora';sel.append(def);
        for(let h=8;h<=21;h++){['00','30'].forEach(m=>{const op=document.createElement('option');op.value=`${h}:${m}`;op.textContent=`${h}:${m}`;sel.append(op);});}
        return sel;
    }

    function mkTags(g,opts,cb) {
        const wrap=document.createElement('div');wrap.className='tags';wrap.setAttribute('data-tg',g);
        opts.forEach(o=>{
            const b=document.createElement('button');b.className='tag';b.type='button';b.textContent=o;
            b.onclick=()=>{_ct.querySelectorAll('[data-tg="'+g+'"] .tag').forEach(t=>t.classList.remove('on'));b.classList.add('on');S.tags[g]=o;if(cb)cb(o);};
            wrap.append(b);
        });
        return wrap;
    }

    function updCal() {
        const el=_ct.querySelector('#cap-cal-auto');if(!el)return;
        const cal=autoCalif(S.newBud,S.newDur,S.newMas,S.newFum);
        const m={'Muy caliente':'mhot','Caliente':'hot','Tibio':'warm','Frío':'cold'};
        const ic={'Muy caliente':'🔥','Caliente':'✅','Tibio':'🟡','Frío':'❄️'};
        el.className='cal-auto '+m[cal];el.textContent=ic[cal]+' Calificación automática: '+cal;
    }

    function guardar() {
        const n=(_ct.querySelector('#cap-fn').value||'').trim();
        const t=(_ct.querySelector('#cap-ft').value||'').trim();
        if(!n||!t){toast('⚠️ Nombre y teléfono obligatorios');return;}
        const bud=_ct.querySelector('#cap-fb').value;
        const fduEl=_ct.querySelector('#cap-fdu');
        const fduManEl=_ct.querySelector('#cap-fdu-manual');
        const dur=fduEl.value==='Otro (manual)'?(fduManEl?fduManEl.value:fduEl.value):fduEl.value;
        const calManSel=_ct.querySelector('#cap-calman');
        const calManVal=calManSel&&calManSel.value!=='— (automática)'?calManSel.value:'';
        const calAuto=autoCalif(bud,dur,S.tags.m||'',S.tags.f||'');
        const cal=calManVal||calAuto;
        const pro={
            id:Date.now(),n,t,
            email:(_ct.querySelector('#cap-fe').value||'').trim(),
            oc:(_ct.querySelector('#cap-fo').value||'').trim(),
            trab:(_ct.querySelector('#cap-fw').value||'').trim(),
            bud,ent:_ct.querySelector('#cap-fd').value,dur,
            de:S.tags.d||'—',ac:S.tags.a||'—',mas:S.tags.m||'—',fum:S.tags.f||'—',
            cal,calManual:calManVal,
            notas:(_ct.querySelector('#cap-fno').value||'').trim(),
            estado:'Nuevo',fecha:new Date().toLocaleDateString('es-ES'),
            propId:S.propAct,
            seguimiento:_ct.querySelector('#cap-fseg').value||'',
            segHora:_ct.querySelector('#cap-fhora').value||'',
            historial:[],
            edad:(_ct.querySelector('#cap-edad')||{}).value||'',
            nacionalidad:(_ct.querySelector('#cap-nac')||{}).value||'',
            sexo:S.tags.sx||'',
            estudia:!!(_ct.querySelector('#cap-estudia')||{}).checked,
            trabajaFijo:!!(_ct.querySelector('#cap-trabaja')||{}).checked
        };
        S.pros.unshift(pro);
        persist();
        _savePro(pro);
        toast('✅ '+n+' guardado — '+cal);S.tab='pros';S.tags={};render();
    }

    // ── Estado de ordenamiento ──────────────────────────────────
    let _sortField='id', _sortDir='desc';

    // ── LISTA PROSPECTOS ───────────────────────────────────────
    function vPros(el) {
        const expRow=document.createElement('div');expRow.style.cssText='display:flex;gap:7px;margin-bottom:8px';
        const b1=document.createElement('button');b1.className='btn-cas';b1.style.flex='1';b1.textContent='📊 CSV';b1.onclick=exportCSV;
        const b2=document.createElement('button');b2.className='btn-cas';b2.style.flex='1';b2.textContent='💾 Backup';b2.onclick=exportBackup;
        const b3=document.createElement('button');b3.className='btn-cas';b3.style.flex='1';b3.textContent='📂 Importar';b3.onclick=()=>showImportModal(el);
        expRow.append(b1,b2,b3);el.append(expRow);

        const sw=document.createElement('div');sw.className='srch-wrap';
        const si=document.createElement('input');si.type='text';si.placeholder='Buscar nombre, teléfono o email...';si.value=S.sr;
        const ic=document.createElement('span');ic.className='srch-icon';ic.textContent='🔍';
        si.addEventListener('input',e=>{S.sr=e.target.value;buildTable(tableWrap);});
        sw.append(ic,si);el.append(sw);

        const fr=document.createElement('div');fr.className='filter-row';
        [{id:'todos',label:'Todos',cls:''},{id:'hoy',label:'📅 Hoy',cls:'yellow-pill'},{id:'vencidos',label:'🔴 Vencidos',cls:'red-pill'},{id:'proximos',label:'📆 Próximos',cls:'green-pill'},{id:'calientes',label:'🔥 Calientes',cls:''},{id:'__all__',label:'🌐 Todas las props',cls:''}].forEach(({id,label,cls})=>{
            const btn=document.createElement('button');btn.className='fp '+cls+(S.fil===id?' on':'');btn.textContent=label;
            btn.onclick=()=>{S.fil=id;fr.querySelectorAll('.fp').forEach(p=>p.classList.remove('on'));btn.classList.add('on');buildTable(tableWrap);};
            fr.append(btn);
        });
        el.append(fr);

        // Barra de ordenamiento
        const sortBar=document.createElement('div');sortBar.className='sort-bar';
        [{f:'id',l:'🕒 Reciente'},{f:'n',l:'🔡 Nombre'},{f:'t',l:'📱 Teléfono'},{f:'seguimiento',l:'📅 Seguimiento'},{f:'cal',l:'⭐ Cal'},{f:'estado',l:'🎮 Estado'}].forEach(({f,l})=>{
            const btn=document.createElement('button');
            btn.className='sort-btn'+(_sortField===f?' on':'');
            btn.textContent=l+(_sortField===f?(_sortDir==='asc'?' ↑':' ↓'):'');
            btn.onclick=()=>{
                if(_sortField===f)_sortDir=_sortDir==='asc'?'desc':'asc';
                else{_sortField=f;_sortDir='asc';}
                sortBar.querySelectorAll('.sort-btn').forEach(b=>{b.classList.remove('on');b.textContent=b.textContent.replace(/ [↑↓]$/,'');});
                btn.classList.add('on');btn.textContent=l+(_sortDir==='asc'?' ↑':' ↓');
                buildTable(tableWrap);
            };
            sortBar.append(btn);
        });
        el.append(sortBar);

        const tableWrap=document.createElement('div');tableWrap.className='pros-table-wrap';
        el.append(tableWrap);
        buildTable(tableWrap);
    }

    function _sortedList(list){
        const CAL_O={'Muy caliente':0,'Caliente':1,'Tibio':2,'Frío':3};
        return[...list].sort((a,b)=>{
            let av=a[_sortField]||'',bv=b[_sortField]||'';
            if(_sortField==='cal'){av=CAL_O[av]??99;bv=CAL_O[bv]??99;}
            else if(_sortField==='id'){av=Number(av);bv=Number(bv);}
            const cmp=typeof av==='number'?av-bv:String(av).localeCompare(String(bv),'es');
            return _sortDir==='asc'?cmp:-cmp;
        });
    }

    function buildTable(container){
        container.innerHTML='';
        const tod=_today();
        let list;
        if(S.fil==='__all__')list=[...S.pros];
        else if(S.fil==='hoy')list=S.pros.filter(p=>p.propId===S.propAct&&p.seguimiento===tod);
        else if(S.fil==='vencidos')list=S.pros.filter(p=>p.propId===S.propAct&&p.seguimiento&&p.seguimiento<tod&&!['Arrendatario','Rechazó','Descalificado'].includes(p.estado));
        else if(S.fil==='proximos')list=S.pros.filter(p=>p.propId===S.propAct&&p.seguimiento>tod);
        else if(S.fil==='calientes')list=S.pros.filter(p=>p.propId===S.propAct&&(p.cal==='Muy caliente'||p.cal==='Caliente'));
        else list=S.pros.filter(p=>p.propId===S.propAct);
        if(S.sr.trim()){const q=S.sr.toLowerCase().replace(/\s/g,'');list=list.filter(p=>(p.n||'').toLowerCase().includes(S.sr.toLowerCase())||(p.t||'').replace(/\s/g,'').includes(q)||(p.email||'').toLowerCase().includes(S.sr.toLowerCase()));}
        list=_sortedList(list);
        if(!list.length){container.innerHTML=`<div class="empty"><span class="empty-ic">😶</span>${S.pros.length?'Sin resultados':'Agregá tu primer prospecto con +'}</div>`;return;}
        const table=document.createElement('table');table.className='pros-table';
        table.innerHTML=`<thead><tr><th>Nombre</th><th>Teléfono</th><th>Cal</th><th>Seguimiento</th><th>Estado</th></tr></thead>`;
        const tbody=document.createElement('tbody');
        list.forEach(p=>{
            const diff=diffDays(p.seguimiento);
            const prop=S.props.find(x=>x.id===p.propId);
            const tr=document.createElement('tr');
            if(S.openPros[p.id])tr.classList.add('row-open');
            // Nombre
            const tdN=document.createElement('td');
            tdN.innerHTML=`<div class="pt-name">${p.n}</div><div class="pt-sub">${p.oc||''}${prop&&S.fil==='__all__'?' · '+prop.name:''}</div>`;
            // Tel
            const tdT=document.createElement('td');tdT.innerHTML=`<div class="pt-tel">${p.t||'—'}</div>`;
            // Cal
            const tdC=document.createElement('td');tdC.innerHTML=`<span class="qbadge ${CAL_MAP[p.cal]||'q-warm'}">${p.cal||'Tibio'}</span>`;
            // Seguimiento
            const tdS=document.createElement('td');
            if(p.seguimiento){
                if(diff<0)tdS.innerHTML=`<span class="seg-badge seg-venc">⚠️ ${fmtDate(p.seguimiento)}</span>`;
                else if(diff===0)tdS.innerHTML=`<span class="seg-badge seg-hoy">📅 Hoy${p.segHora?' '+p.segHora:''}</span>`;
                else tdS.innerHTML=`<span class="seg-badge seg-ok">📆 ${fmtDate(p.seguimiento)}${p.segHora?' '+p.segHora:''}</span>`;
            }else tdS.innerHTML='<span style="color:#cbd5e1;font-size:10px">—</span>';
            // Estado (selector inline)
            const tdE=document.createElement('td');
            const estSel=document.createElement('select');estSel.style.cssText='font-size:10px;padding:2px 4px;border-radius:5px;border:1px solid #e2e8f0;max-width:90px';
            ESTADOS.forEach(e=>{const op=document.createElement('option');op.textContent=e;op.selected=e===p.estado;estSel.append(op);});
            estSel.onchange=ev=>{ev.stopPropagation();p.estado=estSel.value;persist();_savePro(p);toast('Estado: '+estSel.value);};
            tdE.append(estSel);
            tr.append(tdN,tdT,tdC,tdS,tdE);
            tr.onclick=ev=>{
                if(ev.target===estSel||ev.target.tagName==='OPTION')return;
                S.openPros[p.id]=!S.openPros[p.id];
                if(!S.prosTabs[p.id])S.prosTabs[p.id]='datos';
                const existing=tbody.querySelector('.expand-row[data-pid="'+p.id+'"]');
                if(existing){existing.remove();tr.classList.remove('row-open');}
                else{
                    tr.classList.add('row-open');
                    const exTr=document.createElement('tr');exTr.className='expand-row';exTr.setAttribute('data-pid',p.id);
                    const exTd=document.createElement('td');exTd.colSpan=5;
                    const inner=document.createElement('div');inner.className='expand-inner';
                    buildBody(p,inner);
                    exTd.append(inner);exTr.append(exTd);
                    tr.insertAdjacentElement('afterend',exTr);
                    setTimeout(()=>exTr.scrollIntoView({behavior:'smooth',block:'nearest'}),80);
                }
            };
            tbody.append(tr);
        });
        table.append(tbody);container.append(table);
    }

    // backward-compat (dashboard pills)
    function buildList(container){buildTable(container);}

    // ── Importar CSV ───────────────────────────────────────────
    function showImportModal(parentEl){
        const bg=document.createElement('div');bg.className='import-modal-bg';
        const modal=document.createElement('div');modal.className='import-modal';
        modal.onclick=e=>e.stopPropagation();bg.onclick=()=>bg.remove();
        modal.innerHTML=`<h3>📂 Importar prospectos</h3><p>CSV con columnas: <strong>Nombre, Teléfono, Email, Ocupación, Budget, Notas</strong><br>Primera fila = encabezado (se ignora).</p>`;
        const dz=document.createElement('div');dz.className='import-dropzone';
        dz.innerHTML=`<div class="import-dropzone-ic">📄</div><div class="import-dropzone-lbl">Tocá para seleccionar CSV</div><div class="import-dropzone-sub">o arrastrá el archivo aquí</div>`;
        const fileInp=document.createElement('input');fileInp.type='file';fileInp.accept='.csv,text/csv';fileInp.style.display='none';
        dz.onclick=()=>fileInp.click();
        dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('drag-over');});
        dz.addEventListener('dragleave',()=>dz.classList.remove('drag-over'));
        dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('drag-over');if(e.dataTransfer.files[0])processImportCSV(e.dataTransfer.files[0],preview,confirmBtn);});
        const preview=document.createElement('div');preview.className='import-preview';preview.style.display='none';
        const confirmBtn=document.createElement('button');confirmBtn.className='btn-primary';confirmBtn.textContent='✅ Importar';confirmBtn.style.display='none';
        confirmBtn._data=[];
        confirmBtn.onclick=()=>{let added=0;confirmBtn._data.forEach(pro=>{if(!S.pros.find(x=>x.t===pro.t&&x.n===pro.n)){S.pros.unshift(pro);_savePro(pro);added++;}});persist();toast('✅ '+added+' importados');bg.remove();render();};
        fileInp.onchange=()=>{if(fileInp.files[0])processImportCSV(fileInp.files[0],preview,confirmBtn);};
        const closeBtn=document.createElement('button');closeBtn.className='btn-outline';closeBtn.style.cssText='width:100%;margin-top:8px';closeBtn.textContent='Cancelar';closeBtn.onclick=()=>bg.remove();
        modal.append(dz,fileInp,preview,confirmBtn,closeBtn);bg.append(modal);_ct.append(bg);
    }
    function processImportCSV(file,previewEl,confirmBtn){
        const reader=new FileReader();
        reader.onload=ev=>{
            const lines=ev.target.result.split('\n').map(l=>l.trim()).filter(Boolean);
            const rows=lines.slice(1);const parsed=[];const html=[];
            rows.forEach((line,i)=>{
                const cols=_parseCSVLine(line);
                const n=(cols[0]||'').trim(),t=(cols[1]||'').trim();
                if(!n&&!t){html.push(`<div class="import-row"><span>#${i+2}</span><span class="import-err">Vacía</span></div>`);return;}
                parsed.push({id:Date.now()+i,n:n||'Sin nombre',t,email:(cols[2]||'').trim(),oc:(cols[3]||'').trim(),trab:'',bud:(cols[4]||'').trim(),ent:'',dur:'',de:'—',ac:'—',mas:'—',fum:'—',cal:'Tibio',calManual:'',notas:(cols[5]||'').trim(),estado:'Nuevo',fecha:new Date().toLocaleDateString('es-ES'),propId:S.propAct,seguimiento:'',segHora:'',historial:[],edad:'',nacionalidad:'',sexo:'',estudia:false,trabajaFijo:false});
                html.push(`<div class="import-row"><span><strong>${n}</strong> ${t}</span><span class="import-ok">✔ OK</span></div>`);
            });
            previewEl.innerHTML=`<div style="font-weight:700;color:#64748b;margin-bottom:6px;font-size:11px">${parsed.length} prospectos</div>`+html.join('');
            previewEl.style.display='block';confirmBtn._data=parsed;confirmBtn.style.display=parsed.length?'flex':'none';
        };
        reader.readAsText(file,'UTF-8');
    }
    function _parseCSVLine(line){const res=[];let cur='';let inQ=false;for(let i=0;i<line.length;i++){const c=line[i];if(c==='"'&&!inQ){inQ=true;continue;}if(c==='"'&&inQ){inQ=false;continue;}if(c===','&&!inQ){res.push(cur);cur='';continue;}cur+=c;}res.push(cur);return res;}




    function mkProw(p) {
        const isOpen=!!S.openPros[p.id];
        const diff=diffDays(p.seguimiento);
        let segBadgeHtml='';
        if(p.seguimiento){
            if(diff<0)segBadgeHtml=`<span class="seg-badge seg-venc">⚠️ Venc. ${fmtDate(p.seguimiento)}</span>`;
            else if(diff===0)segBadgeHtml=`<span class="seg-badge seg-hoy">📅 Hoy${p.segHora?' '+p.segHora:''}</span>`;
            else segBadgeHtml=`<span class="seg-badge seg-ok">📆 ${fmtDate(p.seguimiento)}${p.segHora?' '+p.segHora:''}</span>`;
        }
        const prop=S.props.find(x=>x.id===p.propId);
        const row=document.createElement('div');row.className='prow'+(isOpen?' op':'');row.id='prow-'+p.id;
        const hdr=document.createElement('div');hdr.className='prow-hdr';
        hdr.innerHTML=`<div class="prow-av">${initials(p.n)}</div><div class="prow-main"><div class="prow-name">${p.n}</div><div class="prow-sub">${p.t}${p.oc?' · '+p.oc:''}${prop&&S.fil==='__all__'?' · '+prop.name:''}</div></div><div class="prow-right"><span class="qbadge ${CAL_MAP[p.cal]||'q-warm'}">${p.cal||'Tibio'}</span>${segBadgeHtml}</div><span class="prow-chev" style="transform:${isOpen?'rotate(180deg)':''}">▾</span>`;
        const body=document.createElement('div');body.className='prow-body'+(isOpen?' on':'');
        hdr.onclick=()=>{
            S.openPros[p.id]=!S.openPros[p.id];
            body.classList.toggle('on',S.openPros[p.id]);
            hdr.querySelector('.prow-chev').style.transform=S.openPros[p.id]?'rotate(180deg)':'';
            row.classList.toggle('op',S.openPros[p.id]);
            if(S.openPros[p.id]&&!S.prosTabs[p.id])S.prosTabs[p.id]='datos';
            if(S.openPros[p.id])buildBody(p,body); else body.innerHTML='';
        };
        if(isOpen)buildBody(p,body);
        row.append(hdr,body);return row;
    }

    function buildBody(p,body) {
        body.innerHTML='';
        const ptabs=document.createElement('div');ptabs.className='ptabs';
        const secs={};
        [['datos','📋 Datos'],['mensajes','💬 Enviar'],['historial','🕐 Historial'],['seguimiento','📅 Seguimiento']].forEach(([id,label])=>{
            const btn=document.createElement('button');btn.className='ptab';btn.textContent=label;
            const sec=document.createElement('div');sec.className='psec';secs[id]=sec;
            const isOn=(S.prosTabs[p.id]||'datos')===id;
            if(isOn){btn.classList.add('on');sec.classList.add('on');}
            btn.onclick=()=>{S.prosTabs[p.id]=id;ptabs.querySelectorAll('.ptab').forEach(b=>b.classList.remove('on'));btn.classList.add('on');Object.values(secs).forEach(s=>s.classList.remove('on'));sec.classList.add('on');};
            ptabs.append(btn);
        });
        body.append(ptabs);
        Object.values(secs).forEach(s=>body.append(s));


        const ds=secs.datos;
        const dg=document.createElement('div');dg.className='dg dg-inline-edit';
        
        const fMap = [
            {l:'Teléfono', k:'t', t:'text'},
            {l:'Budget', k:'bud', t:'text'},
            {l:'Entrada', k:'ent', t:'text'},
            {l:'Duración', k:'dur', t:'text'},
            {l:'Acompañado', k:'ac', t:'text'},
            {l:'Mascotas', k:'mas', t:'text'},
            {l:'Fuma', k:'fum', t:'text'},
            {l:'Origen', k:'de', t:'text'},
            {l:'Trabaja', k:'trab', t:'text'},
            {l:'Contacto', k:'fecha', t:'text'},
            {l:'Edad', k:'edad', t:'number'},
            {l:'Nacionalidad', k:'nacionalidad', t:'text'},
            {l:'Sexo', k:'sexo', t:'select', opts:['','Hombre','Mujer','No especifica']},
            {l:'Estudia', k:'estudia', t:'bool'},
            {l:'Contrato fijo', k:'trabajaFijo', t:'bool'},
            {l:'Cal. manual', k:'calManual', t:'select', opts:['—','Muy caliente','Caliente','Tibio','Frío']}
        ];

        fMap.forEach(f=>{
            const d=document.createElement('div');d.className='det det-editable';
            const renderVal = () => {
                let v = p[f.k];
                if(f.t==='bool') v = v ? '✅ Sí' : '❌ No';
                else if(f.k==='bud' && v) v = v+'€';
                else if(!v) v = '—';
                if(f.k==='calManual' && (!p[f.k] || p[f.k]==='—')) v = '(auto)';
                return v;
            };

            const lDiv=document.createElement('div');lDiv.className='det-l';lDiv.textContent=f.l;
            const vDiv=document.createElement('div');vDiv.className='det-v';
            const valSpan=document.createElement('span');valSpan.textContent=renderVal();
            const editIc=document.createElement('span');editIc.className='det-edit-ic';editIc.textContent='✏️';
            vDiv.append(valSpan,editIc);
            d.append(lDiv,vDiv);

            d.onclick=()=>{
                if(d.classList.contains('editing')) return;
                d.classList.add('editing');
                let inp;
                if(f.t==='bool'){
                    inp=document.createElement('input');inp.type='checkbox';inp.checked=!!p[f.k];
                }else if(f.t==='select'){
                    inp=document.createElement('select');
                    f.opts.forEach(o=>{const op=document.createElement('option');op.value=o;op.textContent=o||'—';op.selected=o===(p[f.k]||(o==='—'?'—':''));inp.append(op);});
                }else{
                    inp=document.createElement('input');inp.type=f.t==='number'?'number':'text';inp.value=p[f.k]||'';
                }
                inp.className='det-inp';
                
                const save = () => {
                    let val = f.t==='bool'?inp.checked:inp.value;
                    if(f.k==='calManual' && val==='—') val='';
                    p[f.k] = val;
                    if(f.k==='calManual') p.cal = p.calManual || autoCalif(p.bud,p.dur,p.mas,p.fum);
                    persist(); _savePro(p);
                    d.classList.remove('editing');
                    valSpan.textContent=renderVal();
                    vDiv.innerHTML='';vDiv.append(valSpan,editIc);
                    toast('Guardado');
                };
                
                inp.onblur=save;
                inp.onkeydown=e=>{if(e.key==='Enter')save();if(e.key==='Escape'){d.classList.remove('editing');vDiv.innerHTML='';vDiv.append(valSpan,editIc);}};
                
                vDiv.innerHTML='';vDiv.append(inp);
                inp.focus();
            };
            dg.append(d);
        });
        ds.append(dg);

        const nb=document.createElement('div');nb.className='nota-block';
        const nl=document.createElement('div');nl.className='nota-label';nl.textContent='Notas';
        const nt=document.createElement('div');nt.className='nota-text';nt.textContent=p.notas||'Sin notas';
        const ne=document.createElement('textarea');ne.className='nota-edit';ne.value=p.notas||'';
        ne.onblur=()=>{p.notas=ne.value;persist();_savePro(p);nt.textContent=ne.value||'Sin notas';nt.style.display='';ne.classList.remove('on');toast('Nota guardada');};
        nb.append(nl,nt,ne);ds.append(nb);

        const estRow=document.createElement('div');estRow.className='est-fld';
        const estL=document.createElement('label');estL.textContent='Estado';
        const estSel=document.createElement('select');
        ESTADOS.forEach(e=>{const op=document.createElement('option');op.textContent=e;op.selected=e===p.estado;estSel.append(op);});
        estSel.onchange=()=>{p.estado=estSel.value;persist();_savePro(p);toast('Estado: '+estSel.value);};
        estRow.append(estL,estSel);ds.append(estRow);

        const prRow=document.createElement('div');prRow.className='est-fld';
        const prL=document.createElement('label');prL.textContent='Propiedad asignada';
        const prSel=document.createElement('select');
        S.props.forEach(prop=>{const op=document.createElement('option');op.value=prop.id;op.textContent=prop.name;op.selected=prop.id===p.propId;prSel.append(op);});
        prSel.onchange=()=>{p.propId=prSel.value;persist();_savePro(p);toast('Reasignado a: '+S.props.find(x=>x.id===p.propId)?.name);};
        prRow.append(prL,prSel);ds.append(prRow);

        const r2=document.createElement('div');r2.className='row2';
        const editBtn=document.createElement('button');editBtn.className='btn-cas';editBtn.textContent='✏️ Editar nota';editBtn.onclick=()=>{nt.style.display='none';ne.classList.add('on');ne.focus();};
        const delBtn=document.createElement('button');delBtn.className='btn-danger';delBtn.textContent='🗑 Eliminar';
        delBtn.onclick=()=>{if(confirm('¿Eliminar a '+p.n+'?')){S.pros=S.pros.filter(x=>x.id!==p.id);persist();CasAPI.remove('inquilinos',p.id).catch(()=>{});toast('Eliminado');render();}};
        r2.append(editBtn,delBtn);ds.append(r2);


        // HISTORIAL
        const hs=secs.historial;
        function renderHistorial(){
            hs.innerHTML='';
            if(!p.historial||!p.historial.length){hs.innerHTML='<div class="hist-empty">Sin mensajes enviados aún.<br>Usa el botón de abajo para agregar una entrada manual.</div>';}
            else{p.historial.forEach((h,idx)=>{
                const row=document.createElement('div');row.className='hist-item';
                const dot=document.createElement('div');dot.className='hist-dot';
                const info=document.createElement('div');info.style.flex='1';
                // hora editable
                const tplDiv=document.createElement('div');tplDiv.className='hist-tpl';tplDiv.textContent=(h.manual?'📝':'💬')+' '+(h.tplName||h.accion||'Mensaje');
                const dateInp=document.createElement('input');dateInp.type='datetime-local';dateInp.style.cssText='font-size:10px;border:1px solid #e2e8f0;border-radius:4px;padding:2px 4px;margin-top:2px;color:#64748b';
                // parse fecha (try ISO or locale)
                try{const d=new Date(h.fecha);if(!isNaN(d))dateInp.value=d.toISOString().slice(0,16);}catch(e){}
                dateInp.onchange=()=>{h.fecha=new Date(dateInp.value).toLocaleString('es-ES');persist();_savePro(p);};
                const notaDiv=document.createElement('div');notaDiv.className='hist-date';notaDiv.textContent=h.fecha+(h.nota?' · '+h.nota:'');
                info.append(tplDiv,dateInp,notaDiv);
                const acts=document.createElement('div');acts.className='hist-item-actions';
                const delHBtn=document.createElement('button');delHBtn.className='hist-del-btn';delHBtn.textContent='🗑';
                delHBtn.onclick=()=>{p.historial.splice(idx,1);persist();_savePro(p);renderHistorial();};
                acts.append(delHBtn);
                row.append(dot,info,acts);hs.append(row);
            });}
            // Boton agregar entrada manual
            const addBtn=document.createElement('button');addBtn.className='hist-add-btn';addBtn.textContent='+ Agregar entrada manual';
            let formVisible=false;
            const manForm=document.createElement('div');manForm.className='hist-manual-form';manForm.style.display='none';
            // select plantilla predefinida o manual
            const tplSel=document.createElement('select');tplSel.style.cssText='width:100%;margin-bottom:6px;font-size:12px;padding:6px 8px;border:2px solid #c4b5fd;border-radius:7px;background:#fff';
            const defOp=document.createElement('option');defOp.value='';defOp.textContent='— Manual / libre —';tplSel.append(defOp);
            tplData.forEach(t=>{const op=document.createElement('option');op.value=t.id;op.textContent=t.n+'. '+t.t;tplSel.append(op);});
            const accionInp=document.createElement('input');accionInp.placeholder='Acción o descripción...';accionInp.style.cssText='margin-bottom:6px;font-size:12px';
            const notaManInp=document.createElement('input');notaManInp.placeholder='Nota opcional...';notaManInp.style.cssText='margin-bottom:6px;font-size:12px';
            const fechaInp=document.createElement('input');fechaInp.type='datetime-local';fechaInp.value=new Date().toISOString().slice(0,16);fechaInp.style.cssText='margin-bottom:8px;font-size:12px';
            const saveHBtn=document.createElement('button');saveHBtn.className='btn-primary';saveHBtn.style.marginTop='0';saveHBtn.textContent='✅ Agregar al historial';
            saveHBtn.onclick=()=>{
                const selTpl=tplData.find(t=>t.id===tplSel.value);
                const entry={
                    tplId:tplSel.value||null,
                    tplName:selTpl?selTpl.t:(accionInp.value||'Acción manual'),
                    accion:accionInp.value,
                    nota:notaManInp.value,
                    fecha:fechaInp.value?new Date(fechaInp.value).toLocaleString('es-ES'):new Date().toLocaleString('es-ES'),
                    manual:true
                };
                if(!p.historial)p.historial=[];
                p.historial.unshift(entry);
                persist();_savePro(p);renderHistorial();
            };
            const cancelHBtn=document.createElement('button');cancelHBtn.className='btn-outline';cancelHBtn.style.cssText='width:100%;margin-top:4px;font-size:11px';cancelHBtn.textContent='Cancelar';
            cancelHBtn.onclick=()=>{formVisible=false;manForm.style.display='none';addBtn.textContent='+ Agregar entrada manual';};
            manForm.append(tplSel,accionInp,notaManInp,fechaInp,saveHBtn,cancelHBtn);
            addBtn.onclick=()=>{formVisible=!formVisible;manForm.style.display=formVisible?'block':'none';addBtn.textContent=formVisible?'✕ Cancelar':'+ Agregar entrada manual';};
            hs.append(addBtn,manForm);
        }
        renderHistorial();

        buildMensajes(p,secs.mensajes);
        buildSeguimiento(p,secs.seguimiento);
    }


    function buildMensajes(p,container) {
        container.innerHTML='';
        const recId=TPL_REC[p.estado]||'T1';
        const recTpl=tplData.find(t=>t.id===recId);
        if(recTpl){const recBox=document.createElement('div');recBox.className='tpl-rec';recBox.innerHTML=`<div class="tpl-rec-label">✨ RECOMENDADA PARA "${p.estado.toUpperCase()}"</div><div class="tpl-rec-name">${recTpl.n}. ${recTpl.t}</div><div class="tpl-rec-why">${TPL_WHY[recId]||''}</div>`;container.append(recBox);}

        const otherL=document.createElement('div');otherL.className='tpl-other-label';otherL.style.cssText='text-transform:uppercase;font-weight:700;letter-spacing:0.5px;color:#64748b;font-size:11px;margin-bottom:6px';otherL.textContent='CAMBIAR PLANTILLA:';container.append(otherL);
        const pills=document.createElement('div');pills.className='tpl-pills';
        let selId=S.selTpls[p.id]||recId;
        const preview=document.createElement('div');preview.className='msg-preview';
        const editArea=document.createElement('textarea');editArea.className='msg-edit';

        function loadTpl(id){
            selId=id;S.selTpls[p.id]=id;
            pills.querySelectorAll('.tpl-pill').forEach(x=>x.classList.remove('on'));
            const pill=pills.querySelector('[data-tpl="'+id+'"]');if(pill)pill.classList.add('on');
            const tpl=tplData.find(t=>t.id===id);if(!tpl)return;
            const txt=tpl.edit.replace(/\[NOMBRE\]/g,p.n.split(' ')[0]);
            preview.textContent=txt;preview.classList.add('on');
            editArea.value=txt;editArea.classList.remove('on');
            preview.style.display='';
        }

        tplData.forEach(t=>{
            const pill=document.createElement('button');pill.className='tpl-pill'+(t.id===selId?' on':'');
            pill.setAttribute('data-tpl',t.id);pill.textContent=t.n+'. '+t.t;
            pill.onclick=()=>loadTpl(t.id);pills.append(pill);
        });
        container.append(pills);loadTpl(selId);container.append(preview,editArea);

        const acts=document.createElement('div');acts.className='msg-actions';
        const editMsgBtn=document.createElement('button');editMsgBtn.className='btn-outline';editMsgBtn.style.flex='1';editMsgBtn.textContent='✏️ Editar';
        editMsgBtn.onclick=()=>{const isEd=editArea.classList.toggle('on');preview.style.display=isEd?'none':'';editMsgBtn.textContent=isEd?'✅ Listo':'✏️ Editar';if(isEd)editArea.focus();};
        const copyMsgBtn=document.createElement('button');copyMsgBtn.className='btn-cas btn-pink';copyMsgBtn.style.flex='1';copyMsgBtn.innerHTML='📋 Copiar';
        copyMsgBtn.onclick=()=>{const txt=editArea.classList.contains('on')?editArea.value:preview.textContent;navigator.clipboard.writeText(txt).then(()=>toast('Mensaje copiado'));};
        acts.append(editMsgBtn,copyMsgBtn);container.append(acts);

        const waBtn=document.createElement('button');waBtn.className='btn-wa-solid';
        waBtn.innerHTML='📱 Abrir WhatsApp con este mensaje';
        waBtn.onclick=()=>{
            const txt=editArea.classList.contains('on')?editArea.value:preview.textContent;
            const tpl=tplData.find(t=>t.id===selId);
            if(!p.historial)p.historial=[];
            p.historial.unshift({tplId:selId,tplName:tpl?tpl.t:'Mensaje',fecha:new Date().toLocaleString('es-ES')});
            persist();_savePro(p);
            const raw=(p.t||'').replace(/[\s\-\(\)]/g,'');
            const num=raw.startsWith('+')?raw.slice(1):raw;
            const encoded=encodeURIComponent(txt);
            // Intentar deep link nativo, fallback a wa.me
            const deepLink='whatsapp://send?phone='+num+'&text='+encoded;
            const webLink='https://wa.me/'+num+'?text='+encoded;
            const isMobile=/Android|iPhone|iPad/i.test(navigator.userAgent);
            if(isMobile){
                const a=document.createElement('a');a.href=deepLink;a.click();
            } else {
                window.open(webLink,'_blank');
            }
            toast('📱 Abriendo WhatsApp...');
            setTimeout(()=>nextBlock.style.display='',600);
        };
        container.append(waBtn);

        const waRaw=document.createElement('button');waRaw.className='btn-outline';waRaw.style.cssText='margin-top:6px;width:100%;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;border-radius:8px;font-size:12px;font-weight:700;color:#16a34a;border:2px solid #86efac';
        waRaw.innerHTML='📱 Abrir WhatsApp sin mensaje';
        waRaw.onclick=()=>{
            const raw=(p.t||'').replace(/[\s\-\(\)]/g,'');
            const num=raw.startsWith('+')?raw.slice(1):raw;
            const isMobile=/Android|iPhone|iPad/i.test(navigator.userAgent);
            if(isMobile){const a=document.createElement('a');a.href='whatsapp://send?phone='+num;a.click();}
            else window.open('https://wa.me/'+num,'_blank');
        };
        container.append(waRaw);

        const nextBlock=document.createElement('div');nextBlock.className='next-block';nextBlock.style.display='none';
        nextBlock.innerHTML=`<div class="next-title">📅 Agendar próximo contacto</div>`;
        const nr=document.createElement('div');nr.className='next-row';
        const nextDate=document.createElement('input');nextDate.type='date';nextDate.min=_today();nextDate.value=p.seguimiento||'';
        const nextHora=mkHoraSel('next-hora-'+p.id+'-'+Date.now());
        if(p.segHora){Array.from(nextHora.options).forEach(o=>{if(o.value===p.segHora)o.selected=true;});}
        const fd1=document.createElement('div');fd1.className='field';const fl1=document.createElement('label');fl1.textContent='Fecha';fd1.append(fl1,nextDate);
        const fd2=document.createElement('div');fd2.className='field';const fl2=document.createElement('label');fl2.textContent='Hora';fd2.append(fl2,nextHora);
        nr.append(fd1,fd2);nextBlock.append(nr);
        // Nota manual para el próximo evento
        const notaEvFld=document.createElement('div');notaEvFld.className='field';notaEvFld.style.marginTop='6px';
        const notaEvL=document.createElement('label');notaEvL.textContent='Nota / acción (opcional)';
        const notaEvInp=document.createElement('input');notaEvInp.type='text';notaEvInp.placeholder='Ej: Llamar para confirmar visita...';notaEvInp.value=p.notaProx||'';
        notaEvFld.append(notaEvL,notaEvInp);nextBlock.append(notaEvFld);
        const saveNextBtn=document.createElement('button');saveNextBtn.className='btn-primary';saveNextBtn.style.marginTop='8px';saveNextBtn.textContent='✅ Guardar próximo contacto';
        saveNextBtn.onclick=()=>{if(!nextDate.value){toast('⚠️ Seleccioná una fecha');return;}p.seguimiento=nextDate.value;p.segHora=nextHora.value;p.notaProx=notaEvInp.value;persist();_savePro(p);toast('📅 Próximo contacto: '+fmtDate(nextDate.value)+(nextHora.value?' '+nextHora.value:''));nextBlock.style.display='none';};
        nextBlock.append(saveNextBtn);
        const skipBtn=document.createElement('button');skipBtn.className='btn-outline';skipBtn.style.cssText='width:100%;margin-top:6px;font-size:11px;padding:7px';skipBtn.textContent='Omitir por ahora';
        skipBtn.onclick=()=>{nextBlock.style.display='none';};
        nextBlock.append(skipBtn);container.append(nextBlock);
    }


    function buildSeguimiento(p,container) {
        container.innerHTML='';
        const sb=document.createElement('div');sb.className='seg-block';
        const fr=document.createElement('div');fr.className='f-row';
        const segDate=document.createElement('input');segDate.type='date';segDate.min=_today();segDate.value=p.seguimiento||'';
        const segHora=mkHoraSel('seg-hora-'+p.id+'-'+Date.now());
        if(p.segHora){Array.from(segHora.options).forEach(o=>{if(o.value===p.segHora)o.selected=true;});}
        const fd1=document.createElement('div');fd1.className='field';const fl1=document.createElement('label');fl1.textContent='Fecha del próximo contacto';fd1.append(fl1,segDate);
        const fd2=document.createElement('div');fd2.className='field';const fl2=document.createElement('label');fl2.textContent='Hora estimada';fd2.append(fl2,segHora);
        fr.append(fd1,fd2);sb.append(fr);
        if(p.seguimiento){const diff=diffDays(p.seguimiento);let msg,cls;if(diff<0){msg=`⚠️ Vencido hace ${Math.abs(diff)} día${Math.abs(diff)!==1?'s':''}`;cls='venc';}else if(diff===0){msg='📅 Es hoy'+(p.segHora?' a las '+p.segHora:'');cls='hoy';}else{msg=`✅ En ${diff} día${diff!==1?'s':''}${p.segHora?' a las '+p.segHora:''}`;cls='ok';}const badge=document.createElement('div');badge.className='seg-status '+cls;badge.textContent=msg;sb.append(badge);}
        const btnRow=document.createElement('div');btnRow.className='row2';btnRow.style.marginTop='10px';
        const saveBtn=document.createElement('button');saveBtn.className='btn-primary';saveBtn.style.marginTop='0';saveBtn.textContent='💾 Guardar';
        saveBtn.onclick=()=>{p.seguimiento=segDate.value;p.segHora=segHora.value;persist();_savePro(p);toast(segDate.value?'📅 Guardado: '+fmtDate(segDate.value)+(segHora.value?' '+segHora.value:''):'Seguimiento eliminado');buildSeguimiento(p,container);};
        const clrBtn=document.createElement('button');clrBtn.className='btn-outline';clrBtn.textContent='🗑 Quitar';
        clrBtn.onclick=()=>{p.seguimiento='';p.segHora='';segDate.value='';persist();_savePro(p);toast('Seguimiento eliminado');buildSeguimiento(p,container);};
        btnRow.append(saveBtn,clrBtn);sb.append(btnRow);container.append(sb);
    }

    // ── VISITAS ────────────────────────────────────────────────
    function vVisitas(el) {
        const topRow=document.createElement('div');topRow.style.cssText='display:flex;gap:7px;margin-bottom:12px;align-items:center';
        const titulo=document.createElement('div');titulo.style.cssText='flex:1;font-size:15px;font-weight:700;color:#1e293b';titulo.textContent='🏠 Visitas';
        const btnNueva=document.createElement('button');btnNueva.className='btn-primary';btnNueva.style.cssText='margin-top:0;padding:9px 14px;width:auto;font-size:12px';btnNueva.textContent='+ Nueva visita';
        btnNueva.onclick=()=>{formWrap.style.display=formWrap.style.display==='none'?'block':'none';btnNueva.textContent=formWrap.style.display==='none'?'+ Nueva visita':'✕ Cancelar';};
        topRow.append(titulo,btnNueva);el.append(topRow);
        const formWrap=document.createElement('div');formWrap.style.display='none';
        buildVisForm(formWrap,null,()=>{formWrap.style.display='none';btnNueva.textContent='+ Nueva visita';buildVisContent(contentEl);});
        el.append(formWrap);

        const semSec=document.createElement('div');semSec.className='sec-t';semSec.textContent='Esta semana';el.append(semSec);
        const semGrid=document.createElement('div');semGrid.className='vis-week';
        const weekDays=['L','M','X','J','V','S','D'];
        const now=new Date();const dow=now.getDay();const diffToMon=dow===0?-6:1-dow;
        const lunes=new Date(now);lunes.setDate(now.getDate()+diffToMon);
        for(let i=0;i<7;i++){
            const d=new Date(lunes);d.setDate(lunes.getDate()+i);
            const ds=d.toISOString().split('T')[0];
            const cnt=S.visitas.filter(v=>v.fecha===ds&&v.estado!=='Cancelada').length;
            const isToday=ds===_today();
            const col=document.createElement('div');col.className='vis-day-col'+(isToday?' today':'')+(cnt>0?' has-vis':'');
            col.innerHTML=`<div class="vis-day-name">${weekDays[i]}</div><div class="vis-day-num">${d.getDate()}</div><div class="vis-day-cnt${cnt===0?' zero':''}">${cnt>0?cnt+' vis':''}</div>`;
            col.onclick=()=>{S.visDia=ds;buildVisContent(contentEl);};
            semGrid.append(col);
        }
        el.append(semGrid);

        const diaRow=document.createElement('div');diaRow.style.cssText='display:flex;gap:7px;margin-bottom:10px;align-items:center';
        const diaLabel=document.createElement('div');diaLabel.style.cssText='font-size:11px;font-weight:700;color:#64748b;flex-shrink:0';diaLabel.textContent='Ver día:';
        const diaInp=document.createElement('input');diaInp.type='date';diaInp.value=S.visDia;diaInp.style.cssText='flex:1;font-size:13px;padding:7px 10px';
        diaInp.onchange=()=>{S.visDia=diaInp.value;buildVisContent(contentEl);};
        const hoyBtn=document.createElement('button');hoyBtn.className='btn-cas';hoyBtn.style.cssText='padding:7px 10px;font-size:11px;flex-shrink:0';hoyBtn.textContent='Hoy';
        hoyBtn.onclick=()=>{S.visDia=_today();diaInp.value=S.visDia;buildVisContent(contentEl);};
        diaRow.append(diaLabel,diaInp,hoyBtn);el.append(diaRow);

        const expRow=document.createElement('div');expRow.style.cssText='display:flex;gap:6px;margin-bottom:10px';
        const expDiaBtn=document.createElement('button');expDiaBtn.className='btn-cas';expDiaBtn.style.flex='1';expDiaBtn.textContent='📅 Exportar día (.ics)';expDiaBtn.onclick=()=>exportICS('dia');
        const expTodBtn=document.createElement('button');expTodBtn.className='btn-cas';expTodBtn.style.flex='1';expTodBtn.textContent='📅 Exportar todas (.ics)';expTodBtn.onclick=()=>exportICS('all');
        expRow.append(expDiaBtn,expTodBtn);el.append(expRow);

        const diaSec=document.createElement('div');diaSec.className='sec-t';
        const contentEl=document.createElement('div');
        el.append(diaSec,contentEl);

        function buildVisContent(container){
            const ds=S.visDia;
            const dayVisitas=S.visitas.filter(v=>v.fecha===ds).sort((a,b)=>a.hora.localeCompare(b.hora));
            const isToday2=ds===_today();
            diaSec.textContent=(isToday2?'📅 Hoy':'📅 ')+formatDateHuman(ds)+' — '+dayVisitas.length+' visita'+(dayVisitas.length!==1?'s':'');
            container.innerHTML='';
            if(!dayVisitas.length){container.innerHTML='<div class="empty"><span class="empty-ic">🏠</span>Sin visitas este día.</div>';return;}
            const overlapWrap=document.createElement('div');container.append(overlapWrap);
            checkOverlaps(dayVisitas,overlapWrap);
            dayVisitas.forEach(v=>container.append(mkVisCard(v,container)));
        }
        buildVisContent(contentEl);
    }

    function checkOverlaps(visitas,container){
        for(let i=0;i<visitas.length-1;i++){
            const a=visitas[i],b=visitas[i+1];
            if(a.estado==='Cancelada'||b.estado==='Cancelada')continue;
            const aEnd=addMinutes(a.hora,a.duracion||60);
            if(aEnd>b.hora){const warn=document.createElement('div');warn.className='overlap-warn on';warn.textContent='⚠️ Solapamiento: '+a.proNombre+' termina a las '+aEnd+' pero '+b.proNombre+' empieza a las '+b.hora;container.append(warn);}
        }
    }

    function mkVisCard(v,listEl) {
        const prop=S.props.find(x=>x.id===v.propId);
        const stBadge={'Pendiente':'vs-pendiente','Confirmada':'vs-confirmada','Realizada':'vs-realizada','Cancelada':'vs-cancelada','No se presentó':'vs-no-presentó'}[v.estado]||'vs-pendiente';
        const card=document.createElement('div');card.className='vis-card';
        const hdr=document.createElement('div');hdr.className='vis-card-hdr';
        hdr.innerHTML=`<div class="vis-time">${v.hora}</div><div class="vis-info"><div class="vis-name">${v.proNombre}</div><div class="vis-prop">🏠 ${prop?prop.name:'—'}</div><div class="vis-dur">⏱ ${v.duracion} min${v.notas?' · '+v.notas.substring(0,30):''}</div></div><span class="vis-status-badge ${stBadge}">${v.estado}</span><span style="color:#cbd5e1;font-size:14px;margin-left:4px;transition:transform .2s" class="vis-chev">▾</span>`;
        const body=document.createElement('div');body.className='vis-card-body';
        hdr.onclick=()=>{S.openVis[v.id]=!S.openVis[v.id];body.classList.toggle('on',S.openVis[v.id]);hdr.querySelector('.vis-chev').style.transform=S.openVis[v.id]?'rotate(180deg)':'';if(S.openVis[v.id])buildVisCardBody(v,body,prop,listEl);else body.innerHTML='';};
        if(S.openVis[v.id]){body.classList.add('on');buildVisCardBody(v,body,prop,listEl);}
        card.append(hdr,body);return card;
    }

    function buildVisCardBody(v,body,prop,listEl){
        body.innerHTML='';
        const propAddr=prop?prop.addr:'';
        const msgConfirm=`Hola ${v.proNombre.split(' ')[0]}, entonces quedamos el ${formatDateHuman(v.fecha)} a las ${v.hora} en ${propAddr}.\n\n¡Te esperamos! Si necesitás algo antes avisame.\n\nSaludos,\nMariano`;
        const msgLabel=document.createElement('div');msgLabel.className='nota-label';msgLabel.textContent='💬 Mensaje de confirmación — listo para copiar';
        const msgBox=document.createElement('div');msgBox.className='vis-msg-box';msgBox.textContent=msgConfirm;
        body.append(msgLabel,msgBox);
        const msgRow=document.createElement('div');msgRow.style.cssText='display:flex;gap:6px;margin-bottom:12px';
        const cpBtn=document.createElement('button');cpBtn.className='btn-cas';cpBtn.style.flex='1';cpBtn.textContent='📋 Copiar mensaje';
        cpBtn.onclick=()=>navigator.clipboard.writeText(msgConfirm).then(()=>toast('Mensaje copiado'));
        const waBtn=document.createElement('a');waBtn.className='btn-wa';waBtn.style.cssText='flex:1;padding:9px;font-size:12px;border-radius:8px;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center';
        waBtn.textContent='📱 WhatsApp';waBtn.target='_blank';
        waBtn.href='https://wa.me/'+(v.proTel||'').replace(/[\s\-\(\)]/g,'').replace(/^\+/,'')+`?text=${encodeURIComponent(msgConfirm)}`;
        msgRow.append(cpBtn,waBtn);body.append(msgRow);
        const dg=document.createElement('div');dg.className='dg';
        [['Fecha',formatDateHuman(v.fecha)],['Hora',v.hora],['Duración',v.duracion+' min'],['Termina',addMinutes(v.hora,v.duracion)],['Propiedad',prop?prop.name:'—'],['Dirección',prop?prop.addr:'—'],['Prospecto',v.proNombre],['Teléfono',v.proTel||'—']].forEach(([l,val])=>{
            const d=document.createElement('div');d.className='det';d.innerHTML=`<div class="det-l">${l}</div><div class="det-v">${val}</div>`;dg.append(d);
        });
        body.append(dg);
        if(v.notas){const nb=document.createElement('div');nb.className='nota-block';nb.innerHTML=`<div class="nota-label">Notas</div><div class="nota-text">${v.notas}</div>`;body.append(nb);}
        const estRow=document.createElement('div');estRow.className='est-fld';
        const estL=document.createElement('label');estL.textContent='Estado de la visita';
        const estSel=document.createElement('select');
        ['Pendiente','Confirmada','Realizada','Cancelada','No se presentó'].forEach(e=>{const op=document.createElement('option');op.textContent=e;op.selected=e===v.estado;estSel.append(op);});
        estSel.onchange=()=>{
            v.estado=estSel.value;
            if(v.estado==='Realizada'){const pro=S.pros.find(x=>x.id===v.proId);if(pro&&pro.estado==='Visita confirmada'){pro.estado='Visitó';persist();_savePro(pro);toast('✅ Prospecto actualizado a "Visitó"');}}
            persist();_saveVisita(v);
        };
        estRow.append(estL,estSel);body.append(estRow);
        const actRow=document.createElement('div');actRow.className='vis-actions';
        const icsBtn=document.createElement('button');icsBtn.className='btn-cas';icsBtn.textContent='📅 Exportar .ics';icsBtn.onclick=()=>exportICSVisita(v);
        const delBtn=document.createElement('button');delBtn.className='btn-danger';delBtn.textContent='🗑 Eliminar';
        delBtn.onclick=()=>{if(!confirm('¿Eliminar esta visita?'))return;S.visitas=S.visitas.filter(x=>x.id!==v.id);persist();CasAPI.remove('visitas',v.id).catch(()=>{});toast('Visita eliminada');render();};
        actRow.append(icsBtn,delBtn);body.append(actRow);
    }

    function buildVisForm(container,prefill,onSave){
        container.innerHTML='';
        const card=document.createElement('div');card.className='vis-form-card';
        const title=document.createElement('div');title.style.cssText='font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px';title.textContent='➕ Nueva visita';card.append(title);
        const fPro=document.createElement('div');fPro.className='field';
        const lPro=document.createElement('label');lPro.textContent='Prospecto';
        const sPro=document.createElement('select');sPro.id='vf-pro';
        const defOp=document.createElement('option');defOp.value='';defOp.textContent='Seleccionar prospecto...';sPro.append(defOp);
        S.pros.forEach(p=>{const op=document.createElement('option');op.value=p.id;op.textContent=p.n+' ('+p.t+')';if(prefill&&prefill.proId==p.id)op.selected=true;sPro.append(op);});
        fPro.append(lPro,sPro);card.append(fPro);
        const fProp=document.createElement('div');fProp.className='field';
        const lProp=document.createElement('label');lProp.textContent='Propiedad';
        const sProp=document.createElement('select');sProp.id='vf-prop';
        S.props.forEach(p=>{const op=document.createElement('option');op.value=p.id;op.textContent=p.name+' — '+p.addr;if((prefill&&prefill.propId===p.id)||(!prefill&&p.id===S.propAct))op.selected=true;sProp.append(op);});
        fProp.append(lProp,sProp);card.append(fProp);
        sPro.onchange=()=>{const pro=S.pros.find(x=>x.id==sPro.value);if(pro)Array.from(sProp.options).forEach(o=>{if(o.value===pro.propId)o.selected=true;});};
        const fr1=document.createElement('div');fr1.className='f-row';
        const fFecha=document.createElement('div');fFecha.className='field';const lFecha=document.createElement('label');lFecha.textContent='Fecha';
        const iFecha=document.createElement('input');iFecha.type='date';iFecha.value=prefill?prefill.fecha:S.visDia||_today();fFecha.append(lFecha,iFecha);
        const fHora=document.createElement('div');fHora.className='field';const lHora=document.createElement('label');lHora.textContent='Hora';
        const iHora=mkHoraSel('vf-hora-'+Date.now());if(prefill&&prefill.hora){Array.from(iHora.options).forEach(o=>{if(o.value===prefill.hora)o.selected=true;});}
        fHora.append(lHora,iHora);fr1.append(fFecha,fHora);card.append(fr1);
        const fDur=document.createElement('div');fDur.className='field';const lDur=document.createElement('label');lDur.textContent='Duración';
        const sDur=document.createElement('select');
        [['30','30 min'],['45','45 min'],['60','1 hora'],['90','1h 30min'],['120','2 horas']].forEach(([v,l])=>{const op=document.createElement('option');op.value=v;op.textContent=l;if((prefill&&prefill.duracion==v)||(!prefill&&v==='60'))op.selected=true;sDur.append(op);});
        fDur.append(lDur,sDur);card.append(fDur);
        const fNotas=document.createElement('div');fNotas.className='field';const lNotas=document.createElement('label');lNotas.textContent='Notas';
        const iNotas=document.createElement('textarea');iNotas.placeholder='Ej: Llevar contrato...';iNotas.value=prefill?prefill.notas||'':'';
        fNotas.append(lNotas,iNotas);card.append(fNotas);
        const overlapWarn=document.createElement('div');overlapWarn.className='overlap-warn';card.append(overlapWarn);
        function checkFormOverlap(){
            const fecha=iFecha.value;const hora=iHora.value;const dur=sDur.value;if(!fecha||!hora)return;
            const fin=addMinutes(hora,dur);
            const conflict=S.visitas.filter(v=>v.fecha===fecha&&v.estado!=='Cancelada'&&(!prefill||v.id!==prefill.id)).find(v=>{const vFin=addMinutes(v.hora,v.duracion||60);return hora<vFin&&fin>v.hora;});
            if(conflict){overlapWarn.textContent='⚠️ Solapamiento con '+conflict.proNombre+' a las '+conflict.hora;overlapWarn.classList.add('on');}
            else{overlapWarn.textContent='';overlapWarn.classList.remove('on');}
        }
        iFecha.onchange=checkFormOverlap;iHora.onchange=checkFormOverlap;sDur.onchange=checkFormOverlap;
        const saveBtn=document.createElement('button');saveBtn.className='btn-primary';saveBtn.textContent='✅ Guardar visita';
        saveBtn.onclick=()=>{
            const proId=sPro.value;const fecha=iFecha.value;const hora=iHora.value;
            if(!proId){toast('⚠️ Seleccioná un prospecto');return;}
            if(!fecha||!hora){toast('⚠️ Fecha y hora obligatorias');return;}
            const pro=S.pros.find(x=>x.id==proId);
            const vid=prefill?prefill.id:Date.now();
            const vis={id:vid,proId,proNombre:pro?pro.n:'',proTel:pro?pro.t:'',propId:sProp.value,fecha,hora,duracion:parseInt(sDur.value),notas:iNotas.value.trim(),estado:prefill?prefill.estado:'Pendiente'};
            if(prefill){const idx=S.visitas.findIndex(x=>x.id===prefill.id);if(idx>=0)S.visitas[idx]=vis;}else{S.visitas.push(vis);}
            if(pro&&['Nuevo','Interesado'].includes(pro.estado)){pro.estado='Visita confirmada';persist();_savePro(pro);}
            persist();_saveVisita(vis);S.visDia=fecha;toast('✅ Visita guardada');if(onSave)onSave();
        };
        card.append(saveBtn);container.append(card);
    }

    // ICS
    function exportICSVisita(v){const prop=S.props.find(x=>x.id===v.propId);const ics=buildICSEvent(v,prop);downloadICS(ics,'CAS_Visita_'+v.proNombre.replace(/\s/g,'_')+'_'+v.fecha+'.ics');}
    function exportICS(mode){let visitas=mode==='dia'?S.visitas.filter(v=>v.fecha===S.visDia):S.visitas;visitas=visitas.filter(v=>v.estado!=='Cancelada');if(!visitas.length){toast('No hay visitas para exportar');return;}let ics='BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//CAS//CAS//ES\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n';visitas.forEach(v=>{const prop=S.props.find(x=>x.id===v.propId);ics+=buildICSEvent(v,prop)+'\r\n';});ics+='END:VCALENDAR';downloadICS(ics,'CAS_Visitas_'+new Date().toISOString().split('T')[0]+'.ics');toast('📅 Exportado');}
    function buildICSEvent(v,prop){const dt=v.fecha.replace(/-/g,'');const ht=v.hora.replace(':','');const fin=addMinutes(v.hora,v.duracion).replace(':','');const uid='cas-'+v.id+'@casrealestate.es';const addr=prop?prop.addr:'';const desc=`Prospecto: ${v.proNombre}\\nTeléfono: ${v.proTel||'—'}\\nPropiedad: ${prop?prop.name:'—'}`;return `BEGIN:VEVENT\r\nUID:${uid}\r\nDTSTART:${dt}T${ht}00\r\nDTEND:${dt}T${fin}00\r\nSUMMARY:Visita ${v.proNombre} — ${prop?prop.name:''}\r\nLOCATION:${addr}\r\nDESCRIPTION:${desc}\r\nEND:VEVENT`;}
    function downloadICS(content,filename){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([content],{type:'text/calendar;charset=utf-8'}));a.download=filename;a.click();}

    // ── PLANTILLAS ────────────────────────────────────────────
    function vTpls(el){
        const banner=document.createElement('div');banner.className='info-banner';banner.innerHTML='✏️ Editá el <strong>nombre</strong> y el <strong>contenido</strong> de cada plantilla. [NOMBRE] se reemplaza automáticamente.';el.append(banner);
        tplData.forEach((t,i)=>{
            const isOpen=!!S.openTpls[t.id];const edited=!!S.tplEdits[t.id]||!!S.tplNameEdits?.[t.id];
            const card=document.createElement('div');card.className='tpl-card'+(isOpen?' op':'');
            const hdr=document.createElement('div');hdr.className='tpl-hdr';
            const chev=document.createElement('span');chev.className='prow-chev';chev.style.transform=isOpen?'rotate(180deg)':'';chev.textContent='▾';
            hdr.innerHTML=`<div class="tpl-num">${t.n}</div><div style="flex:1"><div class="tpl-title">${t.t}${edited?' <span class="edited-badge">✏️</span>':''}</div></div>`;
            hdr.append(chev);
            const body=document.createElement('div');body.className='tpl-body'+(isOpen?' on':'');
            hdr.onclick=()=>{S.openTpls[t.id]=!S.openTpls[t.id];body.classList.toggle('on',S.openTpls[t.id]);chev.style.transform=S.openTpls[t.id]?'rotate(180deg)':'';card.classList.toggle('op',S.openTpls[t.id]);};

            // Nombre editable
            const nameWrap=document.createElement('div');nameWrap.className='field';
            const nameLbl=document.createElement('label');nameLbl.textContent='Nombre de la plantilla';
            const nameInp=document.createElement('input');nameInp.className='tpl-name-inp';nameInp.value=t.t;nameInp.placeholder='Nombre de la plantilla...';
            nameInp.onblur=()=>{
                if(nameInp.value.trim()&&nameInp.value!==t.t){
                    tplData[i].t=nameInp.value.trim();
                    if(!S.tplNameEdits)S.tplNameEdits={};
                    S.tplNameEdits[t.id]=nameInp.value.trim();
                    hdr.querySelector('.tpl-title').textContent=nameInp.value.trim();
                    persist();_saveTpl(tplData[i]);toast('Nombre guardado');
                }
            };
            nameWrap.append(nameLbl,nameInp);body.append(nameWrap);

            const preview=document.createElement('div');preview.className='tpl-preview';preview.textContent=t.edit;
            const editArea=document.createElement('textarea');editArea.className='tpl-edit-area';editArea.value=t.edit;
            editArea.onblur=()=>{tplData[i].edit=editArea.value;S.tplEdits[t.id]=editArea.value;preview.textContent=editArea.value;persist();_saveTpl(tplData[i]);toast('Plantilla guardada');};
            body.append(preview,editArea);
            const btns=document.createElement('div');btns.className='tpl-btns';
            const cp=document.createElement('button');cp.className='tpl-btn';cp.innerHTML='📋 Copiar';
            cp.onclick=()=>{navigator.clipboard.writeText(tplData[i].edit).then(()=>{cp.className='tpl-btn copied';cp.textContent='✅ Copiado';toast('Copiado');setTimeout(()=>{cp.className='tpl-btn';cp.innerHTML='📋 Copiar';},2000);});};
            const ed=document.createElement('button');ed.className='tpl-btn';ed.innerHTML='✏️ Editar';
            ed.onclick=()=>{const isEd=editArea.classList.toggle('on');preview.style.display=isEd?'none':'';ed.className='tpl-btn'+(isEd?' editing':'');ed.textContent=isEd?'✅ Listo':'✏️ Editar';if(isEd)editArea.focus();};
            btns.append(cp,ed);
            if(edited){const re=document.createElement('button');re.className='tpl-btn restore';re.innerHTML='↩️ Original';re.onclick=()=>{if(confirm('¿Restaurar original?')){tplData[i].edit=TPLS_DEF[i].txt;tplData[i].t=TPLS_DEF[i].t;delete S.tplEdits[t.id];if(S.tplNameEdits)delete S.tplNameEdits[t.id];persist();CasAPI.save('plantillas',{id:t.id,n:t.n,t:TPLS_DEF[i].t,txt:TPLS_DEF[i].txt}).catch(()=>{});toast('Restaurada');render();}};btns.append(re);}
            body.append(btns);card.append(hdr,body);el.append(card);
        });
    }


    // ── AJUSTES ───────────────────────────────────────────────
    function vSet(el,st){
        function addSec(t){const d=document.createElement('div');d.className='set-sec';d.textContent=t;el.append(d);}
        function addDesc(t){const d=document.createElement('div');d.className='set-desc';d.textContent=t;el.append(d);}

        addSec('💾 Exportar datos'); addDesc('CSV para Excel o JSON completo para backup.');
        const er=document.createElement('div');er.className='backup-row';
        const b1=document.createElement('button');b1.className='btn-cas';b1.style.flex='1';b1.textContent='📊 CSV';b1.onclick=exportCSV;
        const b2=document.createElement('button');b2.className='btn-cas';b2.style.flex='1';b2.textContent='📥 JSON';b2.onclick=exportBackup;
        er.append(b1,b2);el.append(er);

        addSec('🏠 Propiedades'); addDesc('Propiedades activas. Podés agregar nuevas.');
        const plWrap=document.createElement('div');
        function renderPL(){
            plWrap.innerHTML='';
            S.props.forEach(prop=>{
                const cnt=S.pros.filter(x=>x.propId===prop.id).length;
                const row=document.createElement('div');row.className='prop-item'+(prop.id===S.propAct?' active':'');
                const nm=document.createElement('div');nm.className='prop-item-name';nm.textContent=prop.name;
                const ct=document.createElement('div');ct.className='prop-item-cnt';ct.textContent=cnt+' pros';
                const btns2=document.createElement('div');btns2.style.cssText='display:flex;gap:5px;margin-left:auto;flex-shrink:0';
                const selBtn=document.createElement('button');selBtn.className='btn-cas';selBtn.style.cssText='padding:3px 8px;font-size:11px';
                selBtn.textContent=prop.id===S.propAct?'✅':'Activar';
                selBtn.onclick=()=>{S.propAct=prop.id;svCache('propAct',S.propAct);renderPL();toast('Activa: '+prop.name);};
                btns2.append(selBtn);
                if(S.props.length>1){const dl=document.createElement('button');dl.className='btn-danger';dl.style.cssText='padding:3px 8px;font-size:11px';dl.textContent='🗑';dl.onclick=()=>{if(cnt>0){toast('⚠️ Reasigná los '+cnt+' pros primero');return;}if(!confirm('¿Eliminar "'+prop.name+'"?'))return;S.props=S.props.filter(x=>x.id!==prop.id);if(S.propAct===prop.id)S.propAct=S.props[0].id;persist();CasAPI.remove('propiedades',prop.id).catch(()=>{});renderPL();toast('Eliminada');};btns2.append(dl);}
                row.append(nm,ct,btns2);plWrap.append(row);
            });
        }
        renderPL();el.append(plWrap);
        const addG=document.createElement('div');addG.style.cssText='display:grid;grid-template-columns:1fr 1fr auto;gap:6px;margin-top:8px;align-items:end';
        const ainp=document.createElement('div');ainp.className='field';const al=document.createElement('label');al.textContent='Nombre';const ai=document.createElement('input');ai.type='text';ai.placeholder='Ej: Duque';ainp.append(al,ai);
        const binp=document.createElement('div');binp.className='field';const bl=document.createElement('label');bl.textContent='Descripción';const bi=document.createElement('input');bi.type='text';bi.placeholder='Ej: 900€/mes';binp.append(bl,bi);
        const addB=document.createElement('button');addB.className='btn-primary';addB.style.cssText='margin-top:0;padding:10px 12px;width:auto;font-size:12px';addB.textContent='+ Agregar';
        addB.onclick=()=>{const nm=ai.value.trim();if(!nm){toast('⚠️ Escribí el nombre');ai.focus();return;}const nid='p'+Date.now();const newProp={id:nid,name:nm,sub:bi.value.trim()||nm,addr:''};S.props.push(newProp);S.propAct=nid;ai.value='';bi.value='';persist();_saveProp(newProp);renderPL();toast('✅ Propiedad "'+nm+'" creada');};
        addG.append(ainp,binp,addB);el.append(addG);

        addSec('📊 Estadísticas');
        const stAll=getStats(null);
        const sg=document.createElement('div');sg.className='sg';
        [[stAll.total,'👥','TOTAL'],[stAll.hot,'🔥','CALIENTES'],[stAll.vis,'🏠','VISITAS'],[stAll.cerr,'🔑','CERRADOS']].forEach(([n,ic,l])=>{const sc=document.createElement('div');sc.className='sc';sc.innerHTML=`<div class="sc-ic">${ic}</div><div class="sc-n">${n}</div><div class="sc-l">${l}</div>`;sg.append(sc);});
        el.append(sg);

        const dz=document.createElement('div');dz.className='danger-zone';
        const dzT=document.createElement('div');dzT.className='danger-title';dzT.textContent='⚠️ Zona peligrosa';
        const dzB=document.createElement('button');dzB.className='btn-danger';dzB.style.width='100%';dzB.textContent='🗑 Borrar datos locales';
        dzB.onclick=()=>{if(confirm('¿BORRAR datos locales? (La DB del servidor NO se borra).')){['pros','visitas','tplEdits','props','propAct'].forEach(k=>localStorage.removeItem('cas4-'+k));location.reload();}};
        dz.append(dzT,dzB);el.append(dz);
        const ver=document.createElement('div');ver.style.cssText='text-align:center;margin-top:14px;font-size:10px;color:#cbd5e1';ver.textContent='CAS Captación v4.3 — API';el.append(ver);
    }

    function exportCSV(){
        if(!S.pros.length){toast('No hay prospectos');return;}
        const h=['Nombre','Teléfono','Email','Propiedad','Ocupación','Budget €','Entrada','Duración','Acompañado','Mascotas','Fuma','Origen','Calificación','Estado','Seguimiento','Hora','Notas','Fecha'];
        const rows=S.pros.map(p=>{const prop=S.props.find(x=>x.id===p.propId);return[p.n,p.t,p.email||'',prop?prop.name:'',p.oc||'',p.bud||'',p.ent||'',p.dur||'',p.ac||'',p.mas||'',p.fum||'',p.de||'',p.cal||'',p.estado||'',p.seguimiento||'',p.segHora||'',p.notas||'',p.fecha||''].map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',');});
        const csv=[h.join(','),...rows].join('\n');
        const a=document.createElement('a');a.href=URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8'}));a.download='CAS_Prospectos_'+new Date().toISOString().split('T')[0]+'.csv';a.click();toast('CSV descargado ✅');
    }
    function exportBackup(){
        const data=JSON.stringify({ts:new Date().toISOString(),pros:S.pros,tplEdits:S.tplEdits,props:S.props},null,2);
        const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([data],{type:'application/json'}));a.download='CAS_Backup_'+new Date().toISOString().split('T')[0]+'.json';a.click();toast('Backup exportado ✅');
    }

    return { init };
})();
