# TinoProp — Guía de Instalación y Despliegue

**Versión:** 1.0.0 — Producción  
**Última actualización:** 3 de marzo de 2026

---

## 1. Requisitos del Sistema

### Software necesario

| Componente | Versión mínima | Recomendado |
|-----------|---------------|-------------|
| **PHP** | 8.0 | 8.2+ |
| **MySQL / MariaDB** | 5.7 / 10.3 | 8.0 / 10.6+ |
| **Apache** | 2.4 | 2.4+ |
| **Python** (opcional — scraping) | 3.8 | 3.10+ |

### Extensiones PHP requeridas

```
pdo_mysql      ← Conexión a base de datos
mbstring       ← Manejo de cadenas multibyte (utf8mb4)
session        ← Gestión de sesiones
json           ← Codificación/decodificación JSON
fileinfo       ← Detección de tipo mime (subida de archivos)
gd             ← Procesamiento de imágenes (opcional)
```

### Módulos Apache requeridos

```
mod_rewrite    ← Reescritura de URLs
mod_headers    ← Cabeceras de seguridad HTTP
mod_authz_core ← Control de acceso a archivos
```

Para habilitarlos en Ubuntu/Debian:
```bash
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

---

## 2. Instalación Paso a Paso

### 2.1 Descargar los archivos

Copia el contenido de `V1.0.0-Produccion/` en el directorio raíz del servidor web:

```bash
# Ejemplo: en /var/www/html/tinoprop
sudo mkdir -p /var/www/html/tinoprop
sudo cp -r V1.0.0-Produccion/* /var/www/html/tinoprop/
sudo cp V1.0.0-Produccion/.htaccess /var/www/html/tinoprop/
```

### 2.2 Crear la base de datos

```bash
# Conectar a MySQL
mysql -u root -p

# Crear la base de datos
CREATE DATABASE tinoprop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# (Opcional) Crear un usuario dedicado
CREATE USER 'tinoprop_user'@'localhost' IDENTIFIED BY 'tu_password_segura';
GRANT ALL PRIVILEGES ON tinoprop.* TO 'tinoprop_user'@'localhost';
FLUSH PRIVILEGES;

# Importar el esquema completo
USE tinoprop;
SOURCE /var/www/html/tinoprop/database/tinoprop_v031_completa.sql;
```

O desde terminal:
```bash
mysql -u root -p tinoprop < /var/www/html/tinoprop/database/tinoprop_v031_completa.sql
```

### 2.3 Configurar el archivo .env

```bash
cd /var/www/html/tinoprop
cp .env.example .env
nano .env
```

Contenido a editar:
```dotenv
DB_HOST=localhost
DB_NAME=tinoprop
DB_USER=tinoprop_user
DB_PASS=tu_password_segura
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# (Opcional) IPs de proxies de confianza
TRUSTED_PROXIES=

# Ruta del log de errores
ERROR_LOG_PATH=logs/php_errors.log
```

### 2.4 Crear directorios necesarios

```bash
# Crear directorios de almacenamiento y logs
mkdir -p storage/uploads
mkdir -p storage/documentacion
mkdir -p logs

# Asignar permisos de escritura al servidor web
sudo chown -R www-data:www-data storage/ logs/
sudo chmod -R 755 storage/ logs/
```

### 2.5 Configurar Apache (VirtualHost)

Crear un archivo de configuración:
```bash
sudo nano /etc/apache2/sites-available/tinoprop.conf
```

Contenido:
```apache
<VirtualHost *:80>
    ServerName tinoprop.tudominio.com
    DocumentRoot /var/www/html/tinoprop

    <Directory /var/www/html/tinoprop>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/tinoprop_error.log
    CustomLog ${APACHE_LOG_DIR}/tinoprop_access.log combined
</VirtualHost>
```

Activar el sitio:
```bash
sudo a2ensite tinoprop.conf
sudo systemctl reload apache2
```

### 2.6 Verificar la instalación

1. Abre el navegador y accede a `http://tinoprop.tudominio.com`
2. Deberías ver la pantalla de login
3. Credenciales por defecto del SuperAdmin:
   - **Email:** `admin@tinoprop.com`
   - **Contraseña:** `Admin1234!`
4. Al primer acceso se te pedirá aceptar los términos de servicio

> ⚠️ **IMPORTANTE:** Cambia la contraseña del SuperAdmin inmediatamente después del primer acceso.

---

## 3. Configuración SSL (Producción)

Para entornos de producción es **obligatorio** usar HTTPS:

```bash
# Instalar Certbot (Let's Encrypt)
sudo apt install certbot python3-certbot-apache

# Obtener certificado
sudo certbot --apache -d tinoprop.tudominio.com

# Renovación automática (se configura sola, pero verifica)
sudo certbot renew --dry-run
```

---

## 4. Datos Iniciales (Seed)

El script `tinoprop_v031_completa.sql` incluye datos de ejemplo:

| Dato | Cantidad |
|------|----------|
| Inmobiliaria de ejemplo | 1 ("Mi Inmobiliaria") |
| Usuario SuperAdmin | 1 (`admin@tinoprop.com`) |
| Clientes de ejemplo | 4 |
| Prospectos de ejemplo | 8 |
| Propiedades de ejemplo | 8 |
| Notas de ejemplo | 6 |
| Procesos de ejemplo | 8 |
| Visitas de ejemplo | 6 |

> Puedes eliminar los datos de ejemplo una vez hayas configurado tu propia inmobiliaria y usuarios reales.

---

## 5. Primer Uso — Configuración Inicial

### Paso 1: Crear tu inmobiliaria

1. Inicia sesión como SuperAdmin
2. Ve a **Panel Admin** → **Inmobiliarias**
3. Crea una nueva inmobiliaria con los datos reales de tu empresa

### Paso 2: Crear usuarios

1. Ve a **Panel Admin** → **Usuarios**
2. Pulsa **+ Nuevo Usuario**
3. Selecciona la inmobiliaria, asigna un rol y rellena los datos
4. El sistema generará una **contraseña temporal** (anótala para dársela al usuario)
5. El usuario la cambiará en su primer acceso

### Paso 3: Configurar el equipo

Asigna los roles según la estructura de tu empresa:
- **Jefe** — responsable de la oficina (gestiona usuarios)
- **Supervisor** — coordina agentes (ve datos de todos)
- **Marketing** — enfocado en captación y publicidad
- **Agente** — trabaja tanto venta como compra
- **Agente Vendedor** — especializado en captación
- **Agente Comprador** — especializado en demanda

---

## 6. Actualización

Para actualizar a una nueva versión:

```bash
# 1. Hacer backup de la base de datos
mysqldump -u root -p tinoprop > backup_tinoprop_$(date +%Y%m%d).sql

# 2. Hacer backup de los archivos
cp -r /var/www/html/tinoprop /var/www/html/tinoprop_backup_$(date +%Y%m%d)

# 3. Copiar los nuevos archivos (NO sobrescribir .env ni storage/)
rsync -av --exclude='.env' --exclude='storage/' --exclude='logs/' V1.0.0-Nueva/* /var/www/html/tinoprop/

# 4. Ejecutar migraciones SQL si existen
mysql -u root -p tinoprop < nueva_migracion.sql

# 5. Verificar que todo funciona
```

---

## 7. Solución de Problemas

### Pantalla en blanco

```bash
# Revisar log de errores PHP
tail -f /var/www/html/tinoprop/logs/php_errors.log

# O el log de Apache
tail -f /var/log/apache2/tinoprop_error.log
```

### Error de conexión a base de datos

1. Verifica que los datos de `.env` son correctos
2. Comprueba que el servicio MySQL está activo: `sudo systemctl status mysql`
3. Verifica que el usuario tiene permisos: `mysql -u tinoprop_user -p -e "SHOW DATABASES;"`

### Error 403 Forbidden

```bash
# Verificar permisos
sudo chown -R www-data:www-data /var/www/html/tinoprop/
sudo chmod -R 755 /var/www/html/tinoprop/

# Verificar que AllowOverride está en All
sudo nano /etc/apache2/apache2.conf
```

### Archivos no se suben

```bash
# Verificar permisos de la carpeta storage
ls -la /var/www/html/tinoprop/storage/

# Verificar límites de PHP
php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"
```

Si necesitas aumentar los límites:
```bash
sudo nano /etc/php/8.2/apache2/php.ini
```
```ini
upload_max_filesize = 20M
post_max_size = 25M
max_file_uploads = 10
```
```bash
sudo systemctl restart apache2
```

### La sesión expira muy rápido

```ini
# En php.ini, aumentar el tiempo de vida de sesión
session.gc_maxlifetime = 7200   ; 2 horas
```

---

## 8. Backup Automatizado

Script de backup diario recomendado:

```bash
#!/bin/bash
# /usr/local/bin/backup_tinoprop.sh
FECHA=$(date +%Y%m%d_%H%M)
DIR_BACKUP="/var/backups/tinoprop"
mkdir -p $DIR_BACKUP

# Backup BD
mysqldump -u tinoprop_user -p'TU_PASSWORD' tinoprop | gzip > "$DIR_BACKUP/db_$FECHA.sql.gz"

# Backup archivos subidos
tar czf "$DIR_BACKUP/storage_$FECHA.tar.gz" /var/www/html/tinoprop/storage/

# Limpiar backups > 30 días
find $DIR_BACKUP -mtime +30 -delete

echo "Backup completado: $FECHA"
```

Configurar en cron:
```bash
sudo crontab -e
# Añadir: backup diario a las 3:00 AM
0 3 * * * /usr/local/bin/backup_tinoprop.sh >> /var/log/tinoprop_backup.log 2>&1
```
