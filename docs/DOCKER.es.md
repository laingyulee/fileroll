# Guía de despliegue con Docker de FileRoll

Despliega FileRoll usando la imagen Docker preconstruida `ghcr.io/laingyulee/fileroll`.

## Inicio rápido

### Usando Docker Compose (Recomendado)

1. Crea un archivo `docker-compose.yml`:

```yaml
services:
  fileroll:
    image: ghcr.io/laingyulee/fileroll:latest
    ports:
      - "80:80"
    volumes:
      - fileroll_storage:/var/www/fileroll/storage
      - fileroll_config:/var/www/fileroll/config
    environment:
      - APP_URL=https://yourdomain.com
    restart: unless-stopped

volumes:
  fileroll_storage:
  fileroll_config:
```

2. Inicia el servicio:

```bash
docker compose up -d
```

3. Visita `https://yourdomain.com` y sigue el asistente de instalación.

### Usando Docker Run

```bash
docker run -d \
  --name fileroll \
  -p 80:80 \
  -v fileroll_storage:/var/www/fileroll/storage \
  -v fileroll_config:/var/www/fileroll/config \
  -e APP_URL=https://yourdomain.com \
  --restart unless-stopped \
  ghcr.io/laingyulee/fileroll:latest
```

## Configuración

### Variables de entorno

| Variable | Descripción | Valor predeterminado |
|----------|-------------|---------------------|
| `APP_URL` | URL de la aplicación (usada para generar enlaces externos correctos) | `/` |
| `DB_DRIVER` | Controlador de base de datos (`sqlite` / `mysql`) | `sqlite` |
| `MYSQL_HOST` | Dirección del host MySQL | `127.0.0.1` |
| `MYSQL_PORT` | Puerto MySQL | `3306` |
| `MYSQL_DATABASE` | Nombre de la base de datos MySQL | `fileroll` |
| `MYSQL_USERNAME` | Nombre de usuario MySQL | `root` |
| `MYSQL_PASSWORD` | Contraseña MySQL | (vacío) |

### Persistencia de datos

| Volume | Descripción |
|--------|-------------|
| `/var/www/fileroll/storage` | Almacenamiento de archivos, base de datos (SQLite), archivos temporales |
| `/var/www/fileroll/config` | Archivo de configuración (`config.php`) |

En el primer inicio, si `config/config.php` no existe, el script de entrada lo copiará automáticamente desde `config.sample.php`.

### Usar MySQL

Modifica `docker-compose.yml` para habilitar el servicio MySQL:

```yaml
services:
  fileroll:
    image: ghcr.io/laingyulee/fileroll:latest
    ports:
      - "80:80"
    volumes:
      - fileroll_storage:/var/www/fileroll/storage
      - fileroll_config:/var/www/fileroll/config
    environment:
      - APP_URL=https://yourdomain.com
      - DB_DRIVER=mysql
      - MYSQL_HOST=mysql
      - MYSQL_PORT=3306
      - MYSQL_DATABASE=fileroll
      - MYSQL_USERNAME=fileroll
      - MYSQL_PASSWORD=changeme
    restart: unless-stopped
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: fileroll
      MYSQL_USER: fileroll
      MYSQL_PASSWORD: changeme
    volumes:
      - mysql_data:/var/lib/mysql
    restart: unless-stopped

volumes:
  fileroll_storage:
  fileroll_config:
  mysql_data:
```

Inicia el servicio:

```bash
docker compose up -d
```

## Proxy inverso y HTTPS

En producción, se recomienda colocar un proxy inverso delante de Docker para manejar la terminación SSL. Mapea el puerto del contenedor a un puerto no estándar (ej. `8080`) y deja que el proxy inverso reenvíe el tráfico:

```yaml
ports:
  - "127.0.0.1:8080:80"  # Escuchar solo en localhost, reenviado por el proxy inverso
```

### Ejemplo con Caddy

```Caddyfile
fileroll.yourdomain.com {
    reverse_proxy localhost:8080
}
```

Caddy aprovisionará y renovará automáticamente los certificados HTTPS.

### Ejemplo con Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name fileroll.yourdomain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    client_max_body_size 5G;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
        proxy_send_timeout 300;
    }
}
```

## Gestión por CLI

```bash
docker exec -it fileroll php scripts/console.php migrate
docker exec -it fileroll php scripts/console.php create-user
docker exec -it fileroll php scripts/console.php reset-password
docker exec -it fileroll php scripts/console.php storage-stats
docker exec -it fileroll php scripts/console.php cleanup-sessions
```

## Actualización

```bash
docker compose pull
docker compose up -d
```

## Copia de seguridad y restauración

### Copia de seguridad

```bash
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_config.tar.gz -C /data .
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_storage.tar.gz -C /data .
```

### Restauración

```bash
# Restaurar configuración
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_config.tar.gz"

# Restaurar almacenamiento
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_storage.tar.gz"
```

## Arquitectura de la imagen

La imagen `ghcr.io/laingyulee/fileroll` está basada en `php:8.4-fpm-alpine` e incluye:

- **PHP-FPM 8.4** — Maneja las solicitudes PHP
- **Nginx** — Servidor web
- **Supervisor** — Gestor de procesos, asegura que PHP-FPM y Nginx se ejecuten juntos

Construida automáticamente mediante GitHub Actions, compatible con las arquitecturas `linux/amd64` y `linux/arm64`.

## Preguntas frecuentes

### Falla la subida de archivos grandes

El contenedor viene preconfigurado con `memory_limit=256M`, `upload_max_filesize=5G`, `post_max_size=5G`. Si usas un proxy inverso, asegúrate de que la capa del proxy también permita la subida de archivos grandes (ej. `client_max_body_size` de nginx).

### Problemas de permisos

El script de entrada establece automáticamente los permisos para `storage/` y `config/`. Si encuentras problemas de permisos:

```bash
docker exec -it fileroll chown -R www-data:www-data storage/ config/
```

### Ver registros

```bash
docker logs -f fileroll
```

### Construir desde el código fuente

Si necesitas construir la imagen tú mismo:

```bash
git clone https://github.com/laingyulee/fileroll.git
cd fileroll
docker build -t fileroll .
```

O reemplaza `image` con `build` en `docker-compose.yml`:

```yaml
services:
  fileroll:
    build: .
    # image: ghcr.io/laingyulee/fileroll:latest
```
