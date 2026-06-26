# FileRoll デプロイガイド

[English](../DEPLOYMENT.md) · [中文](./DEPLOYMENT.zh.md) · 日本語 · [Español](./DEPLOYMENT.es.md)

このドキュメントには、FileRoll の完全なデプロイ手順が記載されています。環境準備、Web サーバー設定、インストールウィザードの使用方法、および一般的なトラブルシューティングを含みます。

## 環境要件

| 項目 | 要件 |
|---|---|
| PHP | >= 8.0 |
| PHP 拡張機能 | PDO、pdo_sqlite または pdo_mysql、json、mbstring、session、ctype、filter、fileinfo、gd |
| Web サーバー | nginx または Apache (mod_rewrite) |
| データベース | SQLite（デフォルト）または MySQL 5.7+ / MariaDB 10.3+ |
| Composer | PHP 依存関係のインストール用 |

## クイックデプロイ

> **環境構築を省略したい場合** — Docker なら1コマンドでデプロイできます。詳細は [DOCKER.ja.md](./DOCKER.ja.md) を参照してください。

### 1. コードの取得

```bash
cd /home/wwwroot
git clone <リポジトリURL> fileroll
# または FTP/SFTP でアップロード（完全なディレクトリがアップロードされていることを確認）
```

### 2. PHP 依存関係のインストール

```bash
cd /home/wwwroot/fileroll
composer install --no-dev
```

サーバーに Composer がない場合は、ローカルで `composer install --no-dev` を実行し、`vendor/` ディレクトリごとアップロードしてください。

### 3. ディレクトリ権限

```bash
# ストレージディレクトリを作成
mkdir -p storage/content storage/temp storage/trash

# 書き込み権限を設定（PHP-FPM は通常 www-data または www として実行）
chmod -R 775 storage/ config/
chown -R www:www storage/ config/
```

### 4. Web サーバーの設定

#### nginx（推奨）

サーバー環境に適した設定を選択してください。

##### 方案 A：標準 nginx — `root` を `public/` に指定（最も安全）

適用シナリオ：手動でコンパイルした nginx、apt/yum によるインストール、`root` をカスタマイズできるパネル。

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll/public;    # ← public/ を指す
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

完全な設定は [nginx.conf.example](../nginx.conf.example) を参照（SSL、キャッシュ、Gzip、セキュリティヘッダーを含む）。

> PHP で `open_basedir` を使用している場合、プロジェクトルートが含まれていることを確認してください。
> ```
> open_basedir=/path/to/fileroll/public/:/path/to/fileroll/:/tmp/:/proc/
> ```

##### 方案 B：LNMP ワンクリックパッケージ — `root` をプロジェクトルートに指定 + deny ルール

適用シナリオ：LNMP ワンクリックパッケージ、`open_basedir` が `root` に自動的に追従し個別に変更できない一部のパネル。

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll;            # ← プロジェクトルートを指す

    client_max_body_size 5G;

    # ── 機密ディレクトリをブロック ──
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

    # ── フロントコントローラー ──
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

    # ── アセット（public/ 配下、alias でマッピング） ──
    location /assets/ {
        alias /path/to/fileroll/public/assets/;
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

完全な設定は [nginx.conf.lnmp.example](../nginx.conf.lnmp.example) を参照（SSL、TLS、セキュリティヘッダー、install ディレクトリのブロックを含む）。

> **⚠️ LNMP 注意：`open_basedir` は `root` に自動的に追従します**
>
> LNMP ワンクリックパッケージは nginx の `root` ディレクティブを読み取り、`open_basedir` を自動的に設定します。PHP が `src/`、`config/` などの上位ディレクトリにアクセスできるようにするには、`root` は**必ず**プロジェクトルートを指す必要があります（方案 B）。
>
> 現在の `open_basedir` を確認：
> ```bash
> grep -r 'open_basedir' /usr/local/nginx/conf/ --include='*.conf'
> grep -r 'open_basedir' /usr/local/php/etc/ --include='*.conf'
> ```
>
> PHP-FPM を再読み込み：
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

Apache では `mod_rewrite` を有効にする必要があります。
```bash
a2enmod rewrite
systemctl restart apache2
```

データセキュリティと正しいパス書き換えを確保するため、プロジェクトルートと公開ディレクトリ（`/path/to/fileroll` と `/path/to/fileroll/public`）の `.htaccess` がサーバーに正しくアップロードまたはコピーされていることを確認してください。

### 5. インストールウィザードにアクセス

ブラウザで `https://yourdomain.com/` にアクセスすると、インストールウィザードがガイドします。

1. **環境チェック** — PHP バージョンと拡張機能を確認
2. **データベース設定** — SQLite（設定不要）または MySQL を選択
3. **管理者の作成** — ユーザー名、メールアドレス、パスワードを設定
4. **完了** — インストール成功、ログインページへ

インストールウィザードは `config/config.php` に設定を生成し、データベースを初期化します。

> `config/config.php` が存在しないのにインストール画面にリダイレクトされない場合は、Web サーバーの設定が正しいか、および `open_basedir` が PHP のアクセスを制限していないか確認してください。

### 6. インストール後

```bash
# インストールディレクトリを削除（セキュリティ推奨）
rm -rf install/
```

## 手動設定

インストールウィザードが使用できない場合は、設定ファイルを手動で作成できます。

```bash
cp config/config.sample.php config/config.php
```

`config/config.php` を編集し、少なくとも `app.url` をあなたのドメインに変更してください。

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',  // あなたのドメインに変更
    'debug' => false,
],
```

## CLI 管理

プロジェクトは CLI 管理スクリプトを提供しています。

```bash
php scripts/console.php migrate          # データベースマイグレーションを実行
php scripts/console.php create-user      # ユーザーを作成
php scripts/console.php reset-password   # パスワードをリセット
php scripts/console.php storage-stats    # ストレージ統計
php scripts/console.php cleanup-sessions # 期限切れセッションをクリーンアップ
```

## よくある質問

### 500 エラー

最も一般的な原因：

1. **`vendor/` が存在しない** → `composer install --no-dev` を実行
2. **`config/config.php` が存在しない** → インストールウィザードを実行するか `config.sample.php` からコピー
3. **`open_basedir` の制限** → プロジェクトルートパスに変更
4. **ストレージディレクトリの権限** → `chmod -R 775 storage/ && chown -R www:www storage/`

### 502 Bad Gateway

PHP-FPM 接続に失敗しました。一般的な原因：

- LNMP ワンクリックパッケージは TCP ポートではなく Unix ソケット（`unix:/tmp/php-cgi.sock`）を使用
- `fastcgi_pass` パラメータが php-fpm の設定と一致しているか確認
- PHP-FPM が実行中か確認：`/etc/init.d/php-fpm status`

### アップロードファイルサイズ制限

nginx 設定の `client_max_body_size` および PHP 設定を変更してください。

```ini
; /usr/local/php/etc/php.ini
upload_max_filesize = 5G
post_max_size = 5G
memory_limit = 256M
```

> `memory_limit` は単一の PHP リクエストが使用できるメモリ量に影響します。WebDAV のチャンクアップロードはストリーミングを使用してメモリ消費を最小限に抑えていますが、大きなファイルのサポートには少なくとも 256M を推奨します。
