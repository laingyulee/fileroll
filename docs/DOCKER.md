# FileRoll Docker Deployment Guide

English · [中文](DOCKER.zh.md) · [日本語](DOCKER.ja.md) · [Español](DOCKER.es.md)

Deploy FileRoll using the pre-built Docker image `ghcr.io/laingyulee/fileroll`.

## Quick Start

### Using Docker Compose (Recommended)

1. Create a `docker-compose.yml`:

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

2. Start the service:

```bash
docker compose up -d
```

3. Visit `https://yourdomain.com` and follow the installation wizard.

### Using Docker Run

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

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | Application URL (used to generate correct external links) | `/` |
| `DB_DRIVER` | Database driver (`sqlite` / `mysql`) | `sqlite` |
| `MYSQL_HOST` | MySQL host address | `127.0.0.1` |
| `MYSQL_PORT` | MySQL port | `3306` |
| `MYSQL_DATABASE` | MySQL database name | `fileroll` |
| `MYSQL_USERNAME` | MySQL username | `root` |
| `MYSQL_PASSWORD` | MySQL password | (empty) |

### Data Persistence

| Volume | Description |
|--------|-------------|
| `/var/www/fileroll/storage` | File storage, database (SQLite), temporary files |
| `/var/www/fileroll/config` | Configuration file (`config.php`) |

On first startup, if `config/config.php` does not exist, the entrypoint script will automatically copy it from `config.sample.php`.

### Using MySQL

Modify `docker-compose.yml` to enable the MySQL service:

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

Start the service:

```bash
docker compose up -d
```

## Reverse Proxy & HTTPS

In production, it is recommended to place a reverse proxy in front of Docker to handle SSL termination. Map the container port to a non-standard port (e.g. `8080`) and let the reverse proxy forward traffic:

```yaml
ports:
  - "127.0.0.1:8080:80"  # Listen on localhost only, forwarded by reverse proxy
```

### Caddy Example

```Caddyfile
fileroll.yourdomain.com {
    reverse_proxy localhost:8080
}
```

Caddy will automatically provision and renew HTTPS certificates.

### Nginx Example

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

## CLI Management

```bash
docker exec -it fileroll php scripts/console.php migrate
docker exec -it fileroll php scripts/console.php create-user
docker exec -it fileroll php scripts/console.php reset-password
docker exec -it fileroll php scripts/console.php storage-stats
docker exec -it fileroll php scripts/console.php cleanup-sessions
```

## Updating

```bash
docker compose pull
docker compose up -d
```

## Backup & Restore

### Backup

```bash
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_config.tar.gz -C /data .
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_storage.tar.gz -C /data .
```

### Restore

```bash
# Restore configuration
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_config.tar.gz"

# Restore storage
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_storage.tar.gz"
```

## Image Architecture

The image `ghcr.io/laingyulee/fileroll` is based on `php:8.4-fpm-alpine` and includes:

- **PHP-FPM 8.4** — Handles PHP requests
- **Nginx** — Web server
- **Supervisor** — Process manager, ensures PHP-FPM and Nginx run together

Built automatically via GitHub Actions, supporting `linux/amd64` and `linux/arm64` architectures.

## FAQ

### Large file upload fails

The container is pre-configured with `memory_limit=256M`, `upload_max_filesize=5G`, `post_max_size=5G`. If using a reverse proxy, make sure the proxy layer also allows large file uploads (e.g. nginx's `client_max_body_size`).

### Permission issues

The entrypoint script automatically sets permissions for `storage/` and `config/`. If you encounter permission issues:

```bash
docker exec -it fileroll chown -R www-data:www-data storage/ config/
```

### Viewing logs

```bash
docker logs -f fileroll
```

### Building from source

If you need to build the image yourself:

```bash
git clone https://github.com/laingyulee/fileroll.git
cd fileroll
docker build -t fileroll .
```

Or replace `image` with `build` in `docker-compose.yml`:

```yaml
services:
  fileroll:
    build: .
    # image: ghcr.io/laingyulee/fileroll:latest
```
