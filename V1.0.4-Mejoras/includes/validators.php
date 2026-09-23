<?php
/**
 * Validadores especificos para España
 * DNI, NIE, CIF, telefono, codigo postal, IBAN, referencia catastral
 */

/**
 * Validar DNI español
 * Formato: 8 digitos + 1 letra
 */
function validarDNI($dni) {
    $dni = strtoupper(trim($dni));
    if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni)) return false;

    $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $numero = intval(substr($dni, 0, 8));
    $letraEsperada = $letras[$numero % 23];

    return $dni[8] === $letraEsperada;
}

/**
 * Validar NIE español
 * Formato: X/Y/Z + 7 digitos + 1 letra
 */
function validarNIE($nie) {
    $nie = strtoupper(trim($nie));
    if (!preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $nie)) return false;

    $reemplazos = ['X' => '0', 'Y' => '1', 'Z' => '2'];
    $nieNumero = $reemplazos[$nie[0]] . substr($nie, 1, 7);

    $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $letraEsperada = $letras[intval($nieNumero) % 23];

    return $nie[8] === $letraEsperada;
}

/**
 * Validar CIF español
 * Formato: 1 letra + 7 digitos + 1 digito/letra de control
 */
function validarCIF($cif) {
    $cif = strtoupper(trim($cif));
    if (!preg_match('/^[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J]$/', $cif)) return false;

    $tipoLetra = $cif[0];
    $digitos = substr($cif, 1, 7);
    $control = $cif[8];

    $sumaPares = 0;
    $sumaImpares = 0;

    for ($i = 0; $i < 7; $i++) {
        $d = intval($digitos[$i]);
        if ($i % 2 === 0) { // posiciones impares (0-indexed)
            $doble = $d * 2;
            $sumaImpares += intval($doble / 10) + ($doble % 10);
        } else { // posiciones pares
            $sumaPares += $d;
        }
    }

    $sumaTotal = $sumaPares + $sumaImpares;
    $digitoControl = (10 - ($sumaTotal % 10)) % 10;
    $letraControl = chr(ord('A') + $digitoControl);

    // Algunos CIF usan letra, otros digito
    $letraObligatoria = in_array($tipoLetra, ['K', 'P', 'Q', 'S']);
    $digitoObligatorio = in_array($tipoLetra, ['A', 'B', 'E', 'H']);

    if ($letraObligatoria) {
        return $control === $letraControl;
    } elseif ($digitoObligatorio) {
        return $control === strval($digitoControl);
    } else {
        return $control === strval($digitoControl) || $control === $letraControl;
    }
}

/**
 * Validar DNI, NIE o CIF automaticamente
 */
function validarDocumentoIdentidad($documento) {
    $documento = strtoupper(trim($documento));
    if (empty($documento)) return ['valid' => true, 'type' => null]; // Opcional

    if (preg_match('/^[0-9]/', $documento)) {
        // Empieza por numero: DNI
        if (validarDNI($documento)) return ['valid' => true, 'type' => 'DNI'];
        return ['valid' => false, 'type' => 'DNI', 'message' => 'DNI no valido. Formato: 12345678A'];
    } elseif (preg_match('/^[XYZ]/', $documento)) {
        // Empieza por X, Y o Z: NIE
        if (validarNIE($documento)) return ['valid' => true, 'type' => 'NIE'];
        return ['valid' => false, 'type' => 'NIE', 'message' => 'NIE no valido. Formato: X1234567A'];
    } elseif (preg_match('/^[A-W]/', $documento)) {
        // Empieza por otra letra: CIF
        if (validarCIF($documento)) return ['valid' => true, 'type' => 'CIF'];
        return ['valid' => false, 'type' => 'CIF', 'message' => 'CIF no valido. Formato: B12345678'];
    }

    return ['valid' => false, 'type' => null, 'message' => 'Documento no reconocido. Introduce un DNI, NIE o CIF valido.'];
}

/**
 * Validar telefono español
 * Acepta: +34 612345678, 612345678, 912345678, etc.
 */
function validarTelefono($telefono) {
    if (empty($telefono)) return true; // Opcional
    $limpio = preg_replace('/[\s\-\.\(\)]/', '', $telefono);
    // Formato internacional: +[código país][número] — entre 7 y 15 dígitos
    if (preg_match('/^\+[1-9][0-9]{6,14}$/', $limpio)) return true;
    // Español con o sin +34
    if (preg_match('/^\+?34[6789][0-9]{8}$/', $limpio)) return true;
    // 9 dígitos empezando por 6, 7, 8 o 9 (España sin prefijo)
    if (preg_match('/^[6789][0-9]{8}$/', $limpio)) return true;
    return false;
}

/**
 * Validar codigo postal español (5 digitos, 01-52)
 */
function validarCodigoPostal($cp) {
    if (empty($cp)) return true; // Opcional
    if (!preg_match('/^[0-5][0-9]{4}$/', $cp)) return false;
    $provincia = intval(substr($cp, 0, 2));
    return $provincia >= 1 && $provincia <= 52;
}

/**
 * Validar email
 */
function validarEmail($email) {
    if (empty($email)) return true; // Opcional
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar referencia catastral española
 * 20 caracteres alfanumericos
 */
function validarReferenciaCatastral($ref) {
    if (empty($ref)) return true; // Opcional
    return preg_match('/^[A-Za-z0-9]{20}$/', trim($ref)) === 1;
}

/**
 * Validar IBAN español
 */
function validarIBAN($iban) {
    if (empty($iban)) return true;
    $iban = strtoupper(str_replace(' ', '', $iban));
    if (!preg_match('/^ES[0-9]{22}$/', $iban)) return false;

    // Mover los 4 primeros caracteres al final
    $reordenado = substr($iban, 4) . substr($iban, 0, 4);

    // Convertir letras a numeros (A=10, B=11, ..., Z=35)
    $numerico = '';
    for ($i = 0; $i < strlen($reordenado); $i++) {
        $char = $reordenado[$i];
        if (ctype_alpha($char)) {
            $numerico .= (ord($char) - 55);
        } else {
            $numerico .= $char;
        }
    }

    // Modulo 97 debe dar 1
    return bcmod($numerico, '97') === '1';
}

/**
 * Sanitizar y formatear telefono
 */
function formatTelefono($telefono) {
    $limpio = preg_replace('/[^0-9+]/', '', $telefono);
    if (strlen($limpio) === 9) {
        return '+34 ' . substr($limpio, 0, 3) . ' ' . substr($limpio, 3, 3) . ' ' . substr($limpio, 6, 3);
    }
    return $telefono;
}

/**
 * Validar formulario de propiedad
 */
function validarPropiedad($data) {
    $errors = [];
    if (empty($data['titulo'])) $errors[] = 'El titulo es obligatorio.';
    if (empty($data['tipo'])) $errors[] = 'El tipo de inmueble es obligatorio.';
    if (empty($data['operacion'])) $errors[] = 'La operacion es obligatoria.';
    if (empty($data['localidad'])) $errors[] = 'La localidad es obligatoria.';
    if (empty($data['provincia'])) $errors[] = 'La provincia es obligatoria.';
    if ($data['precio'] <= 0) $errors[] = 'El precio debe ser mayor que 0.';
    if (!empty($data['codigo_postal']) && !validarCodigoPostal($data['codigo_postal'])) {
        $errors[] = 'El codigo postal no es valido (formato: 28001).';
    }
    if (!empty($data['referencia_catastral']) && !validarReferenciaCatastral($data['referencia_catastral'])) {
        $errors[] = 'La referencia catastral debe tener 20 caracteres alfanumericos.';
    }
    return $errors;
}

/**
 * Validar formulario de cliente
 */
function validarCliente($data) {
    $errors = [];
    if (empty($data['nombre'])) $errors[] = 'El nombre es obligatorio.';
    if (empty($data['tipo'])) $errors[] = 'Selecciona al menos un tipo de cliente.';
    if (!empty($data['email']) && !validarEmail($data['email'])) {
        $errors[] = 'El email no es valido.';
    }
    if (!empty($data['telefono']) && !validarTelefono($data['telefono'])) {
        $errors[] = 'El telefono no es valido. Formato: 612345678 o +34612345678.';
    }
    if (!empty($data['telefono2']) && !validarTelefono($data['telefono2'])) {
        $errors[] = 'El telefono secundario no es valido.';
    }
    if (!empty($data['dni_nie_cif'])) {
        $docResult = validarDocumentoIdentidad($data['dni_nie_cif']);
        if (!$docResult['valid']) {
            $errors[] = $docResult['message'];
        }
    }
    if (!empty($data['codigo_postal']) && !validarCodigoPostal($data['codigo_postal'])) {
        $errors[] = 'El codigo postal no es valido.';
    }
    return $errors;
}

/**
 * Validar formulario de prospecto
 */
function validarProspecto($data) {
    $errors = [];
    if (empty($data['nombre'])) $errors[] = 'El nombre es obligatorio.';
    if (!empty($data['email']) && !validarEmail($data['email'])) {
        $errors[] = 'El email no es valido.';
    }
    if (!empty($data['telefono']) && !validarTelefono($data['telefono'])) {
        $errors[] = 'El telefono no es valido. Formato: 612345678 o +34612345678.';
    }
    if (!empty($data['telefono2']) && !validarTelefono($data['telefono2'])) {
        $errors[] = 'El telefono secundario no es valido.';
    }
    if (!empty($data['codigo_postal']) && !validarCodigoPostal($data['codigo_postal'])) {
        $errors[] = 'El codigo postal no es valido.';
    }
    return $errors;
}
