<?php
/**
 * Cifrado simétrico AES-256-GCM para campos sensibles en BD.
 * Usado para: api_key de IA, tokens de WhatsApp, etc.
 *
 * La clave se lee de APP_KEY en el .env.
 * Formato almacenado: base64(IV[12] + TAG[16] + CIPHERTEXT)
 * Prefijo "enc:" para distinguir valores cifrados de texto plano.
 */

/**
 * Obtiene la clave de cifrado de 32 bytes desde el entorno.
 * Lanza RuntimeException si no está configurada (bloquea en producción).
 */
function getAppKey(): string {
    $key = getenv('APP_KEY') ?: '';
    if ($key === '') {
        // En desarrollo, generar clave efímera con advertencia
        if ((getenv('APP_ENV') ?: 'production') !== 'production') {
            return str_repeat("\x00", 32); // Solo para dev — datos no persistentes
        }
        throw new RuntimeException('APP_KEY no está configurada en el .env. Genera una con: php -r "echo base64_encode(random_bytes(32));"');
    }
    $decoded = base64_decode($key, true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new RuntimeException('APP_KEY inválida: debe ser un base64 de exactamente 32 bytes.');
    }
    return $decoded;
}

/**
 * Cifra un valor con AES-256-GCM.
 * Devuelve "enc:<base64>" o el valor original si falla.
 */
function encryptField(string $value): string {
    if ($value === '') return '';
    // Si ya está cifrado, no volver a cifrar
    if (str_starts_with($value, 'enc:')) return $value;

    try {
        $key = getAppKey();
        $iv  = random_bytes(12); // 96 bits — recomendado para GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        if ($ciphertext === false) return $value; // fallback sin cifrar si openssl falla
        return 'enc:' . base64_encode($iv . $tag . $ciphertext);
    } catch (Throwable $e) {
        error_log('[Encryption] encryptField falló: ' . $e->getMessage());
        return $value;
    }
}

/**
 * Descifra un valor cifrado con encryptField().
 * Si el valor no está cifrado (no tiene prefijo "enc:"), lo devuelve tal cual
 * para compatibilidad con datos existentes en texto plano.
 */
function decryptField(string $value): string {
    if ($value === '') return '';
    if (!str_starts_with($value, 'enc:')) return $value; // valor antiguo en texto plano

    try {
        $key  = getAppKey();
        $data = base64_decode(substr($value, 4), true);
        if ($data === false || strlen($data) < 28) return ''; // IV(12) + TAG(16) mínimo

        $iv         = substr($data, 0, 12);
        $tag        = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return $plain !== false ? $plain : '';
    } catch (Throwable $e) {
        error_log('[Encryption] decryptField falló: ' . $e->getMessage());
        return '';
    }
}
