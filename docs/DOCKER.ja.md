# FileRoll Docker デプロイガイド

[English](./DOCKER.md) · [中文](./DOCKER.zh.md) · 日本語 · [Español](./DOCKER.es.md)

事前構築済みの Docker イメージ `ghcr.io/laingyulee/fileroll` を使用して FileRoll をデプロイします。

## クイックスタート

### Docker Compose を使用（推奨）

1. `docker-compose.yml` を作成：

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

2. サービスを起動：

```bash
docker compose up -d
```

3. `https://yourdomain.com` にアクセスし、インストールウィザードに従います。

### Docker Run を使用

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

## 設定

### 環境変数

| 変数 | 説明 | デフォルト |
|------|------|------------|
| `APP_URL` | アプリケーションの URL（正しい外部リンクの生成に使用） | `/` |
| `DB_DRIVER` | データベースドライバ（`sqlite` / `mysql`） | `sqlite` |
| `MYSQL_HOST` | MySQL ホストアドレス | `127.0.0.1` |
| `MYSQL_PORT` | MySQL ポート | `3306` |
| `MYSQL_DATABASE` | MySQL データベース名 | `fileroll` |
| `MYSQL_USERNAME` | MySQL ユーザー名 | `root` |
| `MYSQL_PASSWORD` | MySQL パスワード | （空） |

### データの永続化

| Volume | 説明 |
|--------|------|
| `/var/www/fileroll/storage` | ファイルストレージ、データベース（SQLite）、一時ファイル |
| `/var/www/fileroll/config` | 設定ファイル（`config.php`） |

初回起動時、`config/config.php` が存在しない場合、エントリポイントスクリプトが自動的に `config.sample.php` からコピーします。

### MySQL を使用する場合

`docker-compose.yml` を修正して MySQL サービスを有効にします：

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

サービスを起動：

```bash
docker compose up -d
```

## リバースプロキシと HTTPS

本番環境では、Docker の前にリバースプロキシを配置して SSL 終端を処理することを推奨します。コンテナのポートを非標準ポート（例：`8080`）にマッピングし、リバースプロキシで転送します：

```yaml
ports:
  - "127.0.0.1:8080:80"  # ローカルホストのみリッスン、リバースプロキシで転送
```

### Caddy の例

```Caddyfile
fileroll.yourdomain.com {
    reverse_proxy localhost:8080
}
```

Caddy は自動的に HTTPS 証明書を取得・更新します。

### Nginx の例

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

## CLI 管理

```bash
docker exec -it fileroll php scripts/console.php migrate
docker exec -it fileroll php scripts/console.php create-user
docker exec -it fileroll php scripts/console.php reset-password
docker exec -it fileroll php scripts/console.php storage-stats
docker exec -it fileroll php scripts/console.php cleanup-sessions
```

## アップデート

```bash
docker compose pull
docker compose up -d
```

## バックアップと復元

### バックアップ

```bash
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_config.tar.gz -C /data .
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_storage.tar.gz -C /data .
```

### 復元

```bash
# 設定を復元
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_config.tar.gz"

# ストレージを復元
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_storage.tar.gz"
```

## イメージ構成

イメージ `ghcr.io/laingyulee/fileroll` は `php:8.4-fpm-alpine` をベースにしており、以下が含まれます：

- **PHP-FPM 8.4** — PHP リクエストの処理
- **Nginx** — Web サーバー
- **Supervisor** — プロセスマネージャ、PHP-FPM と Nginx の同時実行を保証

GitHub Actions により自動ビルドされ、`linux/amd64` および `linux/arm64` アーキテクチャに対応しています。

## よくある質問

### 大きなファイルのアップロードに失敗する

コンテナ内では `memory_limit=256M`、`upload_max_filesize=5G`、`post_max_size=5G` が事前設定されています。リバースプロキシを使用する場合、プロキシ層でも大きなファイルのアップロードが許可されていることを確認してください（例：nginx の `client_max_body_size`）。

### 権限の問題

エントリポイントスクリプトが自動的に `storage/` と `config/` の権限を設定します。権限の問題が発生した場合：

```bash
docker exec -it fileroll chown -R www-data:www-data storage/ config/
```

### ログの確認

```bash
docker logs -f fileroll
```

### ソースからビルドする

イメージを自身でビルドする必要がある場合：

```bash
git clone https://github.com/laingyulee/fileroll.git
cd fileroll
docker build -t fileroll .
```

または `docker-compose.yml` で `image` を `build` に置き換えます：

```yaml
services:
  fileroll:
    build: .
    # image: ghcr.io/laingyulee/fileroll:latest
```
