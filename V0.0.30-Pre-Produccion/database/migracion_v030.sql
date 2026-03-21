-- ============================================================
-- Migración V0.0.30 — Pre-Producción / Marco Legal
-- ============================================================
-- Ejecutar sobre la BD existente de TinoProp (tras migracion_v029.sql)
-- Añade columna para control de aceptación de Términos y Condiciones.
-- ============================================================

-- 1. Columna terminos_aceptados en tabla usuarios
--    Almacena la versión de términos que el usuario aceptó (ej: '2026-03-02').
--    NULL = no ha aceptado ninguna versión.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS terminos_aceptados VARCHAR(20) DEFAULT NULL
    COMMENT 'Versión de Términos y Condiciones aceptada';

-- ============================================================
-- Notas V0.0.30:
-- - Se añaden 18 nuevas plantillas de documentos inmobiliarios (gestionado en PHP, no en BD).
-- - Se añade sección «Marco Legal» con Aviso Legal, Términos y Condiciones,
--   Política de Privacidad (RGPD/LOPDGDD) y Política de Cookies.
-- - Flujo de aceptación obligatoria: los usuarios deben aceptar la versión vigente
--   de los Términos antes de poder acceder a la aplicación.
-- - La constante TERMINOS_VERSION en helpers.php controla la versión activa.
--   Al actualizar dicha constante, todos los usuarios deberán re-aceptar.
-- ============================================================
