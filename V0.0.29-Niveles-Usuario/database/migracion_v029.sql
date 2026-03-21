-- ============================================
-- Migración V0.0.29 — Sistema Multi-Inmobiliaria con Niveles de Usuario
-- ============================================

-- 1. Tabla de inmobiliarias (cada empresa/agencia que usa el sistema)
CREATE TABLE IF NOT EXISTS inmobiliarias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    cif VARCHAR(20) DEFAULT NULL,
    direccion VARCHAR(250) DEFAULT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    logo_url VARCHAR(300) DEFAULT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    max_usuarios INT UNSIGNED NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inmob_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Inmobiliaria semilla (la primera agencia del sistema)
INSERT INTO inmobiliarias (nombre, email) VALUES ('Mi Inmobiliaria', 'admin@tinoprop.com');

-- 3. Ampliar tabla usuarios con campos multi-tenant
ALTER TABLE usuarios
    ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER id,
    ADD COLUMN avatar_url VARCHAR(300) DEFAULT NULL AFTER rol,
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_url,
    ADD INDEX idx_usr_inmob (inmobiliaria_id);

-- 4. Actualizar rol del admin existente a 'superadmin' y asignar inmobiliaria 1
UPDATE usuarios SET rol = 'superadmin', inmobiliaria_id = 1 WHERE id = 1;

-- 5. Añadir inmobiliaria_id a tablas de datos principales (para multi-tenant)
ALTER TABLE clientes
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_cli_inmob (inmobiliaria_id);

ALTER TABLE prospectos
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_prosp_inmob (inmobiliaria_id);

ALTER TABLE propiedades
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_prop_inmob (inmobiliaria_id);

ALTER TABLE notas
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_notas_inmob (inmobiliaria_id);

ALTER TABLE proceso_propiedades
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_proc_inmob (inmobiliaria_id);

ALTER TABLE visitas
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_vis_inmob (inmobiliaria_id);

ALTER TABLE scraped_propiedades
    ADD COLUMN inmobiliaria_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD INDEX idx_scrap_inmob (inmobiliaria_id);

-- 6. Roles válidos del sistema:
-- superadmin   = Administrador global (gestiona inmobiliarias)
-- jefe         = Dueño/Director de una inmobiliaria  
-- supervisor   = Supervisa agentes de su inmobiliaria
-- marketing    = Gestiona propiedades y contenido comercial
-- agente_comprador = Solo ve secciones de comprador
-- agente_vendedor  = Solo ve secciones de vendedor
-- agente       = Ve secciones de comprador Y vendedor
