# Guía de Instalación - Tinoprop

## 1. Requisitos Previos

### 1.1 Servidor
- **PHP:** 7.4 o superior
- **MySQL:** 5.7+ o MariaDB 10.3+
- **Extensiones PHP requeridas:**
  - PDO y pdo_mysql
  - mbstring
  - json
  - gd (para procesamiento de imágenes)
  - fileinfo (para validación de uploads)
- **Espacio en disco:** Mínimo 100 MB
- **Servidor web:** Apache 2.4+ o LiteSpeed

### 1.2 Hosting Recomendado
- Hostinger (Business o superior)
- Cualquier hosting compartido con PHP 7.4+ y MySQL

## 2. Instalación en Hostinger

### Paso 1: Crear Base de Datos
1. Acceder al panel de Hostinger → **Bases de datos** → **MySQL**
2. Crear nueva base de datos:
   - Nombre de la BD: `crm_inmobiliario` (o el que prefieras)
   - Usuario: `crm_user`
   - Contraseña: (generar una segura)
3. Anotar los datos de conexión:
   - Host: `localhost` (o el host proporcionado por Hostinger)
   - Puerto: `3306`

### Paso 2: Subir Archivos
**Opción A - File Manager:**
1. Ir a **File Manager** en el panel de Hostinger
2. Navegar a `public_html/` (o subdirectorio deseado)
3. Subir el archivo ZIP del CRM
4. Extraer el contenido

**Opción B - FTP:**
1. Configurar cliente FTP (FileZilla, etc.)
2. Conectar con las credenciales FTP de Hostinger
3. Subir todos los archivos a `public_html/`

### Paso 3: Configurar Base de Datos
1. Abrir el archivo `config/database.php`
2. Modificar las constantes de conexión:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_inmobiliario');
define('DB_USER', 'crm_user');
define('DB_PASS', 'tu_contraseña_segura');
```

### Paso 4: Ejecutar Instalador
1. Abrir en el navegador: `https://tudominio.com/install/setup.php`
2. El instalador creará automáticamente:
   - Todas las tablas necesarias
   - Datos iniciales (roles, configuración por defecto)
   - Usuario administrador por defecto
3. **Credenciales iniciales:**
   - Email: `admin@admin.com`
   - Contraseña: `admin123`
4. **¡IMPORTANTE!** Cambiar la contraseña inmediatamente después del primer login

### Paso 5: Configurar Permisos
Establecer los permisos correctos de archivos:
```
Directorios: 755
Archivos: 644
assets/uploads/: 775
```

En Hostinger, esto se puede hacer desde File Manager → Permisos, o por FTP.

### Paso 6: Eliminar Instalador
Por seguridad, después de la instalación exitosa:
1. Eliminar o renombrar la carpeta `install/`
2. O protegerla con `.htaccess`

## 3. Configuración Post-Instalación

### 3.1 Datos de la Empresa
1. Ir a **Ajustes** → **General**
2. Configurar nombre, dirección, teléfono, email de la empresa
3. Subir logo de la empresa

### 3.2 Marca Blanca (Whitelabel)
1. Ir a **Ajustes** → **Marca Blanca**
2. Personalizar:
   - Nombre de la aplicación
   - Logo y favicon
   - Colores primario, secundario y acento
   - Textos de la página de login
   - CSS personalizado adicional

### 3.3 Usuarios y Roles
1. Ir a **Ajustes** → **Roles**: Crear roles con permisos específicos
2. Ir a **Ajustes** → **Usuarios**: Crear usuarios y asignar roles
3. Desactivar o cambiar contraseña del usuario admin por defecto

### 3.4 Pipeline de Ventas
1. Ir a **Pipeline**
2. Configurar las etapas del embudo de ventas según tu proceso comercial

### 3.5 Plantillas de Email
1. Ir a **Ajustes** → **Plantillas Email**
2. Personalizar las plantillas para notificaciones automáticas

## 4. Actualización

### 4.1 Proceso de Actualización
1. **Hacer backup** de la base de datos y archivos
2. Subir los archivos nuevos sobrescribiendo los existentes
3. **NO sobrescribir** `config/database.php` (tiene tu configuración)
4. Ejecutar scripts de migración si los hay en `install/`

### 4.2 Backup Antes de Actualizar
- Usar el módulo de backup: **Ajustes** → **Backup**
- O exportar manualmente desde phpMyAdmin en Hostinger

## 5. Solución de Problemas

### Error: "Connection refused" o "Access denied"
- Verificar credenciales en `config/database.php`
- Confirmar que el usuario MySQL tiene permisos sobre la base de datos
- En Hostinger, verificar que el host sea correcto (puede no ser `localhost`)

### Error: "Class PDO not found"
- La extensión PDO no está habilitada
- En Hostinger: Panel → PHP Configuration → Habilitar PDO y pdo_mysql

### Página en blanco
- Activar errores PHP temporalmente:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Verificar logs de error en Hostinger → Logs

### Uploads no funcionan
- Verificar permisos de `assets/uploads/` (775)
- Verificar `upload_max_filesize` y `post_max_size` en PHP config
- En Hostinger: PHP Configuration → Aumentar límites

### Modo oscuro no se guarda
- Verificar que JavaScript esté habilitado en el navegador
- Limpiar caché del navegador
- Verificar que `localStorage` esté disponible (no en modo incógnito restrictivo)

## 6. Configuración Avanzada

### 6.1 HTTPS/SSL
- En Hostinger, activar SSL gratuito desde el panel
- El CRM funcionará automáticamente con HTTPS
- Recomendado: forzar HTTPS añadiendo a `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 6.2 Dominio Personalizado
- Configurar el dominio en Hostinger
- Opcionalmente configurar en Ajustes → Whitelabel → Dominio custom

### 6.3 Cron Jobs (Automatizaciones)
- Para automatizaciones basadas en tiempo, configurar cron en Hostinger:
  ```
  */5 * * * * php /home/user/public_html/cron.php
  ```
- Esto ejecuta las automatizaciones pendientes cada 5 minutos
