-- ============================================================
-- Migración V0.0.31 Pre-Producción
-- Ejecutar DESPUÉS de migracion_v030.sql
-- ============================================================

-- 1. Tabla de rate limiting para login (anti brute-force)
CREATE TABLE IF NOT EXISTS login_intentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_li_ip (ip),
    INDEX idx_li_email (email),
    INDEX idx_li_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FIN migración V0.0.31
