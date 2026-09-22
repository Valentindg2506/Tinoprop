</main>
<footer>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <strong class="text-white">Tinoprop</strong> — CRM Inmobiliario
            </div>
            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <a href="privacidad.php">Privacidad</a> ·
                <a href="cookies.php">Cookies</a> ·
                <a href="aviso-legal.php">Aviso Legal</a> ·
                <a href="terminos.php">Términos</a>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// ── Sustituir todos los marcadores [NOMBRE_CAMPO] con los datos del .env ──────
$html = ob_get_clean();

// Mapa de marcadores → valores del entorno
// Clave: texto entre corchetes en los documentos. Valor: constante definida en config.php
$markers = [
    // Marcadores usados en los documentos legales
    'NOMBRE EMPRESA'            => LEGAL_NOMBRE,
    'NOMBRE EMPRESA / RAZÓN SOCIAL' => LEGAL_NOMBRE,
    'RAZÓN SOCIAL'              => LEGAL_NOMBRE,
    'CIF'                       => LEGAL_CIF,
    'CIF/NIF'                   => LEGAL_CIF,
    'NIF'                       => LEGAL_CIF,
    'DIRECCIÓN'                 => LEGAL_DIRECCION_COMPLETA,
    'DIRECCIÓN FISCAL COMPLETA' => LEGAL_DIRECCION_COMPLETA,
    'DIRECCIÓN FISCAL'          => LEGAL_DIRECCION_COMPLETA,
    'DIRECCIÓN FISCAL COMPLETA, CIUDAD, CP, ESPAÑA' => LEGAL_DIRECCION_COMPLETA,
    'CIUDAD'                    => LEGAL_CIUDAD,
    'EMAIL PRIVACIDAD'          => LEGAL_EMAIL,
    'EMAIL PRIVACIDAD, ej: privacidad@tinoprop.es'  => LEGAL_EMAIL,
    'EMAIL DE CONTACTO'         => LEGAL_EMAIL,
    'TELÉFONO'                  => LEGAL_TELEFONO,
    'DATOS DE INSCRIPCIÓN, ej: Registro Mercantil de Madrid, Tomo X, Folio X' => LEGAL_REGISTRO,
    'REGISTRO'                  => LEGAL_REGISTRO,
    'URL DE PRECIOS'            => LEGAL_URL_PRECIOS,
];

foreach ($markers as $marker => $value) {
    if ($value === '') continue; // No sustituir si el valor está vacío (mantener el placeholder visual)

    // Reemplazar span con clase placeholder: <span class="placeholder">[MARKER]</span>
    $html = str_replace(
        '<span class="placeholder">[' . $marker . ']</span>',
        htmlspecialchars($value),
        $html
    );
    // También reemplazar texto plano: [MARKER] (para uso directo en textos sin span)
    $html = str_replace('[' . $marker . ']', htmlspecialchars($value), $html);
}

echo $html;
