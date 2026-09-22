/**
 * CAS Seguimiento — Módulo IIFE
 * UI íntegra del original, datos vía CasAPI.
 * Ámbito privado: S, render, etc. no colisionan con Captacion.
 */
const Seguimiento = (function () {
    'use strict';

    // ── Constantes (idénticas al original) ────────────────────
    const CL = {C:"#ef4444",N:"#f59e0b",V:"#06b6d4",M:"#8b5cf6",CALL:"#3b82f6",AN:"#ec4899",MP:"#22d3ee"};
    const ST_CFG = {activo:{c:"#22c55e",bg:"#f0fdf4",l:"Activo"},pausado:{c:"#f59e0b",bg:"#fffbeb",l:"Pausado"},convertido:{c:"#6366f1",bg:"#f5f3ff",l:"Convertido"},perdido:{c:"#94a3b8",bg:"#f8fafc",l:"Perdido"}};

    const M = {
C1:{t:"C",c:"C1",ti:"Primer contacto WhatsApp",w:"Lunes — Tras 2-3 llamadas sin respuesta",tr:"Manual",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"precio_cierre",l:"Precio cierre €/m²",p:"3.216"}],tpl:"{nombre}, soy Mariano y he visto tu anuncio.\nSolo un dato: en {zona} el precio medio de cierre real este trimestre está en {precio_cierre} €/m². No de publicación. De firma ante notario.\n\nCon ese número ya puedes calcular tú mismo dónde está tu piso respecto al mercado. Cuando quieras el desglose completo, aquí estoy.",nx:"N1",nd:10,nl:"N1"},
AN1:{t:"AN",c:"AN1",ti:"Anuncio nuevo — Anti-CTA",w:"Mismo día (<48h)",tr:"Manual",f:[{k:"zona",l:"Zona",p:"Patraix"}],tpl:"{nombre}, Mariano. Sé que acabas de publicar. No te molesto con datos ahora — los datos importan cuando se necesitan.\n\nSolo una cosa que vale la pena saber desde el día uno: en {zona}, Idealista empuja los anuncios nuevos los primeros 12-15 días. Después caes en el feed. El movimiento real de las primeras dos semanas no predice nada.\n\nCuando llegues al día 20 sin oferta firme, escríbeme. No antes.",nx:"AN2",nd:20,nl:"AN2"},
AN2:{t:"AN",c:"AN2",ti:"Cinco palabras",w:"Día 18-22",tr:"Lystos",f:[],tpl:"{nombre}, ¿oferta firme ya?",nx:null,nd:null,nl:"Retoma V"},
N1:{t:"N",c:"N1",ti:"Empezaron solos, bajaron, sin visitas",w:"Día 10",tr:"Calendario",f:[],tpl:"{nombre}, muchos pisos empezaron a venderse sin agencia. Bajaron el precio una vez, luego otra. Hasta no saber qué hacer, porque se quedaron sin visitas. Bajar es lo más fácil. Solo hay que saber cuándo es el momento justo.",nx:"N2",nd:10,nl:"N2"},
N2:{t:"N",c:"N2",ti:"El comprador mira cuánto llevas",w:"Día 20",tr:"Calendario",f:[],tpl:"{nombre}, antes de pensar la oferta, el comprador mira cuánto llevas publicado, si bajaste precio, compara y empieza a hacer cuentas. Si viene asesorado, la negociación y el número son mucho peores.",nx:"N3",nd:10,nl:"N3"},
N3:{t:"N",c:"N3",ti:"Los 8 segundos de la primera visita",w:"Día 30",tr:"Calendario",f:[],tpl:"{nombre}, en la primera visita, en solo 8 segundos se define casi todo: si entra con interés o por educación, cuánto baja del precio que tenía en la cabeza, y si vale la pena negociar o tirar a matar.",nx:"N4",nd:10,nl:"N4"},
N4:{t:"N",c:"N4",ti:"Capacidad, necesidad, urgencia",w:"Día 40",tr:"Calendario",f:[],tpl:"{nombre}, antes de la visita hay que saber tres cosas: capacidad, necesidad y urgencia. Es ahí donde empieza la venta. Tres preguntas filtran al 80% de los curiosos. Una de ellas vale al menos 10.000€.",nx:"N5",nd:10,nl:"N5"},
N5:{t:"N",c:"N5",ti:"Primera, segunda y tercera oferta",w:"Día 50",tr:"Calendario",f:[],tpl:"{nombre}, con la 1ª y 2ª oferta no pasa nada. El problema es la tercera. La primera la rechazas con orgullo. Con la segunda empiezan las dudas. La tercera la firmas con alivio. Y el alivio cuesta dinero.",nx:null,nd:null,nl:"Solo V+M"},
V1:{t:"V",c:"V1",ti:"Nuevas propiedades y vendidas",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"n_nuevos",l:"Nuevos",p:"12"},{k:"n_vendidos",l:"Vendidos",p:"5"}],tpl:"{nombre}, en {zona} esta semana: {n_nuevos} pisos nuevos publicados, {n_vendidos} vendidos.\n\nMás entran de los que salen. Eso significa que cada semana tu anuncio compite con más pisos por los mismos compradores.\n\nEl que compra no espera a que bajes. Elige entre los que ya están.",nx:"V2",nd:7,nl:"V2"},
V2:{t:"V",c:"V2",ti:"Bajadas de precio + vendidos",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"n_bajadas",l:"Bajadas",p:"8"},{k:"n_vendidos",l:"Vendidos",p:"4"}],tpl:"{nombre}, en {zona} esta semana {n_bajadas} propietarios bajaron el precio de su anuncio. Y {n_vendidos} pisos se vendieron.\n\nLo interesante es que rara vez son los mismos. Los que bajan suelen seguir publicados. Los que se venden muchas veces no habían bajado.\n\nBajar el precio y vender bien no siempre van de la mano. A veces es otra cosa.",nx:"V3",nd:7,nl:"V3"},
V3:{t:"V",c:"V3",ti:"Días en mercado promedio",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"dias_media",l:"Días media",p:"45"}],tpl:"{nombre}, va un dato de {zona} este mes: un piso tarda de media {dias_media} días en venderse desde que se publica.\n\nEsa cifra incluye los que se venden bien y los que se malvenden por agotamiento. La media no distingue.\n\nLo que sí distingue es lo que pasa a partir del día 30: cada semana extra publicado baja el precio final de cierre. No porque el piso valga menos, sino porque el comprador sabe el tiempo que llevas a la venta.",nx:"V4",nd:7,nl:"V4"},
V4:{t:"V",c:"V4",ti:"Brecha publicación vs cierre",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"precio_pub",l:"Pub €/m²",p:"3.800"},{k:"precio_cierre",l:"Cierre €/m²",p:"3.216"},{k:"diferencia",l:"% dif",p:"15,4"}],tpl:"{nombre}, en {zona} el precio medio de publicación es {precio_pub} €/m². El precio medio de cierre real — lo que se firma ante notario — es {precio_cierre} €/m².\n\nDiferencia: {diferencia}%.\n\nEso significa que el propietario medio en tu zona cree que su piso vale bastante más de lo que el mercado acaba pagando. Y esa diferencia no se descubre al publicar. Se descubre al negociar.",nx:"V5",nd:7,nl:"V5"},
V5:{t:"V",c:"V5",ti:"Republicaciones del mes",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"n_repub",l:"Republicaciones",p:"14"}],tpl:"{nombre}, este mes en {zona}: {n_repub} anuncios republicados.\n\nRepublicar es borrar el anuncio y volver a subirlo para que Idealista lo trate como nuevo. Funciona unos días. Después el algoritmo lo detecta y lo empuja al fondo igual que antes.\n\nCuando ves muchas republicaciones en una zona, lo que estás viendo es a muchos propietarios intentando el mismo atajo. Y el comprador que mira todos los días, lo nota en un segundo.",nx:"V6",nd:7,nl:"V6"},
V6:{t:"V",c:"V6",ti:"Agencias por anuncio (media)",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"n_agencias",l:"Media agencias",p:"2,3"}],tpl:"{nombre}, en {zona} los pisos en venta tienen de media {n_agencias} agencias publicándolos.\n\nParece que más agencias = más alcance y lo que pasa en la práctica es lo contrario: el comprador ve el mismo piso a precios distintos, no sabe cuál es el real, y usa la diferencia para negociar a la baja.\n\nAhí las agencias siempre juegan a favor del comprador.",nx:"V7",nd:7,nl:"V7"},
V7:{t:"V",c:"V7",ti:"Tendencia precio/m² trimestral",w:"Viernes 17-18h",tr:"Envío zona",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"precio_antes",l:"€/m² 3m atrás",p:"3.150"},{k:"precio_ahora",l:"€/m² hoy",p:"3.216"},{k:"tendencia",l:"Tendencia",p:"Subida"},{k:"variacion",l:"% var",p:"2,1"}],tpl:"{nombre}, precio por m² en {zona} hace 3 meses: {precio_antes} €. Hoy: {precio_ahora} €.\n\n{tendencia} del {variacion}% en el trimestre.\n\nCuando el mercado sube, esperar tiene lógica. Cuando está estable o baja, cada mes que pasa juega en contra del precio de cierre.",nx:"V1",nd:7,nl:"V1 reinicia"},
M1:{t:"M",c:"M1",ti:"Espejo del piso",w:"Mismo día llamada",tr:"Manual",f:[{k:"zona",l:"Zona",p:"Algirós"},{k:"precio_min",l:"Franja mín €",p:"280.000"},{k:"precio_max",l:"Franja máx €",p:"320.000"},{k:"precio_actual",l:"Precio actual €",p:"434.000"}],tpl:"{nombre}, Mariano. Te dejo lo que te dije.\n\n[imagen — cierres reales últimos 90 días en {zona}]\n\nTu piso, por m² y estado, encaja en la franja de {precio_min}€ a {precio_max}€ de firma real. No de publicación. De firma.\n\nTú lo tienes a {precio_actual}. Eso significa una de dos cosas: o tienes margen real para aguantar, o vas a recibir ofertas que te van a parecer ofensivas las próximas semanas.\n\nUna de las dos es. Y se nota en la tercera visita.",nx:"M2",nd:15,nl:"M2 si Lystos detecta evento"},
M2:{t:"M",c:"M2",ti:"Dejaron de mirarlo",w:"Republicación o 15+ días",tr:"Lystos",f:[{k:"zona",l:"Zona",p:"Algirós"}],tpl:"{nombre}, en {zona} el comprador con capacidad real revisa Idealista varias veces por semana.\n\nEso significa que muchos ya vieron tu piso más de una vez.\n\nCuando un anuncio pasa tiempo sin cambios, el comprador suele asumir una de dos: o que el precio está lejos, o que el propietario no tiene intención real de moverse.\n\nY desde ahí, deja de prestarle atención.",nx:null,nd:null,nl:"Siguiente M por Lystos"},
M3:{t:"M",c:"M3",ti:"Cabeza del comprador perdido",w:"Primera bajada",tr:"Lystos",f:[{k:"bajada",l:"€ bajados",p:"15.000"}],tpl:"{nombre}, has bajado {bajada}€. Te cuento lo que está pasando ahora mismo en la cabeza del que llamó hace tres semanas y no volvió.\n\nLo ve. Ve la bajada. Y piensa: \"esperé bien, va a bajar otra vez\".\n\nY ya no llama. Espera.\n\nEl problema de la primera bajada no es que sea poca o mucha. Es que enseña al mercado que estás dispuesto a bajar. Y a partir de ahí, el que tiene dinero deja de negociar contigo y empieza a negociar con tu paciencia.",nx:null,nd:null,nl:"Siguiente M por Lystos"},
M4:{t:"M",c:"M4",ti:"Te están usando",w:"2ª agencia entra",tr:"Lystos",f:[{k:"agencia",l:"Agencia",p:"Tecnocasa"},{k:"precio_agencia",l:"Precio agencia",p:"410.000"},{k:"precio_tuyo",l:"Tu precio",p:"434.000"},{k:"diff",l:"Diferencia €",p:"24.000"}],tpl:"{nombre}, veo a {agencia} publicando tu piso a {precio_agencia}. Tú a {precio_tuyo}. Diferencia: {diff}€.\n\nLo que el comprador hace cuando ve esto: llama al precio más bajo. Visita por el más bajo. Y luego negocia diciendo que lo ha visto a menos en otra agencia.\n\nTu propio anuncio se está usando para bajarte el precio. Y no es la agencia la que pierde en esa negociación. Eres tú.\n\nQue estén dos no te da más alcance. Te da menos margen.",nx:null,nd:null,nl:"Siguiente M por Lystos"},
M5:{t:"M",c:"M5",ti:"Caso espejo",w:"30+ días o bajada >5%",tr:"Lystos",f:[{k:"barrio",l:"Barrio caso",p:"Benimaclet"},{k:"dias_caso",l:"Días",p:"47"},{k:"bajadas_caso",l:"Bajadas",p:"2"},{k:"precio_cierre_caso",l:"Cierre €",p:"264.000"}],tpl:"{nombre}, te cuento algo, no para venderte. Para que tengas el dato.\n\nHace dos meses un propietario en {barrio} estaba donde estás tú. {dias_caso} días publicado, {bajadas_caso} bajadas, y la sensación de que la próxima oferta iba a ser una miseria.\n\nMe llamó por la segunda oferta. La primera la había rechazado y se había arrepentido.\n\nFirmamos en {precio_cierre_caso}€. Pero el dato importante no es ese. Es que el comprador con el que firmamos llevaba siete semanas viéndolo. Estaba ahí desde el principio.\n\nA veces el comprador correcto ya ha visto el piso. Solo necesita un motivo distinto para volver.",nx:null,nd:null,nl:"Siguiente M por Lystos"},
M6:{t:"M",c:"M6",ti:"El guion del comprador",w:"3ª bajada o 60+ días",tr:"Lystos",f:[{k:"dias",l:"Días",p:"67"},{k:"bajadas",l:"Bajadas",p:"3"}],tpl:"{nombre}, no es un mensaje para que respondas. Es para que lo tengas escrito antes de que pase.\n\nCon {dias} días y {bajadas} bajadas, lo que viene en las próximas dos o tres semanas suele ser esto, por orden:\n\nPrimero, una oferta entre un 12% y un 15% por debajo de tu precio actual, hecha con prisa fingida. No es verdad. Es técnica.\n\nSegundo, dos o tres semanas de silencio absoluto después de rechazarla.\n\nTercero, una segunda oferta del mismo comprador, un poco más alta, presentada como esfuerzo final.\n\nSi te pasa así, no es casualidad ni mala suerte. Es el guion. Y el guion lo escribe el que sabe que llevas tiempo.\n\nNo me contestes. Solo guárdalo.",nx:null,nd:null,nl:"Seguir con cada evento Lystos"},
MP1:{t:"MP",c:"MP1",ti:"Qué cambia cuando una venta se trabaja bien",w:"Tras señal de apertura",tr:"Manual",f:[],tpl:"{nombre}, la diferencia entre publicar un piso y trabajar una venta no se ve en el anuncio.\n\nSe ve en qué comprador entra primero al piso, en cómo se prepara la visita, en qué información se da y cuál se reserva, en cómo se manejan los silencios, y en qué momento exacto se negocia.\n\nAhí es donde normalmente se ganan o se pierden decenas de miles de euros. No en el precio de publicación.",nx:null,nd:null,nl:"Manual"}
    };

    const SEQ = ["C1","N1","N2","N3","N4","N5"];
    const V_O  = ["V1","V2","V3","V4","V5","V6","V7"];
    const AN_O = ["AN1","AN2"];

    // ── Estado privado ─────────────────────────────────────────
    let _ct;
    let _toast, _toastTimer;

    let S = {
        vw:"hoy", om:null, fl:"all", sr:"", psr:"", pfl:"activo", modal:null,
        pros:[], aid:null, fd:{}, met:{sent:0,responded:0,visits:0},
        ct:{}, mr:{}, sl:[], agenda:[],
        _em:null, _dueOpen:false, _newOpen:false, _newSr:"", _bM:null,
        _hoyFl:"hoy", _selMsg:{}, _addOpen:false
    };

    // ── Cache local ────────────────────────────────────────────
    function ldCache(k,d){try{const v=localStorage.getItem("cas-"+k);return v?JSON.parse(v):d;}catch(e){return d;}}
    function svCache(k,v){try{localStorage.setItem("cas-"+k,JSON.stringify(v));}catch(e){}}

    function persist(){
        svCache("p",S.pros);svCache("aid",S.aid);svCache("fd",S.fd);
        svCache("met",S.met);svCache("tpl",S.ct);svCache("mr",S.mr);
        svCache("sl",S.sl);svCache("agenda",S.agenda||[]);
    }

    function _savePro(p){ CasAPI.save('propietarios',p).catch(()=>{}); }
    function _saveMeta(clave,valor){ CasAPI.saveMeta(clave,valor).catch(()=>{}); }

    // ── Helpers ────────────────────────────────────────────────
    function ga(){return S.pros.find(p=>p.id===S.aid)||null;}
    function uid(){return Date.now().toString(36)+Math.random().toString(36).slice(2,6);}
    function addD(d,n){const r=new Date(d);r.setDate(r.getDate()+n);return r;}
    function fD(d){const ds=["Dom","Lun","Mar","Mié","Jue","Vie","Sáb"],ms=["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"];return`${ds[d.getDay()]} ${d.getDate()} ${ms[d.getMonth()]}`;}
    function nFri(f){const d=new Date(f);const dy=d.getDay();const df=dy<=5?5-dy:6;d.setDate(d.getDate()+(df===0?7:df));return d;}
    function bm(tpl,v,nm){let m=tpl.replace(/\{nombre\}/g,nm||"[Nombre]");Object.entries(v||{}).forEach(([k,val])=>{m=m.replace(new RegExp(`\\{${k}\\}`,"g"),val||`[${k}]`)});return m;}
    function swP(id){S.aid=id;S.fd={};const a=S.pros.find(p=>p.id===id);if(a?.zona){Object.values(M).forEach(m=>{if(m.f.some(f=>f.k==="zona"))S.fd[m.c]={zona:a.zona}});}persist();_saveMeta('aid',id);_saveMeta('fd',S.fd);}
    function gfv(c){const fv={...(S.fd[c]||{})};const a=ga();if(a?.zona&&M[c]?.f.some(f=>f.k==="zona")&&!fv.zona){fv.zona=a.zona;if(!S.fd[c])S.fd[c]={};S.fd[c].zona=a.zona;persist();}return fv;}
    function toast(msg){if(!_toast)return;clearTimeout(_toastTimer);_toast.textContent=msg;_toast.classList.add('show');_toastTimer=setTimeout(()=>_toast.classList.remove('show'),2400);}

    function sortP(l){return[...l].sort((a,b)=>{const s={activo:0,pausado:1,convertido:2,perdido:3};return s[a.status]-s[b.status]});}

    function gdue(){
        const td=new Date().toISOString().split("T")[0];const d=[];
        S.pros.filter(p=>p.status==="activo").forEach(p=>{
            if(!p.sentMsgs?.length||!p.lastSentDate)return;
            const l=p.sentMsgs[p.sentMsgs.length-1];const msg=M[l];
            if(!msg?.nx||!msg.nd)return;
            const dd=addD(new Date(p.lastSentDate),msg.nd);
            if(dd.toISOString().split("T")[0]<=td)d.push({p,nc:msg.nx,dd});
        });
        return d;
    }
    function gnew(){return S.pros.filter(p=>p.status==="activo"&&(!p.sentMsgs||!p.sentMsgs.length));}
    function gproximos(){
        const td=new Date();td.setHours(0,0,0,0);const limit=new Date(td);limit.setDate(limit.getDate()+7);const res=[];
        S.pros.filter(p=>p.status==="activo").forEach(p=>{
            if(!p.sentMsgs?.length||!p.lastSentDate)return;
            const l=p.sentMsgs[p.sentMsgs.length-1];const msg=M[l];if(!msg?.nx||!msg.nd)return;
            const dd=addD(new Date(p.lastSentDate),msg.nd);dd.setHours(0,0,0,0);
            if(dd>td&&dd<=limit)res.push({p,nc:msg.nx,dd});
        });
        return res.sort((a,b)=>a.dd-b.dd);
    }
    function recMsg(p){
        if(!p.sentMsgs||!p.sentMsgs.length)return p.route==="B"?"AN1":"C1";
        const l=p.sentMsgs[p.sentMsgs.length-1];const msg=M[l];if(msg?.nx)return msg.nx;
        const vSent=V_O.filter(v=>(p.sentMsgs||[]).includes(v));
        if(vSent.length){const last=vSent[vSent.length-1];const idx=V_O.indexOf(last);return V_O[(idx+1)%V_O.length];}
        return "V1";
    }
    function saveAgenda(item){
        if(!S.agenda)S.agenda=[];
        S.agenda=S.agenda.filter(x=>!(x.pid===item.pid&&x.msgCode===item.msgCode));
        S.agenda.push(item);svCache("agenda",S.agenda);_saveMeta('agenda',S.agenda);
    }
    function getAgendaHoy(){
        if(!S.agenda)S.agenda=[];
        const td=new Date().toISOString().split("T")[0];
        return S.agenda.filter(x=>x.fecha===td);
    }

    // ── h() helper ─────────────────────────────────────────────
    function h(tag,a,...ch){
        const el=document.createElement(tag);
        if(a)Object.entries(a).forEach(([k,v])=>{
            if(k==="style"&&typeof v==="object")Object.assign(el.style,v);
            else if(k.startsWith("on"))el.addEventListener(k.slice(2).toLowerCase(),v);
            else if(k==="className")el.className=v;
            else el.setAttribute(k,v);
        });
        ch.flat(9).forEach(c=>{if(c!=null)el.append(typeof c==="string"?document.createTextNode(c):c)});
        return el;
    }

    // ── Init ──────────────────────────────────────────────────
    async function init(containerEl) {
        _ct = containerEl;
        _ct.innerHTML = '<div class="cas-loading">⏳ Cargando Seguimiento...</div>';

        _toast = document.createElement('div');
        _toast.className = 'toast';
        _ct.appendChild(_toast);

        try {
            const [pros, meta] = await Promise.all([
                CasAPI.get('propietarios'),
                CasAPI.get('seguimiento_meta')
            ]);
            S.pros = pros || [];
            if (meta) {
                if (meta.met)    S.met    = meta.met;
                if (meta.ct)     S.ct     = meta.ct;
                if (meta.mr)     S.mr     = meta.mr;
                if (meta.sl)     S.sl     = meta.sl;
                if (meta.fd)     S.fd     = meta.fd;
                if (meta.aid)    S.aid    = meta.aid;
                if (meta.agenda) S.agenda = meta.agenda;
            }
            // Aplicar textos custom a mensajes
            Object.entries(S.ct).forEach(([c,t])=>{if(M[c])M[c].tpl=t;});
            // Normalizar pros
            S.pros.forEach(p=>{
                if(!p.status)p.status="activo";
                if(!p.createdAt)p.createdAt=new Date().toISOString().split("T")[0];
                if(!p.notes)p.notes="";
                if(!p.reactions)p.reactions={};
                if(!p.sentMsgs)p.sentMsgs=[];
            });
            persist();
        } catch (err) {
            S.pros    = ldCache("p",   []);
            S.met     = ldCache("met", {sent:0,responded:0,visits:0});
            S.ct      = ldCache("tpl", {});
            S.mr      = ldCache("mr",  {});
            S.sl      = ldCache("sl",  []);
            S.fd      = ldCache("fd",  {});
            S.aid     = ldCache("aid", null);
            S.agenda  = ldCache("agenda", []);
            Object.entries(S.ct).forEach(([c,t])=>{if(M[c])M[c].tpl=t;});
            toast('📵 Sin conexión — datos locales');
        }
        render();

        CasAPI.onReconnect(async ()=>{
            try{
                const [pros,meta]=await Promise.all([CasAPI.get('propietarios'),CasAPI.get('seguimiento_meta')]);
                S.pros=pros||S.pros;if(meta&&meta.sl)S.sl=meta.sl;persist();render();
                toast('🔄 Sincronizado');
            }catch(_){}
        });
    }

    // ── RENDER ─────────────────────────────────────────────────
    function render() {
        const app = _ct;
        Array.from(app.children).forEach(c=>{if(!c.classList.contains('toast'))c.remove();});

        const due=gdue(),newPs=gnew();
        const urgentes=due.length+newPs.length;

        const hdr=h("div",{className:"hdr-seg"});
        hdr.append(h("div",{className:"hdr-top"},
            h("div",null,
                h("div",{className:"hdr-sub"},"CAS Real Estate · Valencia"),
                h("div",{className:"hdr-title"},"Sistema de Seguimiento")),
            h("div",{style:{display:"flex",gap:"6px",alignItems:"center"}},
                h("button",{style:{background:"rgba(255,255,255,.2)",borderRadius:"6px",padding:"5px 8px",fontSize:"11px",color:"#fff",fontWeight:"600"},onClick:exportBackup},"💾"),
                ga()?h("div",{style:{background:"rgba(255,255,255,.25)",borderRadius:"6px",padding:"4px 10px",fontSize:"12px",color:"#fff",fontWeight:"600",cursor:"pointer"},onClick:()=>{S.modal=S.aid;render();}},ga().nombre):"")));

        const tabs=h("div",{className:"tabs"});
        [["hoy","🎯","Hoy"+(urgentes?` ${urgentes}`:"")],["prospectos","👥","Prosp."],["mensajes","💬","Mensajes"],["viernes","📡","Viernes"],["mas","⋯","Más"]].forEach(([id,icon,label])=>{
            tabs.append(h("button",{className:"tab"+(S.vw===id?" on":""),onClick:()=>{S.vw=id;S.sr="";S.psr="";render();}},
                h("span",{className:"ticon"},icon),label));
        });
        hdr.append(tabs);

        app.prepend(hdr);
        const ct=h("div",{className:"ct"});app.append(ct);

        if(S.modal){renderModal(app);return;}
        ({hoy:vHoy,prospectos:vProspectos,mensajes:vMensajes,viernes:vViernes,mas:vMas})[S.vw]?.(ct);
    }

    // ── HOY ────────────────────────────────────────────────────
    function vHoy(el){
        const due=gdue(),newPs=gnew(),prox=gproximos();
        const esViernes=new Date().getDay()===5;
        const agendaHoy=getAgendaHoy();
        const activos=S.pros.filter(p=>p.status==="activo");
        const conMsj=activos.filter(p=>(p.sentMsgs||[]).length>0);
        const pct=activos.length?Math.round((conMsj.length/activos.length)*100):0;
        const enviSemana=(S.sl||[]).filter(x=>{const d=new Date(x.d),now=new Date();const wd=now.getDay()||7;const mon=new Date(now);mon.setDate(now.getDate()-wd+1);mon.setHours(0,0,0,0);return d>=mon;}).length;

        el.append(h("div",{className:"mini-dash"},
            h("div",{className:"mini-dash-card",style:{borderColor:"#6366f130"}},h("div",{className:"mini-dash-num",style:{color:"#6366f1"}},String(enviSemana)),h("div",{className:"mini-dash-lbl"},"Enviados\nesta sem")),
            h("div",{className:"mini-dash-card",style:{borderColor:due.length+newPs.length>0?"#ef444430":"#22c55e30"}},h("div",{className:"mini-dash-num",style:{color:due.length+newPs.length>0?"#ef4444":"#22c55e"}},String(due.length+newPs.length)),h("div",{className:"mini-dash-lbl"},"Pendientes\nhoy")),
            h("div",{className:"mini-dash-card",style:{borderColor:"#06b6d430"}},h("div",{className:"mini-dash-num",style:{color:"#0891b2"}},pct+"%"),h("div",{className:"mini-dash-lbl"},"Cartera\nen secuencia"))));

        el.append(h("div",{className:"hoy-filter"},
            h("button",{className:S._hoyFl==="hoy"?"on":"",onClick:()=>{S._hoyFl="hoy";render();}},"🎯 Hoy "+(due.length+newPs.length>0?"("+(due.length+newPs.length)+")":"")),
            h("button",{className:S._hoyFl==="vencidos"?"on":"",onClick:()=>{S._hoyFl="vencidos";render();}},"⏰ Vencidos "+(due.length?"("+due.length+")":"")),
            h("button",{className:S._hoyFl==="proximos"?"on":"",onClick:()=>{S._hoyFl="proximos";render();}},"📅 Próximos "+(prox.length?"("+prox.length+")":""))));

        if(S._hoyFl==="hoy"){
            if(!due.length&&!newPs.length&&!esViernes&&!agendaHoy.length){
                el.append(h("div",{style:{textAlign:"center",padding:"36px 20px",background:"#fff",border:"2px solid #86efac",borderRadius:"16px"}},h("div",{style:{fontSize:"44px",marginBottom:"10px"}},"✅"),h("div",{style:{fontSize:"17px",fontWeight:"700",color:"#15803d",marginBottom:"4px"}},"¡Al día!"),h("div",{style:{fontSize:"12px",color:"#64748b"}},"No hay mensajes pendientes hoy.")));
                return;
            }
            if(agendaHoy.length){el.append(mkSeccion("📌 Agendados para hoy","#8b5cf6"));agendaHoy.forEach(ag=>{const p=S.pros.find(x=>x.id===ag.pid);if(!p)return;const msg=M[ag.msgCode];if(!msg)return;mkTareaCard(el,p,ag.msgCode,msg,"agenda");});}
            if(due.length){el.append(mkSeccion("🔔 Vencidos","#ef4444"));due.forEach(d=>mkTareaCard(el,d.p,d.nc,M[d.nc],"due"));}
            if(newPs.length){
                el.append(mkSeccion("🆕 Sin primer contacto ("+newPs.length+")","#f59e0b"));
                const sq=h("input",{placeholder:"🔍 Filtrar...",value:S._newSr||"",style:{marginBottom:"8px",width:"100%",padding:"9px 12px",background:"#fff",border:"2px solid #fbbf24",borderRadius:"8px",fontSize:"13px",outline:"none"}});
                sq.addEventListener("input",e=>{S._newSr=e.target.value;renderNewPs(newPsContainer);});
                el.append(sq);
                const newPsContainer=h("div");el.append(newPsContainer);
                function renderNewPs(ct2){ct2.innerHTML="";const q=(S._newSr||"").toLowerCase().trim();const filtered=q?newPs.filter(p=>p.nombre.toLowerCase().includes(q)):newPs;filtered.forEach(p=>{const recCode=recMsg(p);const selectedCode=S._selMsg[p.id]||recCode;mkTareaCard(ct2,p,selectedCode,M[selectedCode],"new");});}
                renderNewPs(newPsContainer);
            }
            if(esViernes)el.append(mkViernesReminder());
        }
        if(S._hoyFl==="vencidos"){if(!due.length){el.append(h("div",{style:{textAlign:"center",padding:"32px",color:"#94a3b8",fontSize:"13px"}},"Sin mensajes vencidos ✅"));return;}due.forEach(d=>mkTareaCard(el,d.p,d.nc,M[d.nc],"due"));}
        if(S._hoyFl==="proximos"){
            if(!prox.length&&!newPs.length){el.append(h("div",{style:{textAlign:"center",padding:"32px",color:"#94a3b8",fontSize:"13px"}},"Sin próximos en 7 días"));return;}
            if(prox.length){el.append(mkSeccion("📅 Próximos 7 días","#06b6d4"));prox.forEach(d=>{el.append(h("div",{style:{background:"#fff",border:"2px solid #67e8f9",borderRadius:"10px",padding:"12px 14px",marginBottom:"8px",display:"flex",alignItems:"center",gap:"12px"}},h("div",{style:{flex:"1"}},h("div",{style:{fontSize:"14px",fontWeight:"700",color:"#1e293b"}},d.p.nombre),h("div",{style:{fontSize:"11px",color:"#64748b",marginTop:"2px"}},(d.p.zona||"")+" · "+(d.p.telefono||""))),h("div",{style:{textAlign:"right",flexShrink:"0"}},h("div",{style:{fontSize:"13px",fontWeight:"800",color:"#0891b2"}},fD(d.dd)),h("span",{className:"badge",style:{background:CL[M[d.nc]?.t||"C"],fontSize:"10px",marginTop:"3px"}},d.nc))));});}
        }
    }

    function mkSeccion(txt,color){return h("div",{style:{fontSize:"11px",fontWeight:"800",color:color,textTransform:"uppercase",letterSpacing:"1px",marginBottom:"8px",marginTop:"4px"}},txt);}
    function mkViernesReminder(){return h("div",{className:"vzone",style:{marginTop:"12px"},onClick:()=>{S.vw="viernes";render();}},h("span",{style:{fontSize:"22px"}},"📡"),h("div",{style:{flex:"1"}},h("div",{style:{fontSize:"14px",fontWeight:"700",color:"#0891b2"}},"Hoy es viernes — Envío de zona"),h("div",{style:{fontSize:"12px",color:"#0e7490",marginTop:"2px"}},"Mandá el mensaje de mercado semanal")),h("span",{style:{color:"#0891b2",fontSize:"18px"}},"→"));}

    function mkTareaCard(el,p,selectedCode,msg,tipo){
        if(!msg)return;
        const fv=gfv(msg.c);const nom=p.nombre;
        const ph=(p.telefono||"").replace(/\D/g,"");
        let previewText=bm(msg.tpl,fv,nom);
        const rdy=msg.f.every(f=>(fv[f.k]||"").trim())&&!!nom&&!!ph;
        const snt=(p.sentMsgs||[]).includes(msg.c);
        const borderColor=tipo==="due"?"#ef4444":tipo==="agenda"?"#8b5cf6":"#fbbf24";
        const bgColor=tipo==="due"?"#fef2f2":tipo==="agenda"?"#faf5ff":"#fffbeb";
        const card=h("div",{style:{background:bgColor,border:"2px solid "+borderColor,borderRadius:"12px",padding:"14px",marginBottom:"10px"}});

        card.append(h("div",{style:{display:"flex",alignItems:"flex-start",gap:"10px",marginBottom:"10px"}},
            h("div",{style:{flex:"1"}},
                h("div",{style:{fontSize:"15px",fontWeight:"700",color:"#1e293b"}},nom),
                h("div",{style:{fontSize:"11px",color:"#64748b",marginTop:"2px"}},(p.zona||"Sin zona")+(p.telefono?" · "+p.telefono:"")+(p.notes?" · 📝"+p.notes.substring(0,30):""))),
            snt?h("span",{style:{fontSize:"11px",color:"#22c55e",fontWeight:"700",padding:"3px 8px",background:"#f0fdf4",border:"2px solid #86efac",borderRadius:"6px"}},"✓ Enviado"):""));

        const allMsgOpts=Object.values(M).map(m=>({c:m.c,ti:m.ti,t:m.t}));
        const recCode=recMsg(p);
        const msgSel=h("div",{style:{marginBottom:"10px"}});
        msgSel.append(h("div",{style:{fontSize:"10px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase",letterSpacing:"1px",marginBottom:"5px"}},"Mensaje"+(selectedCode===recCode?" (recomendado)":"")));
        const selRow=h("div",{style:{display:"flex",gap:"6px",alignItems:"center"}});
        const msgSelect=h("select",{style:{flex:"1",fontSize:"12px",padding:"7px 10px",borderRadius:"7px",border:"2px solid "+(selectedCode===recCode?"#22c55e":"#6366f1"),background:"#fff",color:"#1e293b",fontWeight:"600"}});
        allMsgOpts.forEach(opt=>{const o=h("option",{value:opt.c},opt.c+" — "+opt.ti);if(opt.c===selectedCode)o.selected=true;msgSelect.append(o);});
        msgSelect.addEventListener("change",e=>{
            S._selMsg[p.id]=e.target.value;
            const newMsg=M[e.target.value];if(!newMsg)return;
            const newFv=gfv(newMsg.c);const newFm=bm(newMsg.tpl,newFv,nom);
            const preEl=_ct.querySelector("#seg-prev-"+p.id);if(preEl)preEl.textContent=newFm;
            const badgeEl=card.querySelector(".msg-badge");if(badgeEl){badgeEl.textContent=e.target.value;badgeEl.style.background=CL[newMsg.t]||"#94a3b8";}
            const waEl=_ct.querySelector("#seg-wa-"+p.id);if(waEl&&ph){waEl.href=`https://wa.me/${ph}?text=${encodeURIComponent(newFm)}`;waEl.textContent="WhatsApp → "+nom+" ↗";}
            const cpEl=_ct.querySelector("#seg-cp-"+p.id);if(cpEl)cpEl._m=newFm;
        });
        selRow.append(h("span",{className:"badge msg-badge",style:{background:CL[msg.t]||"#94a3b8",flexShrink:"0"}},selectedCode));
        selRow.append(msgSelect);msgSel.append(selRow);card.append(msgSel);

        if(msg.f.length){
            const gr=h("div",{className:"g2",style:{marginBottom:"10px"}});
            msg.f.forEach(f=>{
                const inp=h("input",{placeholder:f.p,value:fv[f.k]||"",style:{fontSize:"13px",borderColor:(fv[f.k]||"")?(CL[msg.t]||"#6366f1")+"60":"#e2e8f0"}});
                inp.addEventListener("input",e=>{
                    if(!S.fd[msg.c])S.fd[msg.c]={};S.fd[msg.c][f.k]=e.target.value;
                    inp.style.borderColor=e.target.value?(CL[msg.t]||"#6366f1")+"60":"#e2e8f0";
                    const curFv=S.fd[msg.c]||{};const upd=bm(msg.tpl,curFv,nom);
                    const preEl=_ct.querySelector("#seg-prev-"+p.id);if(preEl)preEl.textContent=upd;
                    const cpEl=_ct.querySelector("#seg-cp-"+p.id);if(cpEl)cpEl._m=upd;
                    const waEl=_ct.querySelector("#seg-wa-"+p.id);
                    if(waEl&&ph){const ok2=msg.f.every(ff=>(curFv[ff.k]||"").trim());if(ok2){waEl.href=`https://wa.me/${ph}?text=${encodeURIComponent(upd)}`;waEl.className="btn btn-wa";waEl.textContent="WhatsApp → "+nom+" ↗";}}
                    persist();_saveMeta('fd',S.fd);
                });
                gr.append(h("div",null,h("span",{className:"lb"},f.l),inp));
            });
            card.append(gr);
        }

        card.append(h("div",{className:"pv"},h("pre",{id:"seg-prev-"+p.id,style:{fontSize:"13px",color:"#334155",lineHeight:"1.7"}},previewText)));

        const btns=h("div",{style:{display:"flex",gap:"8px",marginTop:"10px"}});
        const cpBtn=h("button",{id:"seg-cp-"+p.id,className:"btn btn-gray",style:{flex:"1"},onClick:()=>{navigator.clipboard.writeText(cpBtn._m||previewText);cpBtn.textContent="✓ Copiado";setTimeout(()=>cpBtn.textContent="📋 Copiar",1500);}},"📋 Copiar");
        cpBtn._m=previewText;btns.append(cpBtn);
        const waLink=h("a",{id:"seg-wa-"+p.id,href:rdy?`https://wa.me/${ph}?text=${encodeURIComponent(previewText)}`:"#",
            target:rdy?"_blank":"",rel:"noopener",className:"btn "+(rdy?"btn-wa":"btn-wa-dis"),
            style:{flex:"1.4",textDecoration:"none"},
            onClick:e=>{
                if(!rdy){e.preventDefault();return;}
                const curCode=S._selMsg[p.id]||recCode;const curMsg=M[curCode];
                const curFv2=S.fd[curCode]||{};const curFm=bm(curMsg.tpl,curFv2,nom);
                if(!(p.sentMsgs||[]).includes(curCode)){
                    p.sentMsgs=p.sentMsgs||[];p.sentMsgs.push(curCode);
                    p.lastSentDate=new Date().toISOString().split("T")[0];
                    S.met.sent++;
                    if(!S.mr[curCode])S.mr[curCode]={sent:0,resp:0,noresp:0};S.mr[curCode].sent++;
                    S.sl.push({d:new Date().toISOString().split("T")[0],c:curCode,t:curMsg.t,pid:p.id,z:p.zona||""});
                    if(S.agenda)S.agenda=S.agenda.filter(x=>!(x.pid===p.id&&x.msgCode===curCode));
                    persist();_savePro(p);_saveMeta('met',S.met);_saveMeta('mr',S.mr);_saveMeta('sl',S.sl);_saveMeta('agenda',S.agenda);
                    setTimeout(()=>showProxPanel(card,p,curCode,curMsg),700);
                }
            }},rdy?"WhatsApp → "+nom+" ↗":(ph?"Faltan datos":"Sin teléfono"));
        btns.append(waLink);card.append(btns);

        if(snt){
            const reacted=p.reactions?.[msg.c];
            card.append(h("div",{style:{display:"flex",gap:"6px",marginTop:"8px"}},
                h("button",{className:"btn",style:{background:reacted==="yes"?"#f0fdf4":"#fff",color:reacted==="yes"?"#15803d":"#64748b",border:"2px solid "+(reacted==="yes"?"#86efac":"#e2e8f0"),flex:"1",fontSize:"11px"},onClick:()=>{if(!p.reactions)p.reactions={};p.reactions[msg.c]="yes";if(!S.mr[msg.c])S.mr[msg.c]={sent:0,resp:0,noresp:0};S.mr[msg.c].resp++;persist();_savePro(p);_saveMeta('mr',S.mr);render();}},"✅ Respondió"),
                h("button",{className:"btn",style:{background:reacted==="no"?"#fef2f2":"#fff",color:reacted==="no"?"#ef4444":"#64748b",border:"2px solid "+(reacted==="no"?"#fca5a5":"#e2e8f0"),flex:"1",fontSize:"11px"},onClick:()=>{if(!p.reactions)p.reactions={};p.reactions[msg.c]="no";if(!S.mr[msg.c])S.mr[msg.c]={sent:0,resp:0,noresp:0};S.mr[msg.c].noresp++;persist();_savePro(p);_saveMeta('mr',S.mr);render();}},"❌ Sin respuesta")));
        }
        el.append(card);
    }

    function showProxPanel(card,p,sentCode,sentMsg){
        const ex=card.querySelector(".prox-panel");if(ex)ex.remove();
        const panel=h("div",{className:"prox-agenda prox-panel",style:{marginTop:"12px"}});
        const nextCode=sentMsg.nx;const nextMsg=nextCode?M[nextCode]:null;
        panel.append(h("div",{style:{fontSize:"12px",fontWeight:"800",color:"#0891b2",marginBottom:"10px"}},"📅 PRÓXIMO CONTACTO"));
        if(nextMsg){
            const base=new Date();const nd=sentMsg.nd||7;const sugg=addD(base,nd);
            panel.append(h("div",{style:{display:"flex",alignItems:"center",gap:"8px",marginBottom:"10px"}},h("span",{className:"badge",style:{background:CL[nextMsg.t]}},nextCode),h("div",{style:{flex:"1"}},h("div",{style:{fontSize:"13px",fontWeight:"700",color:"#0e7490"}},nextMsg.ti),h("div",{style:{fontSize:"11px",color:"#64748b",marginTop:"2px"}},"Sugerido: "+fD(sugg)+" (en "+nd+" días)"))));
            const fechaInp=h("input",{type:"date",value:sugg.toISOString().split("T")[0],style:{marginBottom:"6px",fontSize:"13px"}});
            const horaInp=h("input",{type:"time",value:"10:00",style:{marginBottom:"8px",fontSize:"13px"}});
            const notaInp=h("input",{placeholder:"Nota opcional",style:{marginBottom:"10px",fontSize:"13px"}});
            panel.append(h("div",{className:"g2",style:{gap:"6px"}},h("div",null,h("span",{className:"lb"},"Fecha"),fechaInp),h("div",null,h("span",{className:"lb"},"Hora"),horaInp)));
            panel.append(h("span",{className:"lb"},"Nota"),notaInp);
            panel.append(h("div",{style:{display:"flex",gap:"8px"}},
                h("button",{className:"btn btn-ind",style:{flex:"1"},onClick:()=>{
                    saveAgenda({pid:p.id,msgCode:nextCode,fecha:fechaInp.value,hora:horaInp.value,nota:notaInp.value,nombre:p.nombre});
                    panel.innerHTML="";panel.append(h("div",{style:{textAlign:"center",padding:"12px",color:"#0891b2",fontWeight:"700",fontSize:"13px"}},"✅ Agendado: "+nextCode+" para "+fechaInp.value));
                }},"📌 Agendar"),
                h("button",{className:"btn btn-gray",style:{flex:"1"},onClick:()=>panel.remove()},"Omitir")));
        }else{
            panel.append(h("div",{style:{fontSize:"13px",color:"#64748b"}},"Secuencia N completada. Continuar con V viernes + M Lystos."));
            panel.append(h("button",{className:"btn btn-gray",style:{marginTop:"8px",width:"100%"},onClick:()=>panel.remove()},"Entendido"));
        }
        card.append(panel);
    }

    // ── PROSPECTOS ────────────────────────────────────────────
    function vProspectos(el){
        const due=gdue();const dueIds=new Set(due.map(d=>d.p.id));
        const ac=S.pros.filter(p=>p.status==="activo").length;
        const cv=S.pros.filter(p=>p.status==="convertido").length;
        const pa=S.pros.filter(p=>p.status==="pausado").length;

        el.append(h("div",{className:"g3",style:{marginBottom:"10px"}},
            ...[["#22c55e","#f0fdf4",ac,"Activos","activo"],["#6366f1","#f5f3ff",cv,"Convertidos","convertido"],["#f59e0b","#fffbeb",pa,"Pausados","pausado"]].map(([c,bg,v,l,fl])=>
                h("div",{style:{background:bg,border:"2px solid "+c+(S.pfl===fl?"80":"30"),borderRadius:"8px",padding:"8px",textAlign:"center",cursor:"pointer",outline:S.pfl===fl?"3px solid "+c:"none"},onClick:()=>{S.pfl=fl;render();}},
                    h("div",{style:{fontSize:"18px",fontWeight:"800",color:c}},String(v)),
                    h("div",{style:{fontSize:"9px",fontWeight:"700",color:c,textTransform:"uppercase",letterSpacing:"1px"}},l)))));

        const toolRow=h("div",{style:{display:"flex",gap:"6px",marginBottom:"8px",alignItems:"center"}});
        const si=h("input",{style:{flex:"1",padding:"9px 12px",background:"#fff",border:"2px solid #e2e8f0",borderRadius:"8px",fontSize:"13px",outline:"none"},placeholder:"🔍 Nombre, teléfono o zona...",value:S.psr});
        si.addEventListener("input",e=>{S.psr=e.target.value;render();});
        toolRow.append(si);
        toolRow.append(h("button",{className:"btn btn-ind",style:{padding:"9px 14px",fontSize:"12px",whiteSpace:"nowrap",flexShrink:"0"},onClick:()=>{S._addOpen=!S._addOpen;render();}},"➕ Añadir"));
        el.append(toolRow);

        const fl=h("div",{style:{display:"flex",gap:"5px",marginBottom:"10px",flexWrap:"wrap"}});
        [["activo","⚡ Activos"],["pausado","Pausados"],["convertido","Convertidos"],["perdido","Perdidos"],["all","Todos"]].forEach(([id,l])=>{fl.append(h("button",{className:"chip"+(S.pfl===id?" on":""),onClick:()=>{S.pfl=id;render();}},l));});
        el.append(fl);

        if(S._addOpen){
            const form=h("div",{style:{background:"#f5f3ff",border:"2px solid #c4b5fd",borderRadius:"10px",padding:"12px 14px",marginBottom:"10px"}});
            form.append(h("div",{className:"g2",style:{marginBottom:"8px"}},
                h("div",null,h("span",{className:"lb"},"Nombre *"),h("input",{id:"seg-pN",placeholder:"José"})),
                h("div",null,h("span",{className:"lb"},"Teléfono"),h("input",{id:"seg-pT",placeholder:"34612345678",type:"tel"})),
                h("div",null,h("span",{className:"lb"},"Zona"),h("input",{id:"seg-pZ",placeholder:"Algirós"})),
                h("div",null,h("span",{className:"lb"},"Ruta"),h("select",{id:"seg-pR"},h("option",{value:"A"},"A >48h"),h("option",{value:"B"},"B <48h")))));
            form.append(h("div",{style:{marginBottom:"8px"}},h("span",{className:"lb"},"Notas"),h("textarea",{id:"seg-pNt",placeholder:"Herencia, urgente...",rows:"2",style:{fontSize:"13px"}})));
            form.append(h("div",{style:{display:"flex",gap:"8px"}},
                h("button",{className:"btn btn-gray",style:{flex:"1"},onClick:()=>{S._addOpen=false;render();}},"Cancelar"),
                h("button",{className:"btn btn-ind",style:{flex:"1"},onClick:()=>{
                    const n=(_ct.querySelector('#seg-pN').value||'').trim();
                    if(!n)return alert("Nombre obligatorio");
                    const np={id:uid(),nombre:n,telefono:(_ct.querySelector('#seg-pT').value||'').trim(),zona:(_ct.querySelector('#seg-pZ').value||'').trim(),route:_ct.querySelector('#seg-pR').value,sentMsgs:[],lastSentDate:null,status:"activo",createdAt:new Date().toISOString().split("T")[0],notes:(_ct.querySelector('#seg-pNt').value||'').trim(),reactions:{}};
                    S.pros.push(np);swP(np.id);S._addOpen=false;persist();_savePro(np);render();
                }},"✅ Guardar")));
            el.append(form);
        }

        if(!S.pros.length){el.append(h("div",{style:{color:"#94a3b8",textAlign:"center",padding:"32px"}},"No hay prospectos"));return;}
        let list=sortP(S.pros);
        if(S.pfl!=="all")list=list.filter(p=>p.status===S.pfl);
        if(S.psr.trim()){const q=S.psr.toLowerCase();list=list.filter(p=>p.nombre.toLowerCase().includes(q)||(p.telefono||"").includes(q)||(p.zona||"").toLowerCase().includes(q));}
        if(!list.length){el.append(h("div",{style:{color:"#94a3b8",textAlign:"center",padding:"20px"}},"Sin resultados"));return;}
        el.append(h("div",{style:{fontSize:"11px",color:"#94a3b8",fontWeight:"600",marginBottom:"6px"}},list.length+" prospectos"));

        const wrap=h("div",{style:{background:"#fff",border:"2px solid #e2e8f0",borderRadius:"12px",overflow:"hidden"}});
        wrap.append(h("div",{style:{display:"grid",gridTemplateColumns:"1fr 80px 90px 60px 28px",gap:"0",padding:"6px 12px",background:"#f8fafc",borderBottom:"2px solid #e2e8f0"}},
            h("span",{style:{fontSize:"10px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase",letterSpacing:"1px"}},"Nombre / Zona"),
            h("span",{style:{fontSize:"10px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase"}},"Último"),
            h("span",{style:{fontSize:"10px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase"}},"Próximo"),
            h("span",{style:{fontSize:"10px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase"}},"Estado"),
            h("span",null,"")));

        list.forEach((p,i)=>{
            const sc=(p.sentMsgs||[]).length;const last=sc?p.sentMsgs[sc-1]:null;const nc=last&&M[last]?M[last].nx:null;
            const st=ST_CFG[p.status];const isDue=dueIds.has(p.id);const isNew=!sc&&p.status==="activo";const isLast=i===list.length-1;
            const row=h("div",{style:{display:"grid",gridTemplateColumns:"1fr 80px 90px 60px 28px",gap:"0",padding:"9px 12px",cursor:"pointer",borderBottom:isLast?"none":"1px solid #f1f5f9",background:isDue||isNew?"#fffbeb":"#fff"}});
            row.addEventListener("mouseenter",()=>{if(!isDue&&!isNew)row.style.background="#f8fafc";});
            row.addEventListener("mouseleave",()=>{row.style.background=isDue||isNew?"#fffbeb":"#fff";});
            row.addEventListener("click",()=>{S.modal=p.id;render();});
            const col1=h("div",{style:{minWidth:0}});
            const nameRow=h("div",{style:{display:"flex",alignItems:"center",gap:"5px",marginBottom:"1px"}},h("span",{style:{fontSize:"13px",fontWeight:"700",color:"#1e293b",overflow:"hidden",textOverflow:"ellipsis",whiteSpace:"nowrap"}},p.nombre));
            if(isDue)nameRow.append(h("span",{style:{fontSize:"9px",color:"#ef4444",fontWeight:"800",flexShrink:"0"}},"⚡HOY"));
            if(isNew)nameRow.append(h("span",{style:{fontSize:"9px",color:"#f59e0b",fontWeight:"800",flexShrink:"0"}},"🆕NEW"));
            col1.append(nameRow);
            const sub2=h("div",{style:{display:"flex",alignItems:"center",gap:"5px"}});
            if(p.zona)sub2.append(h("span",{style:{fontSize:"10px",color:"#94a3b8"}},"📍"+p.zona));
            if(p.notes)sub2.append(h("span",{title:p.notes,style:{fontSize:"10px",color:"#92400e",cursor:"help"}},"📝"));
            col1.append(sub2);row.append(col1);
            row.append(h("div",{style:{display:"flex",alignItems:"center"}},last?h("span",{className:"badge",style:{background:CL[M[last]?.t||"C"],fontSize:"9px",padding:"2px 6px"}},last):h("span",{style:{fontSize:"10px",color:"#cbd5e1"}},"-")));
            row.append(h("div",{style:{display:"flex",alignItems:"center",gap:"3px"}},nc?[h("span",{style:{fontSize:"10px",color:"#94a3b8"}},"→"),h("span",{className:"badge",style:{background:CL[M[nc]?.t||"C"]+"22",color:CL[M[nc]?.t||"C"],border:"1px solid "+CL[M[nc]?.t||"C"]+"44",fontSize:"9px",padding:"2px 6px"}},nc)]:sc?h("span",{style:{fontSize:"10px",color:"#22c55e",fontWeight:"700"}},"✓ fin"):h("span",{className:"badge",style:{background:CL.C,fontSize:"9px",padding:"2px 6px"}},"C1")));
            row.append(h("div",{style:{display:"flex",alignItems:"center"}},h("span",{className:"stb",style:{background:st.bg,color:st.c,border:"1px solid "+st.c,fontSize:"9px",padding:"2px 6px",cursor:"pointer"},onClick:e=>{e.stopPropagation();if(!confirm("¿Cambiar estado de "+p.nombre+"?"))return;const ss=["activo","pausado","convertido","perdido"];p.status=ss[(ss.indexOf(p.status)+1)%ss.length];persist();_savePro(p);render();}},st.l.substring(0,3))));
            row.append(h("div",{style:{display:"flex",alignItems:"center",justifyContent:"center",color:"#cbd5e1",fontSize:"14px"}},"›"));
            wrap.append(row);
        });
        el.append(wrap);
    }

    // ── MODAL ─────────────────────────────────────────────────
    function renderModal(app){
        const p=S.pros.find(x=>x.id===S.modal);if(!p){S.modal=null;render();return;}
        const sc=(p.sentMsgs||[]).length,last=sc?p.sentMsgs[sc-1]:null,nc=last&&M[last]?M[last].nx:null;
        const st=ST_CFG[p.status];
        const bg=h("div",{className:"modal-bg",onClick:()=>{S.modal=null;render();}});
        const modal=h("div",{className:"modal",onClick:e=>e.stopPropagation()});
        modal.append(h("div",{className:"modal-handle"}));
        modal.append(h("div",{style:{display:"flex",alignItems:"flex-start",gap:"12px",marginBottom:"14px"}},
            h("div",{style:{flex:"1"}},
                h("div",{style:{fontSize:"20px",fontWeight:"800",color:"#1e293b",marginBottom:"4px"}},p.nombre),
                h("div",{style:{fontSize:"13px",color:"#64748b",marginBottom:"6px"}},(p.telefono||"Sin teléfono")+(p.zona?" · 📍 "+p.zona:"")),
                h("div",{style:{display:"flex",gap:"6px",flexWrap:"wrap"}},h("span",{className:"stb",style:{background:st.bg,color:st.c,border:"1px solid "+st.c,fontSize:"12px",cursor:"default"}},st.l),h("span",{style:{fontSize:"12px",color:"#64748b"}},"Ruta "+(p.route||"A")+" · desde "+(p.createdAt||"?")))),
            h("button",{style:{fontSize:"22px",color:"#94a3b8",padding:"0 4px"},onClick:()=>{S.modal=null;render();}},"✕")));

        modal.append(h("div",{style:{marginBottom:"12px"}},h("span",{className:"lb"},"Notas")));
        const ntarea=document.createElement("textarea");ntarea.rows=2;ntarea.style.fontSize="13px";ntarea.value=p.notes||"";ntarea.placeholder="Sin notas...";ntarea.addEventListener("input",e=>{p.notes=e.target.value;persist();_savePro(p);});
        modal.append(ntarea);

        const codes=p.route==="B"?[...AN_O,...SEQ.slice(1)]:SEQ;
        const seqRow=h("div",{style:{display:"flex",gap:"6px",flexWrap:"wrap",marginBottom:"14px",padding:"12px",background:"#f8fafc",borderRadius:"10px",border:"2px solid #e2e8f0"}});
        seqRow.append(h("div",{style:{width:"100%",fontSize:"11px",fontWeight:"700",color:"#64748b",marginBottom:"6px"}},"Secuencia principal"));
        codes.forEach(code=>{const snt=(p.sentMsgs||[]).includes(code),isNx=!snt&&nc===code;seqRow.append(h("div",{style:{textAlign:"center"}},h("div",{className:"td"+(snt?" s":"")+(isNx?" nx":"")},snt?"✓":code.replace(/[A-Z]/g,"")),h("div",{style:{fontSize:"9px",color:"#94a3b8",marginTop:"2px",fontWeight:"600"}},code)));});
        modal.append(seqRow);

        if(nc){const nmsg=M[nc];modal.append(h("div",{style:{background:"#fffbeb",border:"2px solid #fbbf24",borderRadius:"10px",padding:"12px",marginBottom:"12px",cursor:"pointer"},onClick:()=>{swP(p.id);S.modal=null;S.vw="mensajes";S.om=nc;render();}},h("div",{style:{fontSize:"11px",fontWeight:"700",color:"#b45309",marginBottom:"4px"}},"PRÓXIMO MENSAJE"),h("div",{style:{display:"flex",alignItems:"center",gap:"8px"}},h("span",{className:"badge",style:{background:CL[nmsg.t]}},nc),h("span",{style:{fontSize:"13px",fontWeight:"600",color:"#334155"}},nmsg.ti)),h("div",{style:{fontSize:"11px",color:"#92400e",marginTop:"4px"}},"Tocá para ir al mensaje →")));}

        modal.append(h("div",{style:{display:"flex",gap:"8px",marginBottom:"12px"}},
            h("button",{className:"btn btn-ind",style:{flex:"1"},onClick:()=>{swP(p.id);S.modal=null;S.vw="mensajes";render();}},"💬 Mensajes"),
            p.telefono?h("a",{href:"https://wa.me/"+(p.telefono||"").replace(/\D/g,""),target:"_blank",rel:"noopener",className:"btn btn-wa",style:{flex:"1",textDecoration:"none"}},"WhatsApp ↗"):"",
            h("button",{className:"btn btn-danger",style:{flex:"0.6"},onClick:()=>{if(!confirm("¿Eliminar a "+p.nombre+"?"))return;S.pros=S.pros.filter(x=>x.id!==p.id);if(S.aid===p.id){S.aid=null;S.fd={};}S.modal=null;persist();CasAPI.remove('propietarios',p.id).catch(()=>{});render();}},"🗑")));

        const ssArr=["activo","pausado","convertido","perdido"];
        modal.append(h("div",{style:{display:"flex",gap:"6px",flexWrap:"wrap"}},
            ...ssArr.map(s=>h("button",{className:"chip"+(p.status===s?" on":""),onClick:()=>{if(p.status===s)return;if(!confirm("¿Cambiar estado a "+ST_CFG[s].l+"?"))return;p.status=s;persist();_savePro(p);render();}},ST_CFG[s].l))));
        bg.append(modal);app.append(bg);
    }

    // ── MENSAJES ──────────────────────────────────────────────
    function vMensajes(el){
        const a=ga();
        if(a){
            el.append(h("div",{style:{background:"#f5f3ff",border:"2px solid #c4b5fd",borderRadius:"10px",padding:"10px 14px",marginBottom:"10px",display:"flex",alignItems:"center",gap:"10px"}},
                h("div",{style:{flex:"1"}},h("div",{style:{fontSize:"14px",fontWeight:"700",color:"#5b21b6"}},a.nombre),h("div",{style:{fontSize:"11px",color:"#7c3aed",marginTop:"2px"}},(a.telefono||"")+(a.zona?" · "+a.zona:"")+(a.notes?" · 📝 "+a.notes.substring(0,40):""))),
                h("button",{style:{fontSize:"14px",color:"#7c3aed",border:"2px solid #c4b5fd",borderRadius:"6px",padding:"4px 10px",fontWeight:"700",background:"#fff"},onClick:()=>{S.aid=null;persist();_saveMeta('aid',null);render();}},"✕")));
        }else{
            const psi=h("input",{style:{width:"100%",padding:"10px 14px",background:"#fff",border:"2px solid #e2e8f0",borderRadius:"10px",color:"#1e293b",fontSize:"14px",marginBottom:"8px",outline:"none"},placeholder:"👤 Buscar y seleccionar prospecto..."});
            const pR=h("div",{style:{marginBottom:"6px"}});
            psi.addEventListener("input",e=>{const q=e.target.value.toLowerCase().trim();pR.innerHTML="";if(!q)return;S.pros.filter(p=>p.nombre.toLowerCase().includes(q)||(p.telefono||"").includes(q)).slice(0,6).forEach(p=>{pR.append(h("div",{style:{padding:"8px 12px",background:"#f5f3ff",border:"1px solid #c4b5fd",borderRadius:"6px",marginBottom:"4px",cursor:"pointer",fontSize:"13px",color:"#5b21b6",fontWeight:"600"},onClick:()=>{swP(p.id);psi.value="";pR.innerHTML="";render();}},p.nombre+" · "+(p.telefono||"")+" · "+(p.zona||"")));});});
            el.append(psi,pR);
            const due=gdue(),newPs=gnew(),prox=gproximos();
            el.append(h("div",{style:{fontSize:"11px",fontWeight:"700",color:"#94a3b8",textTransform:"uppercase",letterSpacing:"1px",marginBottom:"8px"}},"Accesos rápidos"));
            el.append(h("div",{className:"g3",style:{marginBottom:"14px"}},
                h("button",{className:"btn",style:{background:due.length?"#fef2f2":"#f8fafc",border:"2px solid "+(due.length?"#ef4444":"#e2e8f0"),color:due.length?"#ef4444":"#94a3b8",flexDirection:"column",gap:"3px",padding:"12px 6px"},onClick:()=>{S.vw="hoy";S._hoyFl="vencidos";render();}},h("span",{style:{fontSize:"20px"}},"⏰"),h("span",{style:{fontSize:"11px",fontWeight:"700"}},"Vencidos"),h("span",{style:{fontSize:"16px",fontWeight:"800"}},String(due.length))),
                h("button",{className:"btn",style:{background:newPs.length?"#fffbeb":"#f8fafc",border:"2px solid "+(newPs.length?"#f59e0b":"#e2e8f0"),color:newPs.length?"#b45309":"#94a3b8",flexDirection:"column",gap:"3px",padding:"12px 6px"},onClick:()=>{S.vw="hoy";S._hoyFl="hoy";render();}},h("span",{style:{fontSize:"20px"}},"🆕"),h("span",{style:{fontSize:"11px",fontWeight:"700"}},"Sin contacto"),h("span",{style:{fontSize:"16px",fontWeight:"800"}},String(newPs.length))),
                h("button",{className:"btn",style:{background:"#ecfeff",border:"2px solid #67e8f9",color:"#0891b2",flexDirection:"column",gap:"3px",padding:"12px 6px"},onClick:()=>{S.vw="hoy";S._hoyFl="proximos";render();}},h("span",{style:{fontSize:"20px"}},"📅"),h("span",{style:{fontSize:"11px",fontWeight:"700"}},"Próximos"),h("span",{style:{fontSize:"16px",fontWeight:"800"}},String(prox.length)))));
        }

        const fl=h("div",{style:{display:"flex",gap:"5px",marginBottom:"10px",flexWrap:"wrap"}});
        [["all","Todos"],["C","Contacto"],["AN","Anuncio"],["N","Negociación"],["V","Mercado"],["M","Lystos"],["MP","Propuesta"]].forEach(([id,l])=>{fl.append(h("button",{className:"chip"+(S.fl===id?" on":""),style:S.fl===id&&id!=="all"?{background:CL[id],borderColor:CL[id],color:"#fff"}:{},onClick:()=>{S.fl=id;render();}},l));});
        el.append(fl);
        const si=h("input",{style:{width:"100%",padding:"10px 14px",background:"#fff",border:"2px solid #e2e8f0",borderRadius:"10px",color:"#1e293b",fontSize:"14px",marginBottom:"8px",outline:"none"},placeholder:"🔍 Buscar mensaje...",value:S.sr});
        si.addEventListener("input",e=>{S.sr=e.target.value;render();});el.append(si);
        let msgs=Object.values(M);
        if(S.fl!=="all")msgs=msgs.filter(m=>m.t===S.fl);
        if(S.sr.trim()){const q=S.sr.toLowerCase();msgs=msgs.filter(m=>m.ti.toLowerCase().includes(q)||m.tpl.toLowerCase().includes(q)||m.c.toLowerCase().includes(q));}
        if(!msgs.length){el.append(h("div",{style:{color:"#94a3b8",textAlign:"center",padding:"32px"}},"Sin resultados"));return;}
        msgs.forEach(msg=>el.append(mkC(msg,a)));
    }

    function mkC(msg,a){
        const fv=a?gfv(msg.c):{};const nom=a?a.nombre:"";const ph=a?(a.telefono||"").replace(/\D/g,""):"";
        const previewText=bm(msg.tpl,fv,nom);const rdy=a&&msg.f.every(f=>(fv[f.k]||"").trim())&&!!nom&&!!ph;
        const card=h("div",{className:"card"+(S.om===msg.c?" hi":""),style:{marginBottom:"8px"}});
        const header=h("div",{className:"ch",onClick:()=>{S.om=S.om===msg.c?null:msg.c;render();}});
        header.append(h("span",{className:"badge",style:{background:CL[msg.t]||"#94a3b8",flexShrink:"0"}},msg.c),h("div",{style:{flex:"1",minWidth:0}},h("div",{style:{fontWeight:"700",fontSize:"13px",color:"#1e293b"}},msg.ti),h("div",{style:{fontSize:"10px",color:"#94a3b8",marginTop:"2px"}},msg.w)),h("span",{style:{color:"#94a3b8",fontSize:"16px",transition:"transform .2s",transform:S.om===msg.c?"rotate(180deg)":"rotate(0)"}},"▾"));
        card.append(header);
        if(S.om===msg.c){
            const body=h("div",{className:"cb"});
            if(msg.f.length){const gr=h("div",{className:"g2",style:{marginBottom:"12px"}});msg.f.forEach(f=>{const inp=h("input",{placeholder:f.p,value:fv[f.k]||""});inp.addEventListener("input",e=>{if(!S.fd[msg.c])S.fd[msg.c]={};S.fd[msg.c][f.k]=e.target.value;const upd=bm(msg.tpl,S.fd[msg.c]||{},nom);preEl.textContent=upd;cpBtn._m=upd;if(a&&ph){const ok3=msg.f.every(ff=>(S.fd[msg.c][ff.k]||"").trim());waEl.href=ok3?`https://wa.me/${ph}?text=${encodeURIComponent(upd)}`:"#";waEl.className="btn "+(ok3?"btn-wa":"btn-wa-dis");}persist();_saveMeta('fd',S.fd);});gr.append(h("div",null,h("span",{className:"lb"},f.l),inp));});body.append(gr);}
            const preEl=h("div",{className:"pv"},h("pre",{style:{fontSize:"13px",color:"#334155",lineHeight:"1.7"}},previewText));body.append(preEl);
            const btns2=h("div",{style:{display:"flex",gap:"8px",marginTop:"10px"}});
            const cpBtn=h("button",{className:"btn btn-gray",style:{flex:"1"},onClick:()=>{navigator.clipboard.writeText(cpBtn._m||previewText);cpBtn.textContent="✓ Copiado";setTimeout(()=>cpBtn.textContent="📋 Copiar",1500);}},"📋 Copiar");cpBtn._m=previewText;btns2.append(cpBtn);
            const waEl=h("a",{href:rdy?`https://wa.me/${ph}?text=${encodeURIComponent(previewText)}`:"#",target:rdy?"_blank":"",rel:"noopener",className:"btn "+(rdy?"btn-wa":"btn-wa-dis"),style:{flex:"1.4",textDecoration:"none"}},rdy?"WhatsApp → "+nom+" ↗":(a?"Faltan datos":"Seleccioná prospecto"));btns2.append(waEl);
            body.append(btns2);card.append(body);
        }
        return card;
    }

    // ── VIERNES ───────────────────────────────────────────────
    function vViernes(el){
        const proxVie=nFri(new Date());
        el.append(h("div",{style:{background:"#ecfeff",border:"2px solid #67e8f9",borderRadius:"12px",padding:"12px 14px",marginBottom:"14px"}},
            h("div",{style:{fontSize:"15px",fontWeight:"700",color:"#0891b2",marginBottom:"4px"}},"📡 Envío de zona · Viernes 17-18h"),
            h("div",{style:{fontSize:"12px",color:"#0e7490",lineHeight:"1.5"}},new Date().getDay()===5?"✅ Hoy es viernes — momento de enviar":"📅 Próximo viernes: "+fD(proxVie))));
        const wrap=h("div");el.append(wrap);
        function buildContent(){
            wrap.innerHTML="";
            const msgSel=h("select",{style:{width:"100%",marginBottom:"12px",fontSize:"13px",padding:"10px 12px",borderRadius:"8px",border:"2px solid #e2e8f0",background:"#fff"}});
            msgSel.append(h("option",{value:""},"Elegí el mensaje de esta semana..."));
            V_O.forEach(c=>msgSel.append(h("option",{value:c},c+" — "+M[c].ti)));
            msgSel.value=S._bM||"";
            msgSel.addEventListener("change",e=>{S._bM=e.target.value;buildContent();});
            wrap.append(msgSel);
            if(!S._bM)return;
            const msg=M[S._bM];if(!S.fd[msg.c])S.fd[msg.c]={};const fv=S.fd[msg.c];
            if(msg.f.length){
                const fb=h("div",{style:{background:"#f8fafc",border:"2px solid #e2e8f0",borderRadius:"10px",padding:"12px",marginBottom:"14px"}},h("div",{className:"lb",style:{marginBottom:"8px"}},"Datos del mensaje"));
                const gr=h("div",{className:"g2"});
                msg.f.forEach(f=>{const inp=h("input",{placeholder:f.p,value:fv[f.k]||""});inp.addEventListener("input",e=>{fv[f.k]=e.target.value;persist();_saveMeta('fd',S.fd);refreshProsList();});gr.append(h("div",null,h("span",{className:"lb"},f.l),inp));});
                fb.append(gr);wrap.append(fb);
            }
            const zona=(fv.zona||"").trim().toLowerCase();
            const pros=zona?S.pros.filter(p=>(p.zona||"").toLowerCase()===zona&&p.status==="activo"):S.pros.filter(p=>p.status==="activo");
            wrap.append(h("div",{style:{fontSize:"13px",color:"#64748b",marginBottom:"10px",fontWeight:"600"}},pros.length+" prospectos"+(zona?" en "+(fv.zona||zona):" activos")));
            const prosContainer=h("div");wrap.append(prosContainer);
            function refreshProsList(){
                prosContainer.innerHTML="";
                const fv2=S.fd[msg.c]||{};const z2=(fv2.zona||"").trim().toLowerCase();
                const list2=z2?S.pros.filter(p=>(p.zona||"").toLowerCase()===z2&&p.status==="activo"):S.pros.filter(p=>p.status==="activo");
                list2.forEach(p=>{
                    const previewText=bm(msg.tpl,fv2,p.nombre);const ph=(p.telefono||"").replace(/\D/g,"");const rdy=msg.f.every(f=>(fv2[f.k]||"").trim())&&!!ph;
                    const snt=(p.sentMsgs||[]).includes(msg.c);
                    const item=h("div",{className:"pi"+(snt?" on":""),style:{marginBottom:"4px"}});
                    item.append(h("div",{style:{flex:"1"}},h("div",{style:{fontWeight:"700",fontSize:"13px"}},p.nombre+(p.zona?" · "+p.zona:"")),h("div",{style:{fontSize:"11px",color:"#64748b",marginTop:"2px"}},p.telefono||"Sin teléfono")));
                    const ab=h("a",{href:rdy?`https://wa.me/${ph}?text=${encodeURIComponent(previewText)}`:"#",target:rdy?"_blank":"",rel:"noopener",className:"btn "+(rdy?"btn-wa":"btn-wa-dis"),style:{padding:"7px 10px",fontSize:"11px",textDecoration:"none",flexShrink:"0"},onClick:()=>{if(!rdy)return;if(!(p.sentMsgs||[]).includes(msg.c)){p.sentMsgs=p.sentMsgs||[];p.sentMsgs.push(msg.c);p.lastSentDate=new Date().toISOString().split("T")[0];S.met.sent++;S.sl.push({d:new Date().toISOString().split("T")[0],c:msg.c,t:msg.t,pid:p.id,z:p.zona||""});persist();_savePro(p);_saveMeta('met',S.met);_saveMeta('sl',S.sl);setTimeout(()=>{ab.className="btn btn-gray";ab.textContent="✓";},500);}}},"WA ↗");
                    item.append(ab);prosContainer.append(item);
                });
            }
            refreshProsList();
        }
        buildContent();
    }

    // ── MÁS ───────────────────────────────────────────────────
    function vMas(el){
        el.append(h("div",{style:{fontSize:"15px",fontWeight:"700",color:"#1e293b",marginBottom:"12px"}},"⚙️ Más opciones"));

        // Estadísticas
        el.append(h("div",{style:{fontSize:"12px",fontWeight:"700",color:"#64748b",textTransform:"uppercase",letterSpacing:"1px",marginBottom:"8px"}},"Estadísticas"));
        const ac=S.pros.filter(p=>p.status==="activo").length;
        const conv=S.pros.filter(p=>p.status==="convertido").length;
        el.append(h("div",{className:"g3",style:{marginBottom:"16px"}},
            h("div",{className:"mini-dash-card"},h("div",{className:"mini-dash-num",style:{color:"#6366f1"}},String(S.met.sent||0)),h("div",{className:"mini-dash-lbl"},"Enviados total")),
            h("div",{className:"mini-dash-card"},h("div",{className:"mini-dash-num",style:{color:"#22c55e"}},String(ac)),h("div",{className:"mini-dash-lbl"},"Activos")),
            h("div",{className:"mini-dash-card"},h("div",{className:"mini-dash-num",style:{color:"#8b5cf6"}},String(conv)),h("div",{className:"mini-dash-lbl"},"Convertidos"))));

        el.append(h("div",{style:{display:"flex",gap:"8px",marginBottom:"12px"}},
            h("button",{className:"btn btn-gray",style:{flex:"1"},onClick:exportBackup},"💾 Backup JSON"),
            h("button",{className:"btn btn-ind",style:{flex:"1"},onClick:()=>showSegImportModal(el)},"📂 Importar CSV")));

        // Reglas del protocolo
        el.append(h("div",{style:{fontSize:"12px",fontWeight:"700",color:"#64748b",textTransform:"uppercase",letterSpacing:"1px",marginBottom:"8px"}},"Reglas del protocolo"));
        [{i:"⚡",r:"M (Lystos) siempre tiene prioridad sobre V."},{i:"🔇",r:"Cuando el prospecto responde, pausar envíos 7 días."},{i:"📵",r:"Máximo 2 mensajes por semana al mismo prospecto."},{i:"🔄",r:"Si M cae misma semana que N, el N se retrasa 4 días."},{i:"♾️",r:"Tras N5: V viernes + M Lystos."},{i:"🔕",r:"Nunca referenciar mensajes anteriores."},{i:"📞",r:"Llamada estratégica entre semana 4-6."}].forEach(({i,r})=>{
            el.append(h("div",{style:{display:"flex",gap:"10px",padding:"8px",background:"#f8fafc",border:"1px solid #e2e8f0",borderRadius:"8px",marginBottom:"5px",fontSize:"12px",color:"#475569"}},h("span",null,i),h("span",null,r)));
        });
        el.append(h("div",{style:{textAlign:"center",marginTop:"14px",fontSize:"10px",color:"#cbd5e1"}},"CAS Seguimiento v2.2 — API"));
    }

    function showSegImportModal(parentEl){
        const bg=document.createElement("div");bg.className="import-modal-bg";
        const modal=document.createElement("div");modal.className="import-modal";
        modal.onclick=e=>e.stopPropagation();bg.onclick=()=>bg.remove();
        modal.innerHTML=`<h3>📂 Importar propietarios</h3><p>CSV con columnas: <strong>Nombre, Teléfono, Zona</strong><br>Primera fila = encabezado (se ignora). Ruta: A por defecto.</p>`;
        const dz=document.createElement("div");dz.className="import-dropzone";
        dz.innerHTML=`<div class="import-dropzone-ic">📄</div><div class="import-dropzone-lbl">Tocá para seleccionar CSV</div><div class="import-dropzone-sub">o arrastrá el archivo aquí</div>`;
        const fileInp=document.createElement("input");fileInp.type="file";fileInp.accept=".csv,text/csv";fileInp.style.display="none";
        dz.onclick=()=>fileInp.click();
        dz.addEventListener("dragover",e=>{e.preventDefault();dz.classList.add("drag-over");});
        dz.addEventListener("dragleave",()=>dz.classList.remove("drag-over"));
        dz.addEventListener("drop",e=>{e.preventDefault();dz.classList.remove("drag-over");if(e.dataTransfer.files[0])processSegCSV(e.dataTransfer.files[0],preview,confirmBtn);});
        const preview=document.createElement("div");preview.className="import-preview";preview.style.display="none";
        const confirmBtn=document.createElement("button");confirmBtn.className="btn btn-ind";confirmBtn.style.cssText="width:100%;margin-top:10px;padding:12px;border-radius:8px;font-size:13px;font-weight:700;display:none;align-items:center;justify-content:center;gap:6px";confirmBtn.textContent="✅ Importar propietarios";
        confirmBtn._data=[];
        confirmBtn.onclick=()=>{
            let added=0;
            confirmBtn._data.forEach(pro=>{
                if(!S.pros.find(x=>x.telefono===pro.telefono&&x.nombre===pro.nombre)){
                    S.pros.push(pro);
                    CasAPI.save("propietarios",pro).catch(()=>{});
                    added++;
                }
            });
            toast("✅ "+added+" propietarios importados");bg.remove();render();
        };
        fileInp.onchange=()=>{if(fileInp.files[0])processSegCSV(fileInp.files[0],preview,confirmBtn);};
        const closeBtn=document.createElement("button");closeBtn.className="btn btn-gray";closeBtn.style.cssText="width:100%;margin-top:8px;padding:10px;border-radius:8px;font-size:12px;font-weight:700";closeBtn.textContent="Cancelar";closeBtn.onclick=()=>bg.remove();
        modal.append(dz,fileInp,preview,confirmBtn,closeBtn);bg.append(modal);document.querySelector("#seg-pane")?document.querySelector("#seg-pane").append(bg):document.body.append(bg);
    }

    function processSegCSV(file,previewEl,confirmBtn){
        const reader=new FileReader();
        reader.onload=ev=>{
            const lines=ev.target.result.split("\n").map(l=>l.trim()).filter(Boolean);
            const rows=lines.slice(1);const parsed=[];const html=[];
            rows.forEach((line,i)=>{
                const cols=_segParseCSV(line);
                const nombre=(cols[0]||"").trim(),tel=(cols[1]||"").trim();
                if(!nombre&&!tel){html.push(`<div class="import-row"><span>#${i+2}</span><span class="import-err">Vacía</span></div>`);return;}
                const id=Date.now().toString(36)+(i).toString(36);
                parsed.push({id,nombre:nombre||"Sin nombre",telefono:tel,zona:(cols[2]||"").trim(),route:"A",sentMsgs:[],lastSentDate:null,status:"activo",createdAt:new Date().toISOString().split("T")[0],notes:(cols[3]||"").trim(),reactions:{}});
                html.push(`<div class="import-row"><span><strong>${nombre}</strong> ${tel}</span><span class="import-ok">✔ OK</span></div>`);
            });
            previewEl.innerHTML=`<div style="font-weight:700;color:#64748b;margin-bottom:6px;font-size:11px">${parsed.length} propietarios</div>`+html.join("");
            previewEl.style.display="block";confirmBtn._data=parsed;confirmBtn.style.display=parsed.length?"flex":"none";
        };
        reader.readAsText(file,"UTF-8");
    }
    function _segParseCSV(line){const res=[];let cur="";let inQ=false;for(let i=0;i<line.length;i++){const c=line[i];if(c==='"'&&!inQ){inQ=true;continue;}if(c==='"'&&inQ){inQ=false;continue;}if(c===","&&!inQ){res.push(cur);cur="";continue;}cur+=c;}res.push(cur);return res;}


    function exportBackup(){
        const data=JSON.stringify({pros:S.pros,met:S.met,ct:S.ct,mr:S.mr,sl:S.sl,ts:new Date().toISOString()},null,2);
        const a=document.createElement("a");a.href=URL.createObjectURL(new Blob([data],{type:"application/json"}));a.download="cas-seg-backup-"+new Date().toISOString().split("T")[0]+".json";a.click();
    }

    return { init };
})();
