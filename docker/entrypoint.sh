#!/bin/sh
set -e

# Create storage directories if they don't exist
mkdir -p storage/content storage/temp storage/trash

# Set permissions
chown -R www-data:www-data storage/ config/

# If config.php does not exist, copy the sample config
if [ ! -f config/config.php ]; then
    if [ -f config/config.sample.php ]; then
        cp config/config.sample.php config/config.php
        echo "[entrypoint] config/config.php created from sample. Please configure it via environment variables or volume mount."
    fi
fi

# Handle environment variable overrides
if [ -n "${APP_URL:-}" ]; then
    sed -i "s|'url' => '/'|'url' => '${APP_URL}'|g" config/config.php 2>/dev/null || true
fi

if [ -n "${DB_DRIVER:-}" ] && [ "${DB_DRIVER}" = "mysql" ]; then
    echo "[entrypoint] Switching to MySQL configuration..."
    sed -i "s/'driver' => 'sqlite'/'driver' => 'mysql'/g" config/config.php 2>/dev/null || true

    if [ -n "${MYSQL_HOST:-}" ]; then
        sed -i "s/'host' => '127.0.0.1'/'host' => '${MYSQL_HOST}'/g" config/config.php 2>/dev/null || true
    fi
    if [ -n "${MYSQL_PORT:-}" ]; then
        sed -i "s/'port' => 3306/'port' => ${MYSQL_PORT}/g" config/config.php 2>/dev/null || true
    fi
    if [ -n "${MYSQL_DATABASE:-}" ]; then
        sed -i "s/'database' => 'fileroll'/'database' => '${MYSQL_DATABASE}'/g" config/config.php 2>/dev/null || true
    fi
    if [ -n "${MYSQL_USERNAME:-}" ]; then
        sed -i "s/'username' => 'root'/'username' => '${MYSQL_USERNAME}'/g" config/config.php 2>/dev/null || true
    fi
    if [ -n "${MYSQL_PASSWORD:-}" ]; then
        sed -i "s/'password' => ''/'password' => '${MYSQL_PASSWORD}'/g" config/config.php 2>/dev/null || true
    fi
fi

# Remove install directory for security
if [ -d install ] && [ -f install/.installed ]; then
    rm -rf install/
fi

exec "$@"
