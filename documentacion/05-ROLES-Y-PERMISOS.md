# TinoProp — Guía de Roles, Permisos y Seguridad

**Versión:** 1.0.3
**Última actualización:** 24 de marzo de 2026

---

## 1. Jerarquía de Roles

TinoProp implementa un sistema de roles jerárquico con **7 niveles**. Cada nivel hereda los permisos de los niveles inferiores.

```
                    ┌─────────────────┐
                    │  Super Admin    │  nivel 99
                    │  (plataforma)   │
                    └────────┬────────┘
                             │
                    ┌────────┴────────┐
                    │      Jefe       │  nivel 5
                    │   (oficina)     │
                    └────────┬────────┘
                             │
                    ┌────────┴────────┐
                    │   Supervisor    │  nivel 4
                    │  (coordinación) │
                    └────────┬────────┘
                             │
                    ┌────────┴────────┐
                    │    Marketing    │  nivel 3
                    │  (captación)    │
                    └────────┬────────┘
                             │
                    ┌────────┴────────┐
                    │     Agente      │  nivel 2
                    │ (venta+compra)  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
     ┌────────┴────────┐           ┌────────┴────────┐
     │ Agente Vendedor │           │ Agente Comprador│  nivel 1
     │ (solo captación)│           │ (solo demanda)  │
     └─────────────────┘           └─────────────────┘
```

---

## 2. Descripción de Cada Rol

### Super Admin (nivel 99)
- **Quién:** Administrador de la plataforma (propietario del sistema)
- **Ámbito:** Acceso a TODAS las inmobiliarias
- **Funciones:**
  - Panel de administración global
  - Crear, editar, activar/desactivar y eliminar inmobiliarias
  - Gestionar usuarios de cualquier inmobiliaria
  - Responder peticiones/tickets de los jefes
  - Configuración global del sistema
  - Ver estadísticas de toda la plataforma
- **NO accede a:** operaciones de negocio (clientes, propiedades, etc.)

### Jefe (nivel 5)
- **Quién:** Responsable/gerente de una oficina inmobiliaria
- **Ámbito:** Toda la información de SU inmobiliaria
- **Funciones:**
  - Todo lo del Supervisor +
  - Gestionar usuarios de su inmobiliaria (crear, cambiar roles, activar/desactivar)
  - Enviar peticiones al SuperAdmin (tickets de error, mejora, consulta, urgente)
  - Ver y gestionar TODOS los datos de su inmobiliaria (sin filtro de usuario)

### Supervisor (nivel 4)
- **Quién:** Coordinador de equipo
- **Ámbito:** Toda la información de SU inmobiliaria
- **Funciones:**
  - Todo lo del Marketing +
  - Importar datos CSV en masa
  - Ver historial de actividad (log de auditoría)
  - Eliminar registros de post-venta
  - Panel de rendimiento por agente en el dashboard
  - Ver TODOS los datos de su inmobiliaria (sin filtro de usuario)

### Marketing (nivel 3)
- **Quién:** Responsable de captación y publicidad
- **Ámbito:** Toda la información de SU inmobiliaria
- **Funciones:**
  - Todo lo del Agente +
  - Ve ambas áreas (venta Y compra)
  - Acceso a TODOS los datos de la inmobiliaria (sin filtro por usuario)

### Agente (nivel 2)
- **Quién:** Agente completo que trabaja en ambas áreas
- **Ámbito:** Solo SUS propios datos
- **Funciones:**
  - Todo lo del Ag. Vendedor + Ag. Comprador
  - Trabaja en venta Y compra
  - Solo ve los clientes, propiedades, notas que él mismo ha creado

### Agente Vendedor (nivel 1)
- **Quién:** Especialista en captación de propiedades
- **Ámbito:** Solo SUS datos, solo área de VENTA
- **Funciones:**
  - Gestionar clientes vendedores
  - Gestionar prospectos vendedores
  - Gestionar propiedades (lado vendedor)
  - Programar visitas de vendedor
  - Gestionar ofertas
  - Proceso de venta (kanban)
  - Post-venta
  - Búsqueda avanzada, matching, recordatorios, documentación

### Agente Comprador (nivel 1)
- **Quién:** Especialista en búsqueda de compradores/inquilinos
- **Ámbito:** Solo SUS datos, solo área de COMPRA
- **Funciones:**
  - Gestionar clientes compradores
  - Gestionar prospectos compradores
  - Gestionar propiedades (lado comprador)
  - Programar visitas de comprador
  - Proceso de compra (kanban)
  - Post-venta
  - Búsqueda avanzada, matching, recordatorios, documentación

---

## 3. Matriz de Acceso por Sección

| Sección | Ag.Comp | Ag.Vend | Agente | Marketing | Supervisor | Jefe | SuperAdmin |
|---------|:-------:|:-------:|:------:|:---------:|:----------:|:----:|:----------:|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Recordatorios | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Matching | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Documentación | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Búsqueda Avanzada | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Post-Venta | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Clientes Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Prospectos Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Propiedades Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Alquileres Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Proceso Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Visitas Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Ofertas Vendedor** | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Clientes Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Prospectos Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Propiedades Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Alquileres Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Proceso Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Visitas Comprador** | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Importar CSV | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Historial Actividad | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Admin Usuarios | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Peticiones | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Configuración | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Legal | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Panel Admin | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Admin Inmobiliarias | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 4. Filtros de Visibilidad de Datos

### Por inmobiliaria (`sql_iid`)

Cada consulta de datos incluye:
```sql
AND inmobiliaria_id = :iid
```
Excepto SuperAdmin que ve todo (filtro vacío).

### Por usuario (`sql_uid`)

Los roles de nivel < 3 (agentes) tienen filtro adicional:
```sql
AND usuario_id = :uid
```
Los roles de nivel ≥ 3 (marketing, supervisor, jefe) ven todos los datos de su inmobiliaria.

### Resumen de visibilidad

| Rol | Ve datos de... |
|-----|---------------|
| Agente Comprador/Vendedor | Solo **sus propios** registros |
| Agente | Solo **sus propios** registros (ambas áreas) |
| Marketing | **Todos** los de su inmobiliaria |
| Supervisor | **Todos** los de su inmobiliaria |
| Jefe | **Todos** los de su inmobiliaria |
| SuperAdmin | **Todos** de **todas** las inmobiliarias |

---

## 5. Funciones de Seguridad (helpers.php)

| Función | Parámetro | Devuelve | Uso |
|---------|-----------|----------|-----|
| `roles_disponibles()` | — | array | Mapa de roles con label y nivel |
| `usuario_rol()` | — | string | Rol del usuario actual |
| `es_superadmin()` | — | bool | ¿Es superadmin? |
| `tiene_nivel($rol)` | string $rol_minimo | bool | ¿Tiene al menos este nivel? |
| `puede_ver_vendedor()` | — | bool | ¿Puede acceder a secciones vendedor? |
| `puede_ver_comprador()` | — | bool | ¿Puede acceder a secciones comprador? |
| `puede_ver_sistema()` | — | bool | ¿Puede ver importar CSV + historial? |
| `puede_gestionar_usuarios()` | — | bool | ¿Puede crear/editar usuarios? |
| `puede_acceder_seccion($s)` | string $seccion | bool | ¿Tiene acceso a esta sección? |
| `verificar_acceso($s)` | string $seccion | void | Redirige si no tiene acceso |

---

## 6. Protecciones de Seguridad

### Autenticación

| Mecanismo | Detalle |
|-----------|---------|
| **Hashing** | bcrypt via `password_hash()` y `password_verify()` |
| **Sesiones** | `session_regenerate_id(true)` al loguearse |
| **Cookies** | `httponly`, `SameSite=Strict`, `Secure` (si HTTPS) |
| **Rate limiting** | Max 5 intentos / 15 min, bloqueo 15 minutos |
| **Password temporal** | Flujo forzado de cambio en primer acceso |
| **Términos** | Aceptación obligatoria antes de usar el sistema |

### Protección contra ataques

| Ataque | Protección |
|--------|-----------|
| **SQL Injection** | PDO con sentencias preparadas (parámetros enlazados) |
| **XSS** | Función `e()` — `htmlspecialchars()` en toda salida |
| **CSRF** | Token por sesión: `csrf_token()`, `csrf_field()`, `csrf_verify()` |
| **Clickjacking** | `X-Frame-Options: DENY` |
| **MIME Sniffing** | `X-Content-Type-Options: nosniff` |
| **Enumeración de archivos** | `Options -Indexes` y `.htaccess` bloquea `.env`, `.sql`, `.log`, etc. |
| **Traversal de directorio** | Validación de rutas en subida de archivos |

### Validación de datos

| Función | Validación |
|---------|-----------|
| `validar_requerido($v)` | Campo no vacío |
| `validar_email($v)` | Formato email válido |
| `validar_password_segura($v)` | ≥ 8 chars, 1 mayúscula, 1 símbolo |
| `validar_telefono($v)` | Formato telefónico |
| `validar_enum($v, $lista)` | Valor incluido en lista permitida |

---

## 7. Gestión de Usuarios (para Jefes)

### Crear un usuario

1. Ve a **Usuarios** en el menú Sistema
2. Pulsa **+ Nuevo Usuario**
3. Rellena: nombre, email, rol
4. El sistema genera una **contraseña temporal** que debes entregar al usuario
5. El usuario la cambiará en su primer acceso

### Cambiar rol de un usuario

1. En la lista de usuarios, busca al usuario
2. Selecciona el nuevo rol en el desplegable
3. Confirma el cambio

> Un jefe solo puede asignar roles de nivel inferior al suyo (no puede crear otro jefe).

### Desactivar un usuario

1. En la lista de usuarios, pulsa el botón de activar/desactivar
2. Un usuario desactivado no puede iniciar sesión
3. Sus datos se conservan pero no se muestran en los listados

---

## 8. Registro de Actividad (Auditoría)

Todas las acciones significativas quedan registradas en `actividad_log`:

- Inicio de sesión
- Creación/edición/eliminación de registros
- Cambios de rol o estado de usuarios
- Importaciones CSV
- Aceptación de términos
- Cambios de contraseña

**Visibilidad:** Solo Supervisores (nivel 4) y Jefes (nivel 5).  
El SuperAdmin no tiene acceso directo al historial (administra desde su panel).
