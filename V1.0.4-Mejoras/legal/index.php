<?php
$pageTitle      = 'Centro Legal y de Privacidad';
$pageLastUpdate = '16/04/2026';
require_once __DIR__ . '/_layout.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <a href="privacidad.php" class="text-decoration-none">
            <div class="legal-card h-100 text-center py-4">
                <i class="bi bi-shield-lock-fill text-success fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold">Política de Privacidad</h5>
                <p class="small text-muted mb-0">Cómo tratamos tus datos personales</p>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="cookies.php" class="text-decoration-none">
            <div class="legal-card h-100 text-center py-4">
                <i class="bi bi-cookie text-success fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold">Política de Cookies</h5>
                <p class="small text-muted mb-0">Qué cookies usamos y para qué</p>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="aviso-legal.php" class="text-decoration-none">
            <div class="legal-card h-100 text-center py-4">
                <i class="bi bi-file-earmark-text-fill text-success fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold">Aviso Legal</h5>
                <p class="small text-muted mb-0">Identificación y condiciones de uso</p>
            </div>
        </a>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="terminos.php" class="text-decoration-none">
            <div class="legal-card h-100 text-center py-4">
                <i class="bi bi-file-earmark-check-fill text-success fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold">Términos y Condiciones</h5>
                <p class="small text-muted mb-0">Contrato de servicio SaaS</p>
            </div>
        </a>
    </div>
</div>

<div class="legal-card">
    <h2>Documentos internos de compliance RGPD</h2>
    <p class="text-muted small mb-3">Documentación interna para el equipo. Actualizar con los datos reales de la empresa antes de archivar.</p>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Documento</th><th>Descripción</th><th>Estado</th></tr></thead>
            <tbody>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-success"></i><strong>Contrato Encargado del Tratamiento</strong></td>
                    <td class="small">DPA (Data Processing Agreement) para clientes SaaS</td>
                    <td><span class="badge bg-warning text-dark">Completar datos empresa</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-success"></i><strong>Registro de Actividades (RAT)</strong></td>
                    <td class="small">Art. 30 RGPD — todos los tratamientos documentados</td>
                    <td><span class="badge bg-warning text-dark">Completar datos empresa</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-success"></i><strong>Base Legal por Tratamiento</strong></td>
                    <td class="small">Análisis de la base legal de cada actividad de tratamiento</td>
                    <td><span class="badge bg-success">Listo</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-danger"></i><strong>Evaluación de Riesgos</strong></td>
                    <td class="small">Mapa de riesgos RGPD con probabilidad e impacto</td>
                    <td><span class="badge bg-danger">3 riesgos críticos</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-warning"></i><strong>Medidas de Seguridad</strong></td>
                    <td class="small">Medidas técnicas y organizativas implementadas y pendientes</td>
                    <td><span class="badge bg-warning text-dark">5 pendientes urgentes</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-success"></i><strong>Cláusulas de Consentimiento</strong></td>
                    <td class="small">Textos listos para implementar en formularios y emails</td>
                    <td><span class="badge bg-success">Listo para implementar</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-danger"></i><strong>Checklist RGPD</strong></td>
                    <td class="small">Estado de cumplimiento completo con prioridades</td>
                    <td><span class="badge bg-danger">Revisión urgente</span></td>
                </tr>
                <tr>
                    <td><i class="bi bi-file-earmark-text me-2 text-warning"></i><strong>Requisitos Técnicos</strong></td>
                    <td class="small">Código para implementar las medidas técnicas pendientes</td>
                    <td><span class="badge bg-warning text-dark">Implementar semana 1-2</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2">
        <i class="bi bi-folder me-1"></i> Archivos en <code>legal/docs/</code>
    </p>
</div>

<div class="legal-card">
    <h2>Contacto en materia de privacidad</h2>
    <p>Para ejercer tus derechos de acceso, rectificación, supresión, oposición, portabilidad o limitación del tratamiento, puedes contactar con nosotros en:</p>
    <ul>
        <li><strong>Email:</strong> <span class="placeholder">[EMAIL PRIVACIDAD]</span></li>
        <li><strong>Dirección postal:</strong> <span class="placeholder">[DIRECCIÓN FISCAL], España</span></li>
    </ul>
    <p class="text-muted small">También tienes derecho a presentar una reclamación ante la <strong>Agencia Española de Protección de Datos (AEPD)</strong>: <a href="https://www.aepd.es" target="_blank">www.aepd.es</a></p>
</div>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
