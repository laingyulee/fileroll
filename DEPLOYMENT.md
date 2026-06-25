# FileRoll Deployment Guide

English · [中文](docs/DEPLOYMENT.zh.md) · [日本語](docs/DEPLOYMENT.ja.md) · [Español](docs/DEPLOYMENT.es.md)

This document contains complete deployment instructions for FileRoll, covering environment preparation, web server configuration, installation wizard usage, and common troubleshooting.

## Requirements

| Item | Requirement |
|---|---|
| PHP | >= 8.0 |
| PHP Extensions | PDO, pdo_sqlite or pdo_mysql, json, mbstring, session, ctype, filter, fileinfo, gd |
| Web Server | nginx or Apache (mod_rewrite) |
| Database | SQLite (default) or MySQL 5.7+ / MariaDB 10.3+ |
| Composer | For installing PHP dependencies |

## Quick Deploy

### 1. Get the Code

```bash
cd /home/wwwroot
git clone <repo-url> fileroll
# Or upload via FTP/SFTP (ensure the full directory is uploaded)
```

### 2. Install PHP Dependencies

```bash
cd /home/wwwroot/fileroll
composer install --no-dev
```

If your server does not have Composer, you can run `composer install --no-dev` locally and upload the `vendor/` directory together.

### 3. Directory Permissions

```bash
# Create storage directories
mkdir -p storage/content storage/temp storage/trash

# Set write permissions (PHP-FPM usually runs as www-data or www)
chmod -R 775 storage/ config/
chown -R www:www storage/ config/
```

### 4. Configure Web Server

#### nginx (Recommended)

Choose the configuration that suits your server environment:

##### Plan A: Standard nginx — `root` points to `public/` (Most Secure)

Applicable scenarios: manually compiled nginx, apt/yum installations, panels that allow custom `root`.

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll/public;    # ← point to public/
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

For the complete configuration, refer to [nginx.conf.example](nginx.conf.example) (includes SSL, caching, Gzip, security headers).

> If PHP uses `open_basedir`, make sure it includes the project root:
> ```
> open_basedir=/path/to/fileroll/public/:/path/to/fileroll/:/tmp/:/proc/
> ```

##### Plan B: LNMP One-Click Package — `root` points to project root + deny rules

Applicable scenarios: LNMP one-click package, some panels (`open_basedir` automatically follows `root` and cannot be modified separately).

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll;            # ← point to project root

    client_max_body_size 5G;

    # ── Block sensitive directories ──
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

    # ── Assets (under public/, mapped via alias) ──
    location /assets/ {
        alias /path/to/fileroll/public/assets/;
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

For the complete configuration, refer to [nginx.conf.lnmp.example](nginx.conf.lnmp.example) (includes SSL, TLS, security headers, install directory blocking).

> **⚠️ LNMP Note: `open_basedir` automatically follows `root`**
>
> The LNMP one-click package reads nginx's `root` directive to automatically set `open_basedir`. To allow PHP to access parent directories such as `src/`, `config/`, etc., `root` **must** point to the project root (Plan B).
>
> Verify current `open_basedir`:
> ```bash
> grep -r 'open_basedir' /usr/local/nginx/conf/ --include='*.conf'
> grep -r 'open_basedir' /usr/local/php/etc/ --include='*.conf'
> ```
>
> Reload PHP-FPM:
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

Apache requires `mod_rewrite` to be enabled:
```bash
a2enmod rewrite
systemctl restart apache2
```

To ensure data security and correct path rewriting, please confirm that the `.htaccess` files in the project root and public directory (`/path/to/fileroll` and `/path/to/fileroll/public`) are correctly uploaded or copied to the server.

### 5. Access the Installation Wizard

Visit `https://yourdomain.com/` in your browser. The installation wizard will guide you through:

1. **Environment Check** — verify PHP version and extensions
2. **Database Configuration** — choose SQLite (no configuration needed) or MySQL
3. **Create Administrator** — set username, email, and password
4. **Complete** — installation successful, enter the login page

The installation wizard will generate the configuration in `config/config.php` and initialize the database.

> If `config/config.php` does not exist but you are not redirected to the installation interface, please check whether the web server configuration is correct and whether `open_basedir` restricts PHP access.

### 6. After Installation

```bash
# Delete the install directory (security recommendation)
rm -rf install/
```

## Manual Configuration

If the installation wizard is unavailable, you can create the configuration file manually:

```bash
cp config/config.sample.php config/config.php
```

Edit `config/config.php`, at minimum changing `app.url` to your domain:

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',  // change to your domain
    'debug' => false,
],
```

## CLI Management

The project provides a CLI management script:

```bash
php scripts/console.php migrate          # Run database migrations
php scripts/console.php create-user      # Create user
php scripts/console.php reset-password   # Reset password
php scripts/console.php storage-stats    # Storage statistics
php scripts/console.php cleanup-sessions # Clean up expired sessions
```

## FAQ

### 500 Error

Most common causes:

1. **Missing `vendor/`** → run `composer install --no-dev`
2. **Missing `config/config.php`** → run the installation wizard or copy from `config.sample.php`
3. **`open_basedir` restriction** → modify to the project root path
4. **Storage directory permissions** → `chmod -R 775 storage/ && chown -R www:www storage/`

### 502 Bad Gateway

PHP-FPM connection failed. Common causes:

- LNMP one-click package uses Unix socket (`unix:/tmp/php-cgi.sock`) instead of TCP port
- Check whether the `fastcgi_pass` parameter matches the php-fpm configuration
- Check whether PHP-FPM is running: `/etc/init.d/php-fpm status`

### Upload File Size Limit

Modify `client_max_body_size` in nginx configuration, as well as PHP configuration:

```ini
; /usr/local/php/etc/php.ini
upload_max_filesize = 5G
post_max_size = 5G
```
