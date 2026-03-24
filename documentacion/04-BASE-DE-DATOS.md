# TinoProp — Documentación de Base de Datos

**Versión:** 1.0.3
**Última actualización:** 24 de marzo de 2026
**Motor:** MySQL 8.0 / MariaDB 10.6 — InnoDB — utf8mb4_unicode_ci

---

## 1. Diagrama Relacional (resumen)

```
inmobiliarias (1) ←───── (N) usuarios
      │                        │
      ├── (N) clientes ────────┤ (usuario_id)
      ├── (N) prospectos ──────┤
      ├── (N) propiedades ─────┤
      │        │               │
      │        ├── (N) imagenes_propiedades
      │        ├── (N) visitas ←── clientes
      │        ├── (N) ofertas ←── clientes
      │        ├── (N) proceso_propiedades
      │        └── (N) post_venta ←── clientes
      │
      ├── (N) notas (polimórfica: cliente | propiedad)
      ├── (N) recordatorios
      ├── (N) actividad_log
      ├── (N) preferencias_usuario
      ├── (N) etiquetas
      ├── (N) entidad_etiquetas (polimórfica)
      ├── (N) filtros_guardados
      ├── (N) peticiones
      └── (N) scraped_propiedades
      
login_intentos (independiente — rate limiting)
```

---

## 2. Tablas Detalladas

### 2.1 `inmobiliarias` — Empresas/agencias (multi-tenant)

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `nombre` | VARCHAR(150) | NO | — | Nombre de la inmobiliaria |
| `cif` | VARCHAR(20) | SÍ | NULL | CIF/NIF de la empresa |
| `direccion` | VARCHAR(250) | SÍ | NULL | Dirección fiscal |
| `telefono` | VARCHAR(30) | SÍ | NULL | Teléfono de contacto |
| `email` | VARCHAR(150) | SÍ | NULL | Email corporativo |
| `activa` | TINYINT(1) | NO | 1 | 1=activa, 0=desactivada |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de alta |

**Índices:** PK(`id`)

---

### 2.2 `usuarios` — Usuarios del sistema

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id (NULL = superadmin) |
| `nombre` | VARCHAR(100) | NO | — | Nombre completo |
| `email` | VARCHAR(150) | NO | — | Email (login, UNIQUE) |
| `password` | VARCHAR(255) | NO | — | Hash bcrypt |
| `rol` | VARCHAR(30) | NO | 'agente' | Rol del usuario |
| `activo` | TINYINT(1) | NO | 1 | 1=activo, 0=desactivado |
| `password_temporal` | TINYINT(1) | NO | 0 | 1=debe cambiar password |
| `terminos_aceptados` | VARCHAR(20) | SÍ | NULL | Versión de términos aceptada |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de registro |

**Índices:** PK(`id`), UNIQUE(`email`), INDEX(`inmobiliaria_id`)  
**Roles válidos:** `agente_comprador`, `agente_vendedor`, `agente`, `marketing`, `supervisor`, `jefe`, `superadmin`

---

### 2.3 `clientes` — Clientes (compradores/vendedores)

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | NO | 1 | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | SÍ | NULL | FK → usuarios.id (agente asignado) |
| `tipo` | ENUM('vendedor','comprador') | NO | — | Tipo de cliente |
| `nombre` | VARCHAR(100) | NO | — | Nombre |
| `apellido` | VARCHAR(120) | NO | — | Apellido |
| `telefono` | VARCHAR(30) | NO | — | Teléfono |
| `email` | VARCHAR(150) | NO | — | Email |
| `operacion` | VARCHAR(50) | NO | — | Tipo de operación buscada |
| `direccion` | VARCHAR(200) | SÍ | NULL | Dirección |
| `genero` | VARCHAR(50) | SÍ | NULL | Género |
| `fecha_nacimiento` | DATE | SÍ | NULL | Fecha de nacimiento |
| `presupuesto` | DECIMAL(12,2) | SÍ | NULL | Presupuesto disponible |
| `zona_interesada` | VARCHAR(120) | SÍ | NULL | Zona de interés (para matching) |
| `comentarios` | TEXT | SÍ | NULL | Notas internas |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de alta |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última modificación |

**Índices:** PK(`id`), INDEX(`inmobiliaria_id`), INDEX(`tipo`)

---

### 2.4 `prospectos` — Pipeline comercial

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | NO | 1 | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | SÍ | NULL | FK → usuarios.id |
| `tipo` | ENUM('vendedor','comprador') | NO | — | Tipo de prospecto |
| `nombre` | VARCHAR(120) | NO | — | Nombre completo |
| `interes` | VARCHAR(200) | NO | — | Qué busca o qué ofrece |
| `estado` | VARCHAR(50) | NO | — | Estado del pipeline |
| `telefono` | VARCHAR(30) | NO | — | Teléfono |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de alta |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última modificación |

**Estados válidos:** `nuevo`, `contactado`, `no_contesta`, `realizado`, `descartado`

---

### 2.5 `propiedades` — Inventario inmobiliario

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | NO | 1 | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | SÍ | NULL | FK → usuarios.id |
| `equipo` | ENUM('vendedor','comprador') | NO | — | Lado del equipo |
| `titulo` | VARCHAR(150) | NO | — | Título descriptivo |
| `tipo` | VARCHAR(80) | NO | — | Tipo de inmueble |
| `ubicacion` | VARCHAR(120) | NO | — | Dirección/zona |
| `precio` | DECIMAL(12,2) | NO | 0.00 | Precio |
| `moneda` | VARCHAR(5) | NO | '€' | Moneda |
| `periodo` | VARCHAR(20) | SÍ | NULL | Periodo (mes, año) para alquileres |
| `operacion` | VARCHAR(50) | NO | — | venta / alquiler |
| `estado` | VARCHAR(50) | NO | 'Disponible' | Estado actual |
| `referencia` | VARCHAR(50) | SÍ | NULL | Código de referencia |
| `descripcion` | TEXT | SÍ | NULL | Descripción completa |
| `superficie` | DECIMAL(8,2) | SÍ | NULL | m² |
| `habitaciones` | INT | SÍ | NULL | Nº habitaciones |
| `banos` | INT | SÍ | NULL | Nº baños |
| `caracteristicas` | TEXT | SÍ | NULL | Características adicionales |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de alta |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última modificación |

**Índices:** PK(`id`), INDEX(`inmobiliaria_id`), INDEX(`equipo`), INDEX(`operacion`)  
**Estados válidos:** `Disponible`, `Reservado`, `Vendido`, `Alquilado`, `Retirado`

---

### 2.6 `notas` — Notas y avisos (polimórfica)

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | NO | 1 | FK → inmobiliarias.id |
| `entity_type` | ENUM('cliente','propiedad') | NO | — | Entidad vinculada |
| `entity_id` | INT UNSIGNED | NO | — | ID de la entidad |
| `tipo` | ENUM('Nota','Aviso') | NO | 'Nota' | Tipo de nota |
| `texto` | TEXT | NO | — | Contenido |
| `usuario_id` | INT UNSIGNED | SÍ | NULL | Autor de la nota |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |

**Índices:** PK(`id`), INDEX(`entity_type`, `entity_id`), INDEX(`inmobiliaria_id`)

---

### 2.7 `proceso_propiedades` — Kanban de etapas

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `propiedad_id` | INT UNSIGNED | NO | — | FK → propiedades.id |
| `usuario_id` | INT UNSIGNED | NO | 0 | FK → usuarios.id |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id |
| `equipo` | ENUM('vendedor','comprador') | NO | — | Lado del equipo |
| `etapa` | VARCHAR(50) | NO | 'captacion' | Etapa actual |
| `notas` | TEXT | SÍ | NULL | Notas del proceso |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última actualización |

**Etapas vendedor:** `captacion`, `valoracion`, `documentacion`, `publicacion`, `negociacion`, `cierre`  
**Etapas comprador:** `busqueda`, `preseleccion`, `visitas`, `negociacion`, `cierre`

---

### 2.8 `visitas` — Programación de visitas

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | NO | — | FK → usuarios.id |
| `propiedad_id` | INT UNSIGNED | NO | — | FK → propiedades.id |
| `cliente_id` | INT UNSIGNED | SÍ | NULL | FK → clientes.id |
| `equipo` | VARCHAR(20) | NO | — | vendedor / comprador |
| `fecha_visita` | DATE | NO | — | Fecha programada |
| `hora_visita` | TIME | SÍ | NULL | Hora programada |
| `estado` | VARCHAR(30) | NO | 'pendiente' | Estado de la visita |
| `observaciones` | TEXT | SÍ | NULL | Notas de la visita |
| `recordatorio_id` | INT UNSIGNED | SÍ | NULL | FK → recordatorios.id |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |

**Estados válidos:** `pendiente`, `realizada`, `cancelada`, `reprogramada`

---

### 2.9 `recordatorios` — Agenda del agente

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | NO | — | FK → usuarios.id |
| `tipo` | VARCHAR(50) | NO | — | Tipo de recordatorio |
| `descripcion` | TEXT | NO | — | Descripción |
| `fecha_recordatorio` | DATE | NO | — | Fecha objetivo |
| `hora_recordatorio` | TIME | SÍ | NULL | Hora (opcional) |
| `estado` | VARCHAR(20) | NO | 'pendiente' | Estado |
| `prospecto_id` | INT UNSIGNED | SÍ | NULL | FK → prospectos.id (opcional) |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |

---

### 2.10 `ofertas` — Gestión de ofertas

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | NO | — | FK → usuarios.id |
| `propiedad_id` | INT UNSIGNED | NO | — | FK → propiedades.id |
| `cliente_id` | INT UNSIGNED | SÍ | NULL | FK → clientes.id |
| `importe` | DECIMAL(12,2) | NO | — | Cantidad ofertada |
| `estado` | VARCHAR(30) | NO | 'pendiente' | Estado de la oferta |
| `notas` | TEXT | SÍ | NULL | Observaciones |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última actualización |

**Estados válidos:** `pendiente`, `aceptada`, `rechazada`, `contraoferta`

---

### 2.11 `imagenes_propiedades` — Galería fotográfica

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `propiedad_id` | INT UNSIGNED | NO | — | FK → propiedades.id |
| `ruta` | VARCHAR(300) | NO | — | Ruta del archivo en storage |
| `principal` | TINYINT(1) | NO | 0 | 1=imagen destacada |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de subida |

---

### 2.12 `post_venta` — Pipeline post-venta

| Campo | Tipo | Nulo | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | PK |
| `propiedad_id` | INT UNSIGNED | NO | — | FK → propiedades.id |
| `cliente_id` | INT UNSIGNED | SÍ | NULL | FK → clientes.id |
| `usuario_id` | INT UNSIGNED | NO | — | FK → usuarios.id |
| `inmobiliaria_id` | INT UNSIGNED | SÍ | NULL | FK → inmobiliarias.id |
| `etapa` | VARCHAR(50) | NO | 'pendiente_docs' | Etapa actual |
| `notas` | TEXT | SÍ | NULL | Observaciones |
| `fecha_venta` | DATE | SÍ | NULL | Fecha de cierre |
| `created_at` | TIMESTAMP | NO | CURRENT_TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE CURRENT_TIMESTAMP | Última actualización |

**Etapas:** `pendiente_docs`, `firma_contrato`, `tramites`, `entrega_llaves`, `completado`

---

### 2.13 Tablas auxiliares

#### `actividad_log` — Auditoría

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | FK → usuarios.id |
| `accion` | VARCHAR(100) | Descripción de la acción |
| `detalles` | TEXT | Información adicional |
| `ip` | VARCHAR(45) | IP del usuario |
| `created_at` | TIMESTAMP | Fecha/hora |

#### `etiquetas` — Tags personalizados

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `nombre` | VARCHAR(50) | Nombre del tag |
| `color` | VARCHAR(7) | Color hexadecimal |
| `created_at` | TIMESTAMP | Fecha de creación |

#### `entidad_etiquetas` — Relación N:M polimórfica

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `etiqueta_id` | INT UNSIGNED | FK → etiquetas.id |
| `entity_type` | VARCHAR(30) | cliente / propiedad |
| `entity_id` | INT UNSIGNED | ID de la entidad |

#### `preferencias_usuario` — Configuración UI

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `usuario_id` | INT UNSIGNED | FK → usuarios.id |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `clave` | VARCHAR(100) | Clave de preferencia |
| `valor` | TEXT | Valor almacenado |

#### `filtros_guardados` — Búsquedas guardadas

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `usuario_id` | INT UNSIGNED | FK → usuarios.id |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `nombre` | VARCHAR(100) | Nombre del filtro |
| `seccion` | VARCHAR(50) | En qué sección aplica |
| `filtros_json` | TEXT | JSON con los filtros |
| `created_at` | TIMESTAMP | Fecha de creación |

#### `peticiones` — Tickets internos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `usuario_id` | INT UNSIGNED | FK → usuarios.id |
| `tipo` | ENUM('error','mejora','consulta','urgente') | Tipo de ticket |
| `asunto` | VARCHAR(200) | Asunto |
| `descripcion` | TEXT | Descripción |
| `estado` | VARCHAR(30) | pendiente / en_proceso / resuelto |
| `respuesta` | TEXT | Respuesta del admin |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Última actualización |

#### `login_intentos` — Rate limiting

Registra **un evento por cada intento fallido** (log de eventos, no contador).
El sistema cuenta cuántos eventos existen en los últimos 15 minutos para determinar el bloqueo.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| `ip` | VARCHAR(45) | IP del intento |
| `email` | VARCHAR(255) | Email usado |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Fecha/hora del intento |

**Índices:** `idx_li_ip (ip)`, `idx_li_email (email)`, `idx_li_created (created_at)`

> Lógica: si hay ≥ 5 registros de la misma IP o email en los últimos 900 segundos → bloqueo durante 900 segundos adicionales.

#### `scraped_propiedades` — Datos importados de portales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | PK |
| `inmobiliaria_id` | INT UNSIGNED | FK → inmobiliarias.id |
| `titulo` | VARCHAR(255) | Título del anuncio |
| `precio` | VARCHAR(50) | Precio (texto) |
| `ubicacion` | VARCHAR(255) | Ubicación |
| `superficie` | VARCHAR(50) | m² |
| `habitaciones` | VARCHAR(20) | Nº habitaciones |
| `url` | TEXT | URL del portal |
| `fuente` | VARCHAR(50) | Portal de origen |
| `created_at` | TIMESTAMP | Fecha de importación |

---

## 3. Scripts SQL Disponibles

| Archivo | Propósito |
|---------|----------|
| `database/tinoprop_v031_completa.sql` | **Esquema completo** con datos de ejemplo (usar para instalación limpia) |
| `database/tinoprop.sql` | Esquema original base (sin multi-tenant) |
| `database/migracion_v029.sql` | Añade multi-tenant: tabla `inmobiliarias` + `inmobiliaria_id` en todas las tablas |
| `database/migracion_v030.sql` | Añade `terminos_aceptados` en `usuarios` |
| `database/migracion_v031_roles.sql` | Añade `usuario_id` en clientes, propiedades, prospectos |
| `sql/migracion_v031.sql` | Crea tabla `login_intentos` para anti brute-force |
