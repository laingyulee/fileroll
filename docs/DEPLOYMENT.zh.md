# FileRoll 部署指南

[English](../DEPLOYMENT.md) · 中文 · [日本語](./DEPLOYMENT.ja.md) · [Español](./DEPLOYMENT.es.md)

本文档包含 FileRoll 的完整部署说明，涵盖环境准备、Web 服务器配置、安装向导使用以及常见问题排查。

## 环境要求

| 项目 | 要求 |
|---|---|
| PHP | >= 8.0 |
| PHP 扩展 | PDO, pdo_sqlite 或 pdo_mysql, json, mbstring, session, ctype, filter, fileinfo, gd |
| Web 服务器 | nginx 或 Apache (mod_rewrite) |
| 数据库 | SQLite (默认) 或 MySQL 5.7+ / MariaDB 10.3+ |
| Composer | 用于安装 PHP 依赖 |

## 快速部署

> **想跳过环境配置？** 一条命令即可用 Docker 部署，详见 [DOCKER.zh.md](./DOCKER.zh.md)。

### 1. 获取代码

```bash
cd /home/wwwroot
git clone https://github.com/laingyulee/fileroll.git fileroll
# 或通过 FTP/SFTP 上传（确保上传完整目录）
```

### 2. 安装 PHP 依赖

```bash
cd /home/wwwroot/fileroll
composer install --no-dev
```

如果服务器没有 Composer，可以在本地执行 `composer install --no-dev` 后连 `vendor/` 目录一起上传。

### 3. 目录权限

```bash
# 创建存储目录
mkdir -p storage/content storage/temp storage/trash

# 设置写权限（PHP-FPM 运行用户通常是 www-data 或 www）
chmod -R 775 storage/ config/
chown -R www:www storage/ config/
```

### 4. 配置 Web 服务器

#### nginx（推荐）

选择适合你服务器环境的配置：

##### 方案 A：标准 nginx —— `root` 指向 `public/`（最安全）

适用场景：手动编译的 nginx、apt/yum 安装、可自定义 `root` 的面板。

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll/public;    # ← 指向 public/
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

完整配置参考 [nginx.conf.example](../nginx.conf.example)（含 SSL、缓存、Gzip、安全头）。

> 如果 PHP 使用了 `open_basedir`，需确保包含项目根目录：
> ```
> open_basedir=/path/to/fileroll/public/:/path/to/fileroll/:/tmp/:/proc/
> ```

##### 方案 B：LNMP 一键包 —— `root` 指向项目根目录 + deny 规则

适用场景：LNMP 一键包、部分面板（`open_basedir` 自动跟随 `root`，无法单独修改）。

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/fileroll;            # ← 指向项目根目录

    client_max_body_size 5G;

    # ── 屏蔽敏感目录 ──
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

    # ── Assets（在 public/ 下，通过 alias 映射） ──
    location /assets/ {
        alias /path/to/fileroll/public/assets/;
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

完整配置参考 [nginx.conf.lnmp.example](../nginx.conf.lnmp.example)（含 SSL、TLS、安全头、install 目录屏蔽）。

> **⚠️ LNMP 注意：`open_basedir` 自动跟随 `root`**
>
> LNMP 一键包会读取 nginx 的 `root` 指令自动设置 `open_basedir`。要使 PHP 能访问 `src/`、`config/` 等上级目录，`root` **必须**指向项目根目录（方案 B）。
>
> 验证当前 `open_basedir`：
> ```bash
> grep -r 'open_basedir' /usr/local/nginx/conf/ --include='*.conf'
> grep -r 'open_basedir' /usr/local/php/etc/ --include='*.conf'
> ```
>
> 重载 PHP-FPM：
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

Apache 需要启用 `mod_rewrite`：
```bash
a2enmod rewrite
systemctl restart apache2
```

为确保数据安全和程序正常转写路径，请确认项目根目录和公开目录（`/path/to/fileroll`和`/path/to/fileroll/public`）下的 `.htaccess` 被正确下载或复制到了服务端。

### 5. 访问安装向导

浏览器访问 `https://yourdomain.com/`，安装向导会自动引导完成：

1. **环境检查** — 验证 PHP 版本和扩展
2. **数据库配置** — 选择 SQLite（无需配置）或 MySQL
3. **创建管理员** — 设置用户名、邮箱、密码
4. **完成** — 安装成功，进入登录页

安装向导会在 `config/config.php` 生成配置并初始化数据库。

> 如果 `config/config.php` 不存在但未跳转到安装界面，请检查 Web 服务器配置是否正确，以及 `open_basedir` 是否限制了 PHP 访问。

### 6. 安装后

```bash
# 删除安装目录（安全建议）
rm -rf install/
```

## 手动配置

如果安装向导不可用，可以手动创建配置文件：

```bash
cp config/config.sample.php config/config.php
```

编辑 `config/config.php`，至少修改 `app.url` 为你的域名：

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',  // 改为你的域名
    'debug' => false,
],
```

## CLI 管理

项目提供 CLI 管理脚本：

```bash
php scripts/console.php migrate          # 运行数据库迁移
php scripts/console.php create-user      # 创建用户
php scripts/console.php reset-password   # 重置密码
php scripts/console.php storage-stats    # 存储统计
php scripts/console.php cleanup-sessions # 清理过期会话
```

## 常见问题

### 500 错误

最常见原因：

1. **`vendor/` 缺失** → 运行 `composer install --no-dev`
2. **`config/config.php` 缺失** → 运行安装向导或从 `config.sample.php` 复制
3. **`open_basedir` 限制** → 修改为项目根目录路径
4. **存储目录权限** → `chmod -R 775 storage/ && chown -R www:www storage/`

### 502 Bad Gateway

PHP-FPM 连接失败。常见原因：

- LNMP 一键包使用 Unix socket (`unix:/tmp/php-cgi.sock`)，而非 TCP 端口
- 检查 `fastcgi_pass` 参数是否与 php-fpm 配置匹配
- 检查 PHP-FPM 是否在运行：`/etc/init.d/php-fpm status`

### 上传文件大小限制

修改 nginx 配置 `client_max_body_size`，以及 PHP 配置：

```ini
; /usr/local/php/etc/php.ini
upload_max_filesize = 5G
post_max_size = 5G
memory_limit = 256M
```

> `memory_limit` 影响单个 PHP 请求可使用的内存大小。WebDAV 分块上传已使用流式处理来最小化内存消耗，但建议至少设置为 256M 以支持大文件上传。
