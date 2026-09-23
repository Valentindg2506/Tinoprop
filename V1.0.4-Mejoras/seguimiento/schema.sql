-- ============================================================
--  CAS Real Estate — Esquema MySQL
--  Ejecutar UNA VEZ en tu base de datos.
--  Después ejecutar setup.php para crear el usuario admin.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── TOKENS DE SESIÓN ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  usuario       VARCHAR(80)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  creado_en     DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tokens (
  token      VARCHAR(64)  PRIMARY KEY,
  usuario_id INT          NOT NULL,
  expires_at DATETIME     NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CAPTACIÓN ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS propiedades (
  id   VARCHAR(20)  PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  sub  VARCHAR(200),
  addr VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inquilinos (
  id           BIGINT       PRIMARY KEY,
  n            VARCHAR(150),
  t            VARCHAR(50),
  email        VARCHAR(150),
  oc           VARCHAR(100),
  trab         VARCHAR(150),
  bud          VARCHAR(20),
  ent          VARCHAR(20),
  dur          VARCHAR(50),
  de           VARCHAR(80),
  ac           VARCHAR(50),
  mas          VARCHAR(50),
  fum          VARCHAR(50),
  cal          VARCHAR(30),
  notas        TEXT,
  estado       VARCHAR(50),
  fecha        VARCHAR(30),
  propId       VARCHAR(20),
  seguimiento  VARCHAR(20),
  segHora      VARCHAR(10),
  historial    JSON,
  -- Campos nuevos v2
  edad         VARCHAR(10),
  estudia      TINYINT(1)   DEFAULT 0,
  trabajaFijo  TINYINT(1)   DEFAULT 0,
  nacionalidad VARCHAR(80),
  calManual    VARCHAR(30),
  sexo         VARCHAR(20),
  FOREIGN KEY (propId) REFERENCES propiedades(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Script ALTER para bases existentes (ejecutar si la tabla ya existe):
-- ALTER TABLE inquilinos
--   ADD COLUMN IF NOT EXISTS edad         VARCHAR(10)  DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS estudia      TINYINT(1)   DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS trabajaFijo  TINYINT(1)   DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS nacionalidad VARCHAR(80)  DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS calManual    VARCHAR(30)  DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS sexo         VARCHAR(20)  DEFAULT NULL;

CREATE TABLE IF NOT EXISTS visitas (
  id         BIGINT       PRIMARY KEY,
  proId      BIGINT,
  proNombre  VARCHAR(150),
  proTel     VARCHAR(50),
  propId     VARCHAR(20),
  fecha      VARCHAR(20),
  hora       VARCHAR(10),
  duracion   INT          DEFAULT 60,
  notas      TEXT,
  estado     VARCHAR(30)  DEFAULT 'Pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plantillas (
  id  VARCHAR(10)  PRIMARY KEY,
  n   VARCHAR(10),
  t   VARCHAR(100),
  txt TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SEGUIMIENTO ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS propietarios (
  id           VARCHAR(40)  PRIMARY KEY,
  nombre       VARCHAR(150),
  telefono     VARCHAR(50),
  zona         VARCHAR(100),
  route        CHAR(1)      DEFAULT 'A',
  sentMsgs     JSON,
  lastSentDate DATE,
  status       VARCHAR(20)  DEFAULT 'activo',
  createdAt    DATE,
  notes        TEXT,
  reactions    JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- clave-valor JSON para config de seguimiento
CREATE TABLE IF NOT EXISTS seguimiento_meta (
  clave VARCHAR(50) PRIMARY KEY,
  valor JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  SEMILLAS
-- ============================================================

-- ── Propiedades ──────────────────────────────────────────────
INSERT IGNORE INTO propiedades (id, name, sub, addr) VALUES
('p1', 'San Pío X',       '1.200€/mes · No fumadores', 'Calle San Pío X 29, Valencia'),
('p2', 'Altobuey',        '',                           'Calle Campillo de Altobuey 22, puerta 8, piso 3, Valencia'),
('p3', 'Duque de Gaeta',  '',                           'Duque de Gaeta 39, puerta 6, piso 3, Valencia'),
('p4', 'Picaio',          '',                           'Calle Picaio 25, puerta 8, escalera B, piso 2, Valencia');

-- ── Plantillas de mensajes (Captación) ───────────────────────
INSERT IGNORE INTO plantillas (id, n, t, txt) VALUES
('T1','1','Primer contacto',
'Hola [NOMBRE],

Gracias por escribir. Soy Mariano de CAS Real Estate.

Te hago unas preguntas rápidas para saber si encajamos:

1. ¿Cuál es tu ocupación?
2. ¿Dónde trabajas en Valencia?
3. ¿Vienes solo o acompañado?
4. ¿Cuándo necesitas entrar?
5. ¿11 meses exactos o flexible?

Con eso vemos si el piso te encaja y coordinamos visita.

Saludos,
Mariano'),
('T2','2','Presupuesto',
'El piso está a 1.200€/mes + fianza 2 meses (2.400€).

Total para entrar: 3.600€.

¿Eso entra en tu budget?'),
('T3','3','Sin ascensor',
'Buena pregunta. Quinta planta a mano, es verdad. PERO:

- Vistas completamente despejadas
- Luz natural todo el día
- Muy tranquilo, sin ruido de calle
- Si teletrabajas, lo vas a agradecer

¿Trabajarías desde el piso o salís mucho?'),
('T4','4','WiFi / equipamiento',
'Sí, WiFi incluido y configurado.

Cocina completa: vitrocerámica, horno, microondas, nevera. También lavadora y aire acondicionado en salón y dormitorio.

Llegás con tu maleta y ya está.'),
('T5','5','Confirmar visita',
'Perfecto, vemos la visita.

¿Qué día/hora te va mejor?

- Hoy tarde
- Mañana
- Este fin de semana

Dime y queda confirmado.'),
('T6','6','Post-visita (interesado)',
'¿Qué te pareció el piso?

Si te interesa avanzar, necesitamos:
- DNI + nómina o justificante de ingresos
- Fianza: 2.400€ (2 meses)
- Primer mes: 1.200€

Total entrada: 3.600€

¿Lo cerramos?'),
('T7','7','Post-visita (no interesado)',
'Sin problema, te agradezco el tiempo.

¿Hay algo que no te convenció? Por si tenemos otra opción.

Si en el futuro necesitas algo en Valencia, aquí estoy.'),
('T8','8','Seguimiento 48h',
'Hola [NOMBRE], no tuve respuesta de tu parte.

Sin drama. Si te sigue interesando el piso, avisame. Tenemos varios prospectos mirándolo.

¿Qué tal estás?'),
('T9','9','Seguimiento 1 semana',
'[NOMBRE], último mensaje de mi parte.

Si en algún momento el piso te interesa o necesitás algo en Valencia, aquí estoy.

Un saludo.');

-- ── Inquilinos (10 prospectos semilla) ───────────────────────
INSERT IGNORE INTO inquilinos
  (id,n,t,email,oc,trab,bud,ent,dur,de,ac,mas,fum,cal,notas,estado,fecha,propId,seguimiento,segHora,historial)
VALUES
(1000001,'Carlos','613 35 90 28','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000002,'Salvatore','603 86 04 56','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000003,'Valeria Fernandez','678 71 28 54','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000004,'Brian Cirne','+55 21 99917-0363','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000005,'Mamadou Ndiaye','664 72 08 12','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000006,'Giulia','+31 6 37348640','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000007,'Ricardo Alberto Escobar Gasca','621 09 40 18','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000008,'Salvatore Lieto','624 64 60 13','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000009,'Danique','+31 6 25209372','','','','','','','—','—','—','—','Tibio','','Nuevo','27/05/2026','p1','','','[]'),
(1000010,'Sin Nombre','+31 6 25209372','','','','','','','—','—','—','—','Tibio','Mismo número que Danique','Nuevo','27/05/2026','p1','','','[]');

-- ── Propietarios (~90 contactos de Seguimiento) ──────────────
INSERT IGNORE INTO propietarios
  (id,nombre,telefono,zona,route,sentMsgs,lastSentDate,status,createdAt,notes,reactions)
VALUES
('mplmp7ongshy','Mariano','624200660','Arrancapins','B','["C1"]','2026-05-25','activo','2026-05-26','','{}'),
('mplmtiorc4gn','Ejemplo 2','722814936','Paterna','B','["C1"]','2026-05-26','activo','2026-05-26','','{}'),
('mpme12t1offa','Sonia','646843805','Alboraya','B','["V6"]','2026-05-26','activo','2026-05-26','','{}'),
('mpmer9pfc02m','Jorge','+34654801326','Aldaia','B','["C1"]','2026-05-26','activo','2026-05-26','','{}'),
('mpmex8xnuwve','Jose','+34660810335','El Pla del Real','A','["C1"]','2026-05-26','activo','2026-05-26','','{}'),
('mpmf9auu5286','Paco','+34676363519','El Carme','B','["C1"]','2026-05-26','activo','2026-05-26','','{}'),
('mpmfdrg2iq7o','Rosa','651775973','Torrent','B','["N1","N4"]','2026-05-26','activo','2026-05-26','','{}'),
('mpmflalbctcl','Laura','667 73 30 52','Algirós','B','["C1"]','2026-05-26','activo','2026-05-26','','{}'),
('mpoe9q87hoji','Jose','+34624530524','Moret 7','A','[]',NULL,'activo','2026-05-01','El telefono 2 es del hijo, dijo que se comuniquen con el padre, para mi que ni leyo el mensaje c! seguimiento','{}'),
('mpoe9q876927','Patricia','','Marcel·lí 21','A','[]',NULL,'activo','2026-04-29','Intentar localizar el telefono de Patricia','{}'),
('mpoe9q8727x9','German','+34629456697','Valencia','A','[]',NULL,'activo','2026-04-29','','{}'),
('mpoe9q871usj','Monica','+34651894428','','A','[]',NULL,'activo','2026-04-29','Hablarle con tono de locutor con seguridad.','{}'),
('mpoe9q87wfnl','Raquel','679344509','','A','[]',NULL,'activo','2026-04-29','Mensaje ws','{}'),
('mpoe9q87e3qh','Maravilla','641269451','','A','[]',NULL,'activo','2026-04-29','','{}'),
('mpoe9q87tox9','Pablo o Aroa','+34600259835','Valencia','A','[]',NULL,'activo','2026-04-29','','{}'),
('mpoe9q87s33a','Pedro','+34659719466','','A','[]',NULL,'activo','2026-04-28','Preguntar si se puede hoy ir a conocer el piso','{}'),
('mpoe9q87hguz','Anastasiia','+34633522363','','A','[]',NULL,'activo','2026-04-24','','{}'),
('mpoe9q87dhi9','Maica','+34610420825','','A','[]',NULL,'activo','2026-04-24','','{}'),
('mpoe9q873gok','Ruben','+34652965204','','A','[]',NULL,'activo','2026-04-24','Ver si sigue a la venta y que mensaje de reactivacion Valor enviar','{}'),
('mpoe9q8745e2','Sergio','+34634695234','','A','[]',NULL,'activo','2026-04-24','','{}'),
('mpoe9q87zgbw','Patiruclar','+34643269448','San Marceli','A','[]',NULL,'activo','2026-04-25','Es agencia y colabora - Ver si aparecio nueva publicacion','{}'),
('mpoe9q87mjhx','Maria','+34629827892','','A','[]',NULL,'activo','2026-04-25','','{}'),
('mpoe9q87m264','Beatriz','+34650285569','','A','[]',NULL,'activo','2026-04-25','','{}'),
('mpoe9q872z1o','Miguel','+34654185097','','A','[]',NULL,'activo','2026-04-26','','{}'),
('mpoe9q87ucsj','Paritcular','+34634981553','','A','[]',NULL,'activo','2026-04-26','','{}'),
('mpoe9q87qvnq','Antonella','+34664088242','','A','[]',NULL,'activo','2026-04-22','','{}'),
('mpoe9q87nmha','julia','+34680720275','','A','[]',NULL,'activo','2026-04-22','No me dejo hablar, se ve que la llamaron varios, como que ya le explico a muchos','{}'),
('mpoe9q87sf9h','Ramona','+34650145118','','A','[]',NULL,'activo','2026-04-23','','{}'),
('mpoe9q879vx1','Maribel','+34645461286','','A','[]',NULL,'activo','2026-04-23','','{}'),
('mpoe9q87tagd','Lola','+34656314858','','A','[]',NULL,'activo','2026-04-23','Solo valor con desapego, no me dejo hablar','{}'),
('mpoe9q872nu4','Valentin','+34641025995','Arrancapins','A','[]',NULL,'activo','2025-02-20','','{}'),
('mpoe9q874ntw','Mario','+34659377821','','A','[]',NULL,'activo','2026-04-20','','{}'),
('mpoe9q87kwyv','Javier','+34607463642','','A','[]',NULL,'activo','2026-04-21','','{}'),
('mpoe9q87ujb2','Pilar','+34629920553','','A','[]',NULL,'activo','2026-04-20','','{}'),
('mpoe9q87c7px','Maria','+34649143598','','A','[]',NULL,'activo','2026-04-20','Analizar Lystos','{}'),
('mpoe9q87gcfh','Marien','+34615627514','','A','[]',NULL,'activo','2026-04-17','Preguntar por el tabique','{}'),
('mpoe9q872201','Cristina','+34627557331','','A','[]',NULL,'activo','2026-04-17','','{}'),
('mpoe9q87hxfq','Amparo','+34649683143','','A','[]',NULL,'activo','2026-04-17','','{}'),
('mpoe9q87yqf1','Marina','+34663509729','','A','[]',NULL,'activo','2026-04-17','Dijo que la llame el miercoles para quedar, que tenia que hacer unos arreglos.','{}'),
('mpoe9q87teyn','Santiago','+34670978180','','A','[]',NULL,'activo','2026-04-17','','{}'),
('mpoe9q87raby','Javier','+34666494494','','A','[]',NULL,'activo','2026-04-17','Llamar para confirmar visita','{}'),
('mpoe9q87kpsj','Sandra','+34653360861','','A','[]',NULL,'activo','2026-04-18','Tiene que ser muy persuaisvo, son vendedores que se la saben todas','{}'),
('mpoe9q87wz75','Cristina','+34673059152','','A','[]',NULL,'activo','2026-04-18','Luego llamar para ver como le fue con las visitas. Enviar info sobre esto.','{}'),
('mpoe9q878mmh','Pedro','616408686','','A','[]',NULL,'activo','2026-04-18','','{}'),
('mpoe9q87wnez','Marina','663509729','','A','[]',NULL,'activo','2026-04-19','','{}'),
('mpoe9q87v1y6','Petro','652537973','','A','[]',NULL,'activo','2026-04-19','','{}'),
('mpoe9q87910u','Jose','+34614050523','','A','[]',NULL,'activo','2026-04-15','Seguimiento fino, ni me dejo hablar','{}'),
('mpoe9q87xuns','Victor','+34611689248','','A','[]',NULL,'activo','2026-04-16','','{}'),
('mpoe9q8711ym','Segundo','+34687676789','','A','[]',NULL,'activo','2026-04-15','Enviar M1','{}'),
('mpoe9q87q3q4','Ricardo','+34662447153','','A','[]',NULL,'activo','2026-04-15','','{}'),
('mpoe9q87uhbh','Yolanda','655101705','','A','[]',NULL,'activo','2026-04-15','Pendiente la visita, pregunte por Ws','{}'),
('mpoe9q874nv1','Tatiana','+34656707621','','A','[]',NULL,'activo','2026-04-15','','{}'),
('mpoe9q87di71','David','+34619440228','','A','[]',NULL,'activo','2026-04-14','M1','{}'),
('mpoe9q87n9mr','Pedro','675826896','','A','[]',NULL,'activo','2026-04-12','','{}'),
('mpoe9q87gbzk','Particular','632719806','','A','[]',NULL,'activo','2026-04-13','Por las mañana aparece ocupado el telefono, posible agencia?','{}'),
('mpoe9q876qzx','Benjamin','650960108','','A','[]',NULL,'activo','2026-04-13','','{}'),
('mpoe9q87mdym','Particular','+34604851748','','A','[]',NULL,'activo','2026-04-13','','{}'),
('mpoe9q87qkaz','Gerardo','+34670099191','','A','[]',NULL,'activo','2026-04-13','Aparentemente hace fliping','{}'),
('mpoe9q87ohh0','Begoña','+34653198633','','A','[]',NULL,'activo','2026-04-13','M1','{}'),
('mpoe9q87at5b','Carolina','+34679609724','','A','[]',NULL,'activo','2026-04-13','M1','{}'),
('mpoe9q872fm2','Yesica','+34670839060','','A','[]',NULL,'activo','2026-04-13','','{}'),
('mpoe9q8732ke','Particular','668545849','','A','[]',NULL,'activo','2026-04-14','','{}'),
('mpoe9q87r40h','Laura','663970578','','A','[]',NULL,'activo','2026-04-10','','{}'),
('mpoe9q87t1er','Yolanda G.','+34695572700','','A','[]',NULL,'activo','2026-04-10','llamar, nunca hable con ella','{}'),
('mpoe9q87ccme','Luis marquez','+34600755192','','A','[]',NULL,'activo','2026-04-10','quedo pendiente enviarme info que nunca llego','{}'),
('mpoe9q87envi','Mavi','+34645549610','','A','[]',NULL,'activo','2026-04-10','Mensaje de seguimiento','{}'),
('mpoe9q87x1gi','Juan','+34687739181','','A','[]',NULL,'activo','2026-04-10','','{}'),
('mpoe9q87l2e8','Victoria','+34691626294','','A','[]',NULL,'activo','2026-04-10','','{}'),
('mpoe9q870f1v','Laura','+34609806344','','A','[]',NULL,'activo','2026-04-08','Mensaje preguntado si lo vendio','{}'),
('mpoe9q87udsh','Particular','+34677345796','','A','[]',NULL,'activo','2026-04-06','Volver a llamar','{}'),
('mpoe9q87ed0p','Gonzalo O Rosa','696210069','','A','[]',NULL,'activo','2026-04-06','A la espera de que hable con Gonzalo o el dueño','{}'),
('mpoe9q87djic','Particular San Marceli','','San Marceli','A','[]',NULL,'activo','2026-03-20','Esperar a hablar con SIC para ir a captarlo a dueño.','{}'),
('mpoe9q873iby','Jesus J Tocallo','+34662117228','Manises','A','[]',NULL,'activo','2026-05-27','15/02 lo quiere vender el mismo, no quiere pagar','{}'),
('mpoe9q87rwbw','María Lorena','+34695583669','Valencia','A','[]',NULL,'activo','2026-02-11','Nueva bajada de precio, ya son dos, nunca le pude hablar de ninguna de las dos','{}'),
('mpoe9q87j05t','Aranzazu','+34600269846','Benimaclet','A','[]',NULL,'activo','2026-02-11','Llamar, ya envie dos veces contenido','{}'),
('mpoe9q874ne0','Sandra Gómez','+34647605514','Patraix','A','[]',NULL,'activo','2026-02-11','','{}'),
('mpoe9q87krbt','Vicente','+34663156522','El Cabanyal','A','[]',NULL,'activo','2026-02-24','Mensaje Preguntar si vendio el piso','{}'),
('mpoe9q87yd2b','Joaquin','698732211','','A','[]',NULL,'activo','2026-05-27','Retirado','{}'),
('mpoe9q87k42t','Veronica','+34653038086','','A','[]',NULL,'activo','2026-03-11','Hablar sobre la bajada de precio','{}'),
('mpoe9q87wnmj','Olha','+3434660763996','Quart de Poblet','A','[]',NULL,'activo','2026-03-04','Llamar por M1, mas de un mes a la venta','{}'),
('mpoe9q87wcvm','Lidia Climent Pérez','+34634599462','La Petxina','A','[]',NULL,'activo','2026-02-22','Enviar otro mensaje proque no lo llame por el anterior enviado','{}'),
('mpoe9q87nojm','Maria Jose','606120170','','A','[]',NULL,'activo','2026-03-02','buscar anuncios','{}'),
('mpoe9q873wtz','Isabel','+34616823917','','A','[]',NULL,'activo','2026-03-02','Mensaje de Seguimiento','{}'),
('mpoe9q87arbm','Miguel','+34676928231','Valencia','A','[]',NULL,'activo','2026-03-04','Ver cual de los mensajes de seguimiento enviar','{}'),
('mpoe9q87h3j5','Edu','+3434690724141','','A','[]',NULL,'activo','2026-03-10','Analisis de mercado','{}'),
('mpoe9q87sixt','Victor','+34655427773','','A','[]',NULL,'activo','2026-03-12','Llamar','{}'),
('mpoe9q87quf6','Juan Luis','+34627174042','','A','[]',NULL,'activo','2026-03-12','Llamar, nunca respondio','{}'),
('mpoe9q87kvip','Jose Antonio','+3434665577102','','A','[]',NULL,'activo','2026-03-12','Llamar, sin respuesta','{}'),
('mpoe9q87rc1v','Maria','+34613722925','','A','[]',NULL,'activo','2026-03-12','Llamar, contestador','{}'),
('mpoe9q87vwp5','Miguel','+34625563366','','A','[]',NULL,'activo','2026-03-16','Mensaje: Vendiste el piso?','{}');

-- ── Seguimiento meta (estado inicial) ────────────────────────
INSERT IGNORE INTO seguimiento_meta (clave, valor) VALUES
('met',    '{"sent":9,"responded":0,"visits":0}'),
('ct',     '{"AN1":"{nombre}, sé que acabas de publicar. No te molesto con datos ahora — los datos importan cuando se necesitan.\\n\\nSolo una cosa que vale la pena saber desde el día uno: en {zona}, Idealista empuja los anuncios nuevos los primeros 12-15 días. Después caes en el feed. El movimiento real de las primeras dos semanas no predice nada.\\n\\nCuando llegues al día 20 sin oferta firme, escríbeme. No antes."}'),
('mr',     '{}'),
('sl',     '[]'),
('fd',     '{"C1":{"zona":"Arrancapins"},"AN1":{"zona":"Arrancapins"},"V1":{"zona":"Arrancapins"},"V2":{"zona":"Arrancapins"},"V3":{"zona":"Arrancapins"},"V4":{"zona":"Arrancapins"},"V5":{"zona":"Arrancapins"},"V6":{"zona":"Arrancapins"},"V7":{"zona":"Arrancapins"},"M1":{"zona":"Arrancapins"},"M2":{"zona":"Arrancapins"}}'),
('aid',    'null'),
('agenda', '[]');
