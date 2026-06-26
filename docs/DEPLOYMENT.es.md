# Guía de despliegue de FileRoll

[English](../DEPLOYMENT.md) · [中文](./DEPLOYMENT.zh.md) · [日本語](./DEPLOYMENT.ja.md) · Español

Este documento contiene instrucciones completas de despliegue para FileRoll, cubriendo preparación del entorno, configuración del servidor web, uso del asistente de instalación y solución de problemas comunes.

## Requisitos

| Elemento | Requisito |
|---|---|
| PHP | >= 8.0 |
| Extensiones PHP | PDO, pdo_sqlite o pdo_mysql, json, mbstring, session, ctype, filter, fileinfo, gd |
| Servidor web | nginx o Apache (mod_rewrite) |
| Base de datos | SQLite (predeterminado) o MySQL 5.7+ / MariaDB 10.3+ |
| Composer | Para instalar las dependencias PHP |

## Despliegue rápido

### 1. Obtener el código

```bash
cd /home/wwwroot
git clone <url-del-repositorio> fileroll
# O subir mediante FTP/SFTP (asegúrate de subir el directorio completo)
```

### 2. Instalar dependencias PHP

```bash
cd /home/wwwroot/fileroll
composer install --no-dev
```

Si el servidor no tiene Composer, puedes ejecutar `composer install --no-dev` localmente y subir también el directorio `vendor/`.

### 3. Permisos de directorios

```bash
# Crear directorios de almacenamiento
mkdir -p storage/content storage/temp storage/trash

# Establecer permisos de escritura (PHP-FPM suele ejecutarse como www-data o www)
chmod -R 775 storage/ config/
chown -R www:www storage/ config/
```

### 4. Configurar el servidor web

#### nginx (Recomendado)

Elige la configuración que se adapte a tu entorno de servidor.

##### Plan A: nginx estándar — `root` apunta a `public/` (más seguro)

Escenarios aplicables: nginx compilado manualmente, instalación con apt/yum, paneles que permiten personalizar `root`.

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll/public;    # ← apuntar a public/
    index index.php;

    client_max_body_size 5G;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location /dav {
        try_files $uri /index.php?$query_string;
        limit_except GET HEAD POST PUT DELETE MKCOL COPY MOVE OPTIONS PROPFIND PROPPATCH LOCK UNLOCK REPORT {
            deny all;
        }
    }
}
```

Para la configuración completa, consulta [nginx.conf.example](../nginx.conf.example) (incluye SSL, caché, Gzip, cabeceras de seguridad).

> Si PHP usa `open_basedir`, asegúrate de que incluya la raíz del proyecto:
> ```
> open_basedir=/path/to/fileroll/public/:/path/to/fileroll/:/tmp/:/proc/
> ```

##### Plan B: Paquete LNMP One-Click — `root` apunta a la raíz del proyecto + reglas deny

Escenarios aplicables: paquete LNMP one-click, algunos paneles (`open_basedir` sigue automáticamente a `root` y no se puede modificar por separado).

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll;            # ← apuntar a la raíz del proyecto

    client_max_body_size 5G;

    # ── Bloquear directorios sensibles ──
    location ~ ^/(src|config|storage|vendor|templates|tests|scripts|lang|install) {
        deny all;
        return 404;
    }

    location ~ ^/(composer\.(json|lock|phar)|nginx\.conf.*\.example|phpunit\.xml|README\.md|\.gitignore|\.htaccess)$ {
        deny all;
        return 404;
    }

    location ~ /\. {
        deny all;
    }

    location ~ /\.well-known {
        allow all;
    }

    # ── Front controller ──
    location / {
        try_files $uri /public/index.php?$query_string;
    }

    # ── PHP ──
    location ~ ^/(public|install)/.+\.php$ {
        fastcgi_pass unix:/tmp/php-cgi.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi.conf;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    location ~ \.php$ {
        internal;
        fastcgi_pass unix:/tmp/php-cgi.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi.conf;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
    }

    # ── WebDAV ──
    location /dav {
        try_files $uri /public/index.php?$query_string;
        limit_except GET HEAD POST PUT DELETE MKCOL COPY MOVE OPTIONS PROPFIND PROPPATCH LOCK UNLOCK REPORT {
            deny all;
        }
    }

    # ── Assets (bajo public/, mapeados mediante alias) ──
    location /assets/ {
        alias /path/to/fileroll/public/assets/;
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

Para la configuración completa, consulta [nginx.conf.lnmp.example](../nginx.conf.lnmp.example) (incluye SSL, TLS, cabeceras de seguridad, bloqueo del directorio install).

> **⚠️ Nota LNMP: `open_basedir` sigue automáticamente a `root`**
>
> El paquete LNMP one-click lee la directiva `root` de nginx para establecer automáticamente `open_basedir`. Para permitir que PHP acceda a directorios superiores como `src/`, `config/`, etc., `root` **debe** apuntar a la raíz del proyecto (Plan B).
>
> Verificar `open_basedir` actual:
> ```bash
> grep -r 'open_basedir' /usr/local/nginx/conf/ --include='*.conf'
> grep -r 'open_basedir' /usr/local/php/etc/ --include='*.conf'
> ```
>
> Recargar PHP-FPM:
> ```bash
> /etc/init.d/php-fpm reload
> ```

#### Apache

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/fileroll/public

    <Directory /path/to/fileroll/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Apache requiere que `mod_rewrite` esté habilitado:
```bash
a2enmod rewrite
systemctl restart apache2
```

Para garantizar la seguridad de los datos y la reescritura correcta de rutas, confirma que los archivos `.htaccess` en la raíz del proyecto y en el directorio público (`/path/to/fileroll` y `/path/to/fileroll/public`) se han subido o copiado correctamente al servidor.

### 5. Acceder al asistente de instalación

Visita `https://yourdomain.com/` en tu navegador. El asistente de instalación te guiará a través de:

1. **Comprobación del entorno** — verificar la versión de PHP y las extensiones
2. **Configuración de la base de datos** — elegir SQLite (sin configuración) o MySQL
3. **Crear administrador** — establecer nombre de usuario, correo electrónico y contraseña
4. **Completar** — instalación exitosa, entrar en la página de inicio de sesión

El asistente de instalación generará la configuración en `config/config.php` e inicializará la base de datos.

> Si `config/config.php` no existe pero no se redirige a la interfaz de instalación, comprueba si la configuración del servidor web es correcta y si `open_basedir` restringe el acceso de PHP.

### 6. Después de la instalación

```bash
# Eliminar el directorio de instalación (recomendación de seguridad)
rm -rf install/
```

## Configuración manual

Si el asistente de instalación no está disponible, puedes crear el archivo de configuración manualmente:

```bash
cp config/config.sample.php config/config.php
```

Edita `config/config.php`, cambiando al menos `app.url` a tu dominio:

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',  // cambia a tu dominio
    'debug' => false,
],
```

## Gestión mediante CLI

El proyecto proporciona un script de gestión CLI:

```bash
php scripts/console.php migrate          # Ejecutar migraciones de base de datos
php scripts/console.php create-user      # Crear usuario
php scripts/console.php reset-password   # Restablecer contraseña
php scripts/console.php storage-stats    # Estadísticas de almacenamiento
php scripts/console.php cleanup-sessions # Limpiar sesiones expiradas
```

## Preguntas frecuentes

### Error 500

Causas más comunes:

1. **`vendor/` ausente** → ejecutar `composer install --no-dev`
2. **`config/config.php` ausente** → ejecutar el asistente de instalación o copiar desde `config.sample.php`
3. **Restricción de `open_basedir`** → modificar a la ruta de la raíz del proyecto
4. **Permisos del directorio de almacenamiento** → `chmod -R 775 storage/ && chown -R www:www storage/`

### 502 Bad Gateway

Fallo de conexión PHP-FPM. Causas comunes:

- El paquete LNMP one-click usa socket Unix (`unix:/tmp/php-cgi.sock`) en lugar de puerto TCP
- Comprobar si el parámetro `fastcgi_pass` coincide con la configuración de php-fpm
- Comprobar si PHP-FPM está en ejecución: `/etc/init.d/php-fpm status`

### Límite de tamaño de archivos subidos

Modifica `client_max_body_size` en la configuración de nginx, así como la configuración de PHP:

```ini
; /usr/local/php/etc/php.ini
upload_max_filesize = 5G
post_max_size = 5G
memory_limit = 256M
```

> `memory_limit` afecta la cantidad de memoria que puede usar una sola petición PHP. Las subidas fragmentadas por WebDAV utilizan streaming para minimizar el consumo de memoria, pero se recomienda al menos 256M para soportar archivos grandes.
