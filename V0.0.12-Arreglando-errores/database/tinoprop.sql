CREATE DATABASE IF NOT EXISTS tinoprop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tinoprop;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('vendedor','comprador') NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(120) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    email VARCHAR(150) NOT NULL,
    operacion VARCHAR(50) NOT NULL,
    direccion VARCHAR(200) DEFAULT NULL,
    genero VARCHAR(50) DEFAULT NULL,
    fecha_nacimiento DATE DEFAULT NULL,
    presupuesto DECIMAL(12,2) DEFAULT NULL,
    zona_interesada VARCHAR(120) DEFAULT NULL,
    comentarios TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS prospectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('vendedor','comprador') NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    interes VARCHAR(200) NOT NULL,
    estado VARCHAR(50) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS propiedades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo ENUM('vendedor','comprador') NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    ubicacion VARCHAR(120) NOT NULL,
    direccion VARCHAR(200) DEFAULT NULL,
    metros INT DEFAULT NULL,
    habitaciones INT DEFAULT NULL,
    banos INT DEFAULT NULL,
    precio DECIMAL(12,2) NOT NULL,
    moneda VARCHAR(10) NOT NULL DEFAULT 'EUR',
    periodo VARCHAR(20) DEFAULT NULL,
    operacion ENUM('venta','alquiler') NOT NULL,
    estado VARCHAR(50) NOT NULL,
    referencia VARCHAR(80) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    visitas INT NOT NULL DEFAULT 0,
    ofertas INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('cliente','propiedad') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    tipo ENUM('Nota','Aviso') NOT NULL DEFAULT 'Nota',
    texto TEXT NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notas_entity (entity_type, entity_id),
    CONSTRAINT fk_notas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
);

INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@tinoprop.com', '$2y$10$HitxwSOeHIfBuRBtiwl53.b16auDL/xvFhtbCXYRkChtxa7iUEplC', 'admin');

INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, direccion, genero, fecha_nacimiento, presupuesto, zona_interesada, comentarios)
VALUES
('vendedor', 'Valentin', 'De Gennaro', '666-111-222', 'valentin@email.com', 'Venta', 'Av. del Puerto, 12, Valencia', 'Masculino', '1990-05-10', 250000, 'Ruzafa', 'Cliente interesado en aticos por la zona de Ruzafa.'),
('vendedor', 'Ana', 'Lopez', '666-333-444', 'ana.lopez@email.com', 'Venta', 'Carrer Colon, 4, Valencia', 'Femenino', '1988-11-02', 320000, 'Centro', 'Busca vender chalet familiar.'),
('comprador', 'Carlos', 'Perez', '666-555-666', 'carlos.p@email.com', 'Compra', 'Av. Blasco Ibanez, 54, Valencia', 'Masculino', '1994-01-15', 190000, 'Campanar', 'Prefiere piso con balcon.'),
('comprador', 'Maria', 'Garcia', '666-777-888', 'maria.g@email.com', 'Compra', 'Calle la Paz, 9, Valencia', 'Femenino', '1992-06-22', 210000, 'Ruzafa', 'Interesada en zona con servicios.');

INSERT INTO prospectos (tipo, nombre, interes, estado, telefono)
VALUES
('vendedor', 'Laura Gomez', 'Busca atico centro', 'nuevo', '600-111-222'),
('vendedor', 'Pedro Ruiz', 'Vende piso playa', 'contactado', '611-222-333'),
('vendedor', 'Marta Diaz', 'Inversion local', 'no_contesta', '622-333-444'),
('vendedor', 'Javier S.', 'Quiere vender ya', 'vender', '633-444-555'),
('comprador', 'Sofia L.', 'Compra primera vivienda', 'comprar', '644-555-666'),
('comprador', 'Carlos M.', 'Alquiler vacacional', 'nuevo', '655-666-777'),
('comprador', 'Luis T.', 'Venta heredada', 'descartado', '666-777-888'),
('comprador', 'Ana B.', 'Ya compro', 'realizado', '677-888-999');

INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, periodo, operacion, estado, referencia, descripcion, visitas, ofertas)
VALUES
('vendedor', 'Apartamento Centrico', 'Piso', 'Valencia', 'Calle Colon 25', 95, 2, 1, 185000, 'EUR', NULL, 'venta', 'Disponible', 'TP-VAL-101', 'Apartamento reformado con balcon y luz natural.', 12, 3),
('vendedor', 'Chalet con Jardin', 'Chalet', 'Torrent', 'Av. Valencia 12', 180, 4, 2, 320000, 'EUR', NULL, 'venta', 'Reservado', 'TP-TOR-202', 'Chalet familiar con jardin amplio.', 9, 1),
('vendedor', 'Atico Luminoso', 'Atico', 'Paterna', 'Calle Mayor 7', 110, 3, 2, 245000, 'EUR', NULL, 'venta', 'Disponible', 'TP-PAT-103', 'Atico con terraza y vistas abiertas.', 12, 3),
('comprador', 'Duplex Moderno', 'Duplex', 'Valencia', 'Calle Serreria 18', 120, 3, 2, 255000, 'EUR', NULL, 'venta', 'Disponible', 'TP-VAL-204', 'Duplex moderno con cocina abierta.', 7, 2),
('vendedor', 'Loft Urban', 'Loft', 'Valencia', 'Calle Sueca 3', 60, 1, 1, 850, 'EUR', 'mes', 'alquiler', 'Disponible', 'TP-VAL-301', 'Loft ideal para profesionales.', 9, 1),
('vendedor', 'Piso Familiar', 'Piso', 'Burjassot', 'Calle Valencia 40', 90, 3, 2, 1050, 'EUR', 'mes', 'alquiler', 'Reservado', 'TP-BUR-302', 'Piso amplio con garaje.', 5, 1),
('comprador', 'Piso con Terraza', 'Piso', 'Valencia', 'Av. del Puerto 44', 85, 2, 1, 980, 'EUR', 'mes', 'alquiler', 'Disponible', 'TP-VAL-401', 'Terraza amplia y luminoso.', 6, 1),
('comprador', 'Estudio con Luz', 'Estudio', 'Valencia', 'Calle Cuba 22', 40, 1, 1, 700, 'EUR', 'mes', 'alquiler', 'Reservado', 'TP-VAL-403', 'Estudio funcional cerca del metro.', 4, 0);

INSERT INTO notas (entity_type, entity_id, tipo, texto, usuario_id)
VALUES
('cliente', 1, 'Aviso', 'Llamar el viernes por la tarde.', 1),
('cliente', 1, 'Nota', 'Prefiere visitas por la manana.', 1),
('cliente', 1, 'Aviso', 'Enviar listado de aticos nuevos.', 1),
('propiedad', 1, 'Aviso', 'Preparar fotos nuevas antes de publicacion.', 1),
('propiedad', 1, 'Nota', 'Cliente interesado en visita el jueves.', 1),
('propiedad', 1, 'Aviso', 'Revisar certificado energetico.', 1);
