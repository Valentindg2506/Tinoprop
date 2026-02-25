<?php
require_once __DIR__ . '/../inc/bootstrap.php';

$clientes_stmt = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellido) AS etiqueta FROM clientes ORDER BY nombre, apellido");
$clientes = $clientes_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$propiedades_stmt = $pdo->query("SELECT id, titulo FROM propiedades ORDER BY titulo");
$propiedades = $propiedades_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$clientes_data_stmt = $pdo->query("SELECT id, nombre, apellido, email, telefono, direccion, operacion, zona_interesada, presupuesto FROM clientes ORDER BY id");
$clientes_data_raw = $clientes_data_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$clientes_data = [];
foreach ($clientes_data_raw as $cliente_row) {
    $cliente_row['full_name'] = trim(($cliente_row['nombre'] ?? '') . ' ' . ($cliente_row['apellido'] ?? ''));
    $clientes_data[] = $cliente_row;
}

$propiedades_data_stmt = $pdo->query("SELECT id, titulo, tipo, ubicacion, direccion, precio, operacion, estado, referencia FROM propiedades ORDER BY id");
$propiedades_data = $propiedades_data_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$plantillas = [
    [
        'key' => 'encargo_venta_exclusiva',
        'nombre' => 'Encargo de venta exclusiva',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agencia', 'label' => 'Agencia'],
            ['name' => 'agente', 'label' => 'Agente responsable'],
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'dni_propietario', 'label' => 'DNI propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_salida', 'label' => 'Precio de salida'],
            ['name' => 'duracion_meses', 'label' => 'Duración (meses)'],
        ],
    ],
    [
        'key' => 'encargo_venta_no_exclusiva',
        'nombre' => 'Encargo de venta no exclusiva',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agencia', 'label' => 'Agencia'],
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'dni_propietario', 'label' => 'DNI propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_salida', 'label' => 'Precio de salida'],
        ],
    ],
    [
        'key' => 'encargo_alquiler',
        'nombre' => 'Encargo de alquiler',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'dni_arrendador', 'label' => 'DNI arrendador'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'renta_mensual', 'label' => 'Renta mensual'],
            ['name' => 'fianza', 'label' => 'Fianza'],
        ],
    ],
    [
        'key' => 'contrato_arras',
        'nombre' => 'Contrato de arras',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'vendedor', 'label' => 'Vendedor'],
            ['name' => 'comprador', 'label' => 'Comprador'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_total', 'label' => 'Precio total'],
            ['name' => 'importe_arras', 'label' => 'Importe arras'],
            ['name' => 'fecha_limite_escritura', 'label' => 'Fecha límite escritura'],
        ],
    ],
    [
        'key' => 'reserva_compra',
        'nombre' => 'Documento de reserva de compra',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'comprador', 'label' => 'Comprador'],
            ['name' => 'vendedor', 'label' => 'Vendedor'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'importe_reserva', 'label' => 'Importe reserva'],
            ['name' => 'fecha_vigencia', 'label' => 'Fecha de vigencia'],
        ],
    ],
    [
        'key' => 'oferta_compra',
        'nombre' => 'Oferta de compra',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'oferente', 'label' => 'Oferente'],
            ['name' => 'precio_ofertado', 'label' => 'Precio ofertado'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'validez_oferta', 'label' => 'Validez de la oferta'],
        ],
    ],
    [
        'key' => 'contrato_alquiler_habitual',
        'nombre' => 'Contrato alquiler vivienda habitual',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'arrendatario', 'label' => 'Arrendatario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'renta_mensual', 'label' => 'Renta mensual'],
            ['name' => 'duracion_contrato', 'label' => 'Duración contrato'],
            ['name' => 'fecha_inicio', 'label' => 'Fecha inicio'],
        ],
    ],
    [
        'key' => 'contrato_alquiler_temporal',
        'nombre' => 'Contrato alquiler temporal',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'arrendatario', 'label' => 'Arrendatario'],
            ['name' => 'motivo_temporalidad', 'label' => 'Motivo de temporalidad'],
            ['name' => 'fecha_inicio', 'label' => 'Fecha inicio'],
            ['name' => 'fecha_fin', 'label' => 'Fecha fin'],
        ],
    ],
    [
        'key' => 'inventario_anexo',
        'nombre' => 'Inventario y anexo de mobiliario',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'estado_general', 'label' => 'Estado general'],
            ['name' => 'electrodomesticos', 'label' => 'Electrodomésticos'],
            ['name' => 'observaciones', 'label' => 'Observaciones'],
        ],
    ],
    [
        'key' => 'parte_visita',
        'nombre' => 'Parte de visita',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agente', 'label' => 'Agente'],
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'direccion_inmueble', 'label' => 'Inmueble visitado'],
            ['name' => 'fecha_visita', 'label' => 'Fecha de visita'],
            ['name' => 'hora_visita', 'label' => 'Hora de visita'],
        ],
    ],
    [
        'key' => 'autorizacion_visitas',
        'nombre' => 'Autorización de visitas',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'franja_horaria', 'label' => 'Franja horaria permitida'],
        ],
    ],
    [
        'key' => 'consentimiento_rgpd',
        'nombre' => 'Consentimiento RGPD',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'interesado', 'label' => 'Interesado'],
            ['name' => 'dni', 'label' => 'DNI'],
            ['name' => 'finalidad', 'label' => 'Finalidad del tratamiento'],
        ],
    ],
    [
        'key' => 'encargo_busqueda_comprador',
        'nombre' => 'Encargo de búsqueda de comprador (Personal Shopper)',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'presupuesto_maximo', 'label' => 'Presupuesto máximo'],
            ['name' => 'zonas', 'label' => 'Zonas de interés'],
            ['name' => 'honorarios', 'label' => 'Honorarios'],
        ],
    ],
    [
        'key' => 'acta_entrega_llaves',
        'nombre' => 'Acta de entrega de llaves',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'entrega_a', 'label' => 'Entrega a'],
            ['name' => 'entrega_de', 'label' => 'Entrega de'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'numero_juegos', 'label' => 'Número de juegos de llaves'],
            ['name' => 'fecha_entrega', 'label' => 'Fecha entrega'],
        ],
    ],
    [
        'key' => 'mandato_sepa',
        'nombre' => 'Mandato SEPA',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'titular_cuenta', 'label' => 'Titular cuenta'],
            ['name' => 'iban', 'label' => 'IBAN'],
            ['name' => 'acreedor', 'label' => 'Acreedor'],
            ['name' => 'referencia_mandato', 'label' => 'Referencia mandato'],
        ],
    ],
];
?>

<div class="encabezado_seccion">
    <h2>Documentación</h2>
</div>

<div class="doc_intro" id="doc_intro">
    <article class="doc_mode_card">
        <div class="doc_mode_icon">📄</div>
        <h3>Generar documentación</h3>
        <p>Usa plantillas del sector inmobiliario en España, completa datos, firma y descarga PDF. Siempre se guarda copia.</p>
        <button type="button" class="btn_guardar" id="btn_ir_generar">Ir a generar</button>
    </article>

    <article class="doc_mode_card">
        <div class="doc_mode_icon">📎</div>
        <h3>Subir documentación</h3>
        <p>Sube PDF o fotos a cliente o propiedad, con separación estricta por carpetas para que no se mezclen.</p>
        <button type="button" class="btn_guardar" id="btn_ir_subir">Ir a subir</button>
    </article>
</div>

<section class="doc_panel oculto" id="panel-subidas">
    <div class="doc_panel_head">
        <h3>Subir documentación</h3>
        <button type="button" class="btn_secundario" id="btn_volver_subidas">← Volver</button>
    </div>
    <div class="doc_grid">
        <article class="tarjeta_info doc_card">
            <h3>Subir documentación por entidad</h3>
            <p>Los archivos se guardan por separado en carpetas de clientes o propiedades para evitar mezcla.</p>

            <form id="formSubida" enctype="multipart/form-data" class="doc_form">
                <div class="doc_row">
                    <label for="upload_entity_type">Tipo de entidad</label>
                    <select id="upload_entity_type" name="entity_type">
                        <option value="cliente">Cliente</option>
                        <option value="propiedad">Propiedad</option>
                    </select>
                </div>

                <div class="doc_row" id="wrap_upload_cliente">
                    <label for="upload_cliente_id">Cliente</label>
                    <select id="upload_cliente_id" name="cliente_id">
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo (int) $cliente['id']; ?>"><?php echo e($cliente['etiqueta']); ?> (ID <?php echo (int) $cliente['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="doc_row oculto" id="wrap_upload_propiedad">
                    <label for="upload_propiedad_id">Propiedad</label>
                    <select id="upload_propiedad_id" name="propiedad_id">
                        <?php foreach ($propiedades as $propiedad): ?>
                            <option value="<?php echo (int) $propiedad['id']; ?>"><?php echo e($propiedad['titulo']); ?> (ID <?php echo (int) $propiedad['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="doc_row">
                    <label for="documento">Archivo (PDF o imagen)</label>
                    <input type="file" id="documento" name="documento" accept="application/pdf,image/*" required>
                </div>

                <button type="submit" class="btn_guardar">Subir archivo</button>
                <p class="doc_feedback" id="upload_feedback"></p>
            </form>
        </article>

        <article class="tarjeta_info doc_card">
            <h3>Archivos guardados</h3>
            <p>Se listan los ficheros subidos y los PDF generados para la entidad seleccionada.</p>
            <button type="button" class="btn_nuevo_cliente" id="btn_refrescar_listado">Refrescar</button>
            <div class="doc_listados">
                <h4>Subidos</h4>
                <ul id="lista_subidos" class="doc_lista"></ul>
                <h4>Generados</h4>
                <ul id="lista_generados" class="doc_lista"></ul>
            </div>
        </article>
    </div>
</section>

<section class="doc_panel oculto" id="panel-plantillas">
    <div class="doc_panel_head">
        <h3>Generar documentación</h3>
        <button type="button" class="btn_secundario" id="btn_volver_plantillas">← Volver</button>
    </div>
    <article class="tarjeta_info doc_card">
        <h3>Plantillas inmobiliarias España</h3>
        <p>Completa los campos, firma cuando aplique y genera el PDF. Se descarga y además se guarda una copia automática.</p>

        <form id="formPlantilla" class="doc_form">
            <div class="doc_row">
                <label for="tpl_entity_type">Tipo de entidad vinculada</label>
                <select id="tpl_entity_type">
                    <option value="cliente">Cliente</option>
                    <option value="propiedad">Propiedad</option>
                </select>
            </div>

            <div class="doc_row" id="wrap_tpl_cliente">
                <label for="tpl_cliente_id">Cliente</label>
                <select id="tpl_cliente_id">
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo (int) $cliente['id']; ?>"><?php echo e($cliente['etiqueta']); ?> (ID <?php echo (int) $cliente['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="doc_row oculto" id="wrap_tpl_propiedad">
                <label for="tpl_propiedad_id">Propiedad</label>
                <select id="tpl_propiedad_id">
                    <?php foreach ($propiedades as $propiedad): ?>
                        <option value="<?php echo (int) $propiedad['id']; ?>"><?php echo e($propiedad['titulo']); ?> (ID <?php echo (int) $propiedad['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="doc_row">
                <label for="template_key">Plantilla</label>
                <select id="template_key"></select>
            </div>

            <div id="template_fields" class="doc_fields"></div>

            <div class="doc_actions_inline">
                <button type="button" class="btn_nuevo_cliente" id="btn_autocompletar">Autocompletar desde entidad</button>
                <button type="button" class="btn_secundario" id="btn_preview_pdf">Vista previa PDF</button>
            </div>

            <div class="doc_signature" id="firma_wrap">
                <label>Firma (si aplica)</label>
                <canvas id="firma_canvas" width="560" height="180"></canvas>
                <div class="doc_signature_actions">
                    <button type="button" class="btn_secundario" id="firma_limpiar">Limpiar firma</button>
                </div>
            </div>

            <button type="submit" class="btn_guardar">Generar y guardar PDF</button>
            <p class="doc_feedback" id="pdf_feedback"></p>
        </form>
    </article>
</section>

<div class="modal_overlay" id="docPreviewModal" aria-hidden="true">
    <div class="modal_contenido doc_preview_modal" role="dialog" aria-modal="true" aria-labelledby="docPreviewTitle">
        <h3 id="docPreviewTitle">Vista previa del PDF</h3>
        <p>Revisa el documento antes de guardarlo y descargarlo.</p>
        <iframe id="docPreviewFrame" title="Vista previa PDF"></iframe>
        <div class="modal_acciones">
            <button type="button" class="btn_secundario" id="docPreviewCancelar">Cancelar</button>
            <button type="button" class="btn_guardar" id="docPreviewConfirmar">Guardar y descargar</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const plantillas = <?php echo json_encode($plantillas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const clientesData = <?php echo json_encode($clientes_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const propiedadesData = <?php echo json_encode($propiedades_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const docIntro = document.getElementById('doc_intro');
const panelSubidas = document.getElementById('panel-subidas');
const panelPlantillas = document.getElementById('panel-plantillas');

function abrirPanelSubidas() {
    docIntro.classList.add('oculto');
    panelPlantillas.classList.add('oculto');
    panelSubidas.classList.remove('oculto');
    cargarListados();
}

function abrirPanelPlantillas() {
    docIntro.classList.add('oculto');
    panelSubidas.classList.add('oculto');
    panelPlantillas.classList.remove('oculto');
}

function volverInicioDocumentacion() {
    panelSubidas.classList.add('oculto');
    panelPlantillas.classList.add('oculto');
    docIntro.classList.remove('oculto');
}

document.getElementById('btn_ir_subir').addEventListener('click', abrirPanelSubidas);
document.getElementById('btn_ir_generar').addEventListener('click', abrirPanelPlantillas);
document.getElementById('btn_volver_subidas').addEventListener('click', volverInicioDocumentacion);
document.getElementById('btn_volver_plantillas').addEventListener('click', volverInicioDocumentacion);

const uploadEntityType = document.getElementById('upload_entity_type');
const wrapUploadCliente = document.getElementById('wrap_upload_cliente');
const wrapUploadPropiedad = document.getElementById('wrap_upload_propiedad');

function syncUploadEntityUI() {
    const isCliente = uploadEntityType.value === 'cliente';
    wrapUploadCliente.classList.toggle('oculto', !isCliente);
    wrapUploadPropiedad.classList.toggle('oculto', isCliente);
}

uploadEntityType.addEventListener('change', () => {
    syncUploadEntityUI();
    cargarListados();
});
syncUploadEntityUI();

function entidadSeleccionadaUpload() {
    const entityType = uploadEntityType.value;
    const entityId = entityType === 'cliente'
        ? document.getElementById('upload_cliente_id').value
        : document.getElementById('upload_propiedad_id').value;
    return { entityType, entityId };
}

document.getElementById('upload_cliente_id').addEventListener('change', cargarListados);
document.getElementById('upload_propiedad_id').addEventListener('change', cargarListados);
document.getElementById('btn_refrescar_listado').addEventListener('click', cargarListados);

async function cargarListados() {
    const { entityType, entityId } = entidadSeleccionadaUpload();
    const url = `api/documentacion.php?action=list_files&entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`;

    const res = await fetch(url);
    const data = await res.json();

    const subidos = document.getElementById('lista_subidos');
    const generados = document.getElementById('lista_generados');
    subidos.innerHTML = '';
    generados.innerHTML = '';

    if (!data.success) {
        subidos.innerHTML = '<li>Error cargando archivos.</li>';
        generados.innerHTML = '<li>Error cargando archivos.</li>';
        return;
    }

    const renderLista = (lista, nodo) => {
        if (!lista.length) {
            nodo.innerHTML = '<li>Sin archivos.</li>';
            return;
        }

        lista.forEach((item) => {
            const li = document.createElement('li');
            li.innerHTML = `<a href="${item.download_url}" target="_blank">${item.name}</a> <span>(${item.size_label})</span>`;
            nodo.appendChild(li);
        });
    };

    renderLista(data.uploaded, subidos);
    renderLista(data.generated, generados);
}

document.getElementById('formSubida').addEventListener('submit', async (event) => {
    event.preventDefault();

    const feedback = document.getElementById('upload_feedback');
    feedback.textContent = 'Subiendo...';

    const { entityType, entityId } = entidadSeleccionadaUpload();
    const formData = new FormData(event.target);
    formData.append('entity_type', entityType);
    formData.append('entity_id', entityId);

    const res = await fetch('api/documentacion.php?action=upload', {
        method: 'POST',
        body: formData,
    });

    const data = await res.json();
    feedback.textContent = data.message || 'Operación completada.';

    if (data.success) {
        event.target.reset();
        syncUploadEntityUI();
        await cargarListados();
    }
});

const tplEntityType = document.getElementById('tpl_entity_type');
const wrapTplCliente = document.getElementById('wrap_tpl_cliente');
const wrapTplPropiedad = document.getElementById('wrap_tpl_propiedad');
const templateSelect = document.getElementById('template_key');
const templateFields = document.getElementById('template_fields');
const firmaWrap = document.getElementById('firma_wrap');
const btnPreviewPdf = document.getElementById('btn_preview_pdf');
const docPreviewModal = document.getElementById('docPreviewModal');
const docPreviewFrame = document.getElementById('docPreviewFrame');
const docPreviewCancelar = document.getElementById('docPreviewCancelar');
const docPreviewConfirmar = document.getElementById('docPreviewConfirmar');

let pendingPdfDataUri = null;
let pendingTpl = null;
let pendingEntity = null;
let pendingValues = null;

function syncTemplateEntityUI() {
    const isCliente = tplEntityType.value === 'cliente';
    wrapTplCliente.classList.toggle('oculto', !isCliente);
    wrapTplPropiedad.classList.toggle('oculto', isCliente);
}

tplEntityType.addEventListener('change', syncTemplateEntityUI);
syncTemplateEntityUI();

plantillas.forEach((tpl) => {
    const option = document.createElement('option');
    option.value = tpl.key;
    option.textContent = tpl.nombre;
    templateSelect.appendChild(option);
});

function plantillaActiva() {
    return plantillas.find((tpl) => tpl.key === templateSelect.value) || plantillas[0];
}

function renderCamposPlantilla() {
    const tpl = plantillaActiva();
    templateFields.innerHTML = '';

    tpl.campos.forEach((campo) => {
        const row = document.createElement('div');
        row.className = 'doc_row';
        row.innerHTML = `
            <label for="campo_${campo.name}">${campo.label}</label>
            <input type="text" id="campo_${campo.name}" name="${campo.name}" required>
        `;
        templateFields.appendChild(row);
    });

    firmaWrap.classList.toggle('oculto', !tpl.requiere_firma);

    autocompletarCamposPlantilla();
}

templateSelect.addEventListener('change', renderCamposPlantilla);
renderCamposPlantilla();

const firmaCanvas = document.getElementById('firma_canvas');
const firmaCtx = firmaCanvas.getContext('2d');
let firmando = false;

function coordFirma(event) {
    const rect = firmaCanvas.getBoundingClientRect();
    const touch = event.touches ? event.touches[0] : event;
    return {
        x: touch.clientX - rect.left,
        y: touch.clientY - rect.top,
    };
}

function firmaStart(event) {
    firmando = true;
    const p = coordFirma(event);
    firmaCtx.beginPath();
    firmaCtx.moveTo(p.x, p.y);
}

function firmaMove(event) {
    if (!firmando) return;
    event.preventDefault();
    const p = coordFirma(event);
    firmaCtx.lineWidth = 2;
    firmaCtx.lineCap = 'round';
    firmaCtx.strokeStyle = '#1f2937';
    firmaCtx.lineTo(p.x, p.y);
    firmaCtx.stroke();
}

function firmaEnd() {
    firmando = false;
}

firmaCanvas.addEventListener('mousedown', firmaStart);
firmaCanvas.addEventListener('mousemove', firmaMove);
window.addEventListener('mouseup', firmaEnd);
firmaCanvas.addEventListener('touchstart', firmaStart, { passive: false });
firmaCanvas.addEventListener('touchmove', firmaMove, { passive: false });
window.addEventListener('touchend', firmaEnd);

document.getElementById('firma_limpiar').addEventListener('click', () => {
    firmaCtx.clearRect(0, 0, firmaCanvas.width, firmaCanvas.height);
});

function entidadSeleccionadaPlantilla() {
    const entityType = tplEntityType.value;
    const entityId = entityType === 'cliente'
        ? document.getElementById('tpl_cliente_id').value
        : document.getElementById('tpl_propiedad_id').value;
    return { entityType, entityId };
}

function clienteActualData() {
    const id = Number(document.getElementById('tpl_cliente_id').value || 0);
    return clientesData.find((item) => Number(item.id) === id) || null;
}

function propiedadActualData() {
    const id = Number(document.getElementById('tpl_propiedad_id').value || 0);
    return propiedadesData.find((item) => Number(item.id) === id) || null;
}

function sugerenciaPorCampo(nombreCampo, cliente, propiedad) {
    const campo = nombreCampo.toLowerCase();

    if (campo.includes('direccion_inmueble') || campo.includes('inmueble')) {
        return (propiedad?.direccion || propiedad?.ubicacion || cliente?.direccion || '').toString();
    }

    if (campo.includes('precio') || campo.includes('renta') || campo.includes('presupuesto') || campo.includes('importe')) {
        if (propiedad?.precio) {
            return String(propiedad.precio);
        }
        if (cliente?.presupuesto) {
            return String(cliente.presupuesto);
        }
    }

    if (campo.includes('propietario') || campo.includes('vendedor') || campo.includes('arrendador') || campo.includes('entrega_de')) {
        return (cliente?.full_name || '').toString();
    }

    if (campo.includes('comprador') || campo.includes('cliente') || campo.includes('arrendatario') || campo.includes('interesado') || campo.includes('oferente') || campo.includes('entrega_a')) {
        return (cliente?.full_name || '').toString();
    }

    if (campo.includes('agente')) {
        return '<?php echo e($_SESSION['usuario']['nombre'] ?? 'Agente'); ?>';
    }

    if (campo.includes('agencia')) {
        return 'TinoProp';
    }

    if (campo.includes('operacion')) {
        return (propiedad?.operacion || cliente?.operacion || '').toString();
    }

    if (campo.includes('referencia')) {
        return (propiedad?.referencia || '').toString();
    }

    if (campo.includes('zona')) {
        return (cliente?.zona_interesada || propiedad?.ubicacion || '').toString();
    }

    return '';
}

function autocompletarCamposPlantilla() {
    const { entityType } = entidadSeleccionadaPlantilla();
    const cliente = entityType === 'cliente' ? clienteActualData() : null;
    const propiedad = entityType === 'propiedad' ? propiedadActualData() : null;

    const inputs = templateFields.querySelectorAll('input[name]');
    inputs.forEach((input) => {
        if (input.value.trim() !== '') {
            return;
        }

        const sugerencia = sugerenciaPorCampo(input.name, cliente, propiedad);
        if (sugerencia) {
            input.value = sugerencia;
        }
    });
}

document.getElementById('btn_autocompletar').addEventListener('click', autocompletarCamposPlantilla);
document.getElementById('tpl_cliente_id').addEventListener('change', autocompletarCamposPlantilla);
document.getElementById('tpl_propiedad_id').addEventListener('change', autocompletarCamposPlantilla);
tplEntityType.addEventListener('change', autocompletarCamposPlantilla);

function tieneFirma() {
    const pixels = firmaCtx.getImageData(0, 0, firmaCanvas.width, firmaCanvas.height).data;
    for (let i = 3; i < pixels.length; i += 4) {
        if (pixels[i] !== 0) return true;
    }
    return false;
}

function construirDocumentoPdf() {
    const tpl = plantillaActiva();
    const form = document.getElementById('formPlantilla');
    const formData = new FormData(form);
    const { entityType, entityId } = entidadSeleccionadaPlantilla();
    const values = {};

    tpl.campos.forEach((campo) => {
        values[campo.name] = (formData.get(campo.name) || '').toString().trim();
    });

    for (const campo of tpl.campos) {
        if (!values[campo.name]) {
            return { ok: false, message: `Completa el campo: ${campo.label}` };
        }
    }

    if (tpl.requiere_firma && !tieneFirma()) {
        return { ok: false, message: 'La plantilla requiere firma.' };
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const fecha = new Date().toLocaleDateString('es-ES');

    doc.setFontSize(16);
    doc.text(tpl.nombre, 14, 20);
    doc.setFontSize(10);
    doc.text(`Fecha: ${fecha}`, 14, 28);
    doc.text(`Entidad: ${entityType} #${entityId}`, 14, 34);

    let y = 46;
    doc.setFontSize(12);
    tpl.campos.forEach((campo) => {
        const value = values[campo.name] || '-';
        const line = `${campo.label}: ${value}`;
        const lines = doc.splitTextToSize(line, 180);
        if (y > 260) {
            doc.addPage();
            y = 20;
        }
        doc.text(lines, 14, y);
        y += (lines.length * 7) + 3;
    });

    if (tpl.requiere_firma && tieneFirma()) {
        const firmaData = firmaCanvas.toDataURL('image/png');
        if (y > 220) {
            doc.addPage();
            y = 20;
        }
        doc.text('Firma:', 14, y);
        y += 4;
        doc.addImage(firmaData, 'PNG', 14, y, 70, 25);
    }

    const pdfDataUri = doc.output('datauristring');
    return {
        ok: true,
        tpl,
        entityType,
        entityId,
        values,
        pdfDataUri,
        doc,
    };
}

btnPreviewPdf.addEventListener('click', () => {
    const feedback = document.getElementById('pdf_feedback');
    const built = construirDocumentoPdf();
    if (!built.ok) {
        feedback.textContent = built.message;
        return;
    }

    pendingPdfDataUri = built.pdfDataUri;
    pendingTpl = built.tpl;
    pendingEntity = { entityType: built.entityType, entityId: built.entityId };
    pendingValues = built.values;

    docPreviewFrame.src = pendingPdfDataUri;
    docPreviewModal.classList.add('modal_visible');
    docPreviewModal.setAttribute('aria-hidden', 'false');
    feedback.textContent = 'Vista previa generada.';
});

function cerrarPreview() {
    docPreviewModal.classList.remove('modal_visible');
    docPreviewModal.setAttribute('aria-hidden', 'true');
    docPreviewFrame.src = 'about:blank';
}

docPreviewCancelar.addEventListener('click', cerrarPreview);
docPreviewModal.addEventListener('click', (event) => {
    if (event.target === docPreviewModal) {
        cerrarPreview();
    }
});

docPreviewConfirmar.addEventListener('click', async () => {
    const feedback = document.getElementById('pdf_feedback');

    if (!pendingPdfDataUri || !pendingTpl || !pendingEntity || !pendingValues) {
        feedback.textContent = 'No hay vista previa preparada.';
        return;
    }

    feedback.textContent = 'Guardando PDF...';

    const saveRes = await fetch('api/documentacion.php?action=save_pdf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            entity_type: pendingEntity.entityType,
            entity_id: pendingEntity.entityId,
            template_key: pendingTpl.key,
            template_name: pendingTpl.nombre,
            fields: pendingValues,
            pdf_base64: pendingPdfDataUri,
        }),
    });

    const saveData = await saveRes.json();
    if (!saveData.success) {
        feedback.textContent = saveData.message || 'Error guardando copia.';
        return;
    }

    const link = document.createElement('a');
    link.href = pendingPdfDataUri;
    link.download = saveData.download_name || `${pendingTpl.key}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();

    cerrarPreview();
    feedback.textContent = 'PDF guardado y descargado correctamente.';
});

document.getElementById('formPlantilla').addEventListener('submit', async (event) => {
    event.preventDefault();
    const feedback = document.getElementById('pdf_feedback');
    feedback.textContent = 'Usa “Vista previa PDF” y confirma para guardar/descargar.';
});

</script>
