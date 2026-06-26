# FileRoll Docker 部署指南

使用预构建的 Docker 镜像 `ghcr.io/laingyulee/fileroll` 快速部署 FileRoll。

## 快速开始

### 使用 Docker Compose（推荐）

1. 创建 `docker-compose.yml`：

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

2. 启动服务：

```bash
docker compose up -d
```

3. 访问 `https://yourdomain.com`，按安装向导完成初始化。

### 使用 Docker Run

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

## 配置

### 环境变量

| 变量 | 说明 | 默认值 |
|------|------|--------|
| `APP_URL` | 应用访问地址（用于生成正确的外部链接） | `/` |
| `DB_DRIVER` | 数据库驱动 (`sqlite` / `mysql`) | `sqlite` |
| `MYSQL_HOST` | MySQL 主机地址 | `127.0.0.1` |
| `MYSQL_PORT` | MySQL 端口 | `3306` |
| `MYSQL_DATABASE` | MySQL 数据库名 | `fileroll` |
| `MYSQL_USERNAME` | MySQL 用户名 | `root` |
| `MYSQL_PASSWORD` | MySQL 密码 | （空） |

### 数据持久化

| Volume | 说明 |
|--------|------|
| `/var/www/fileroll/storage` | 文件存储、数据库（SQLite）、临时文件 |
| `/var/www/fileroll/config` | 配置文件（`config.php`） |

首次启动时，如果 `config/config.php` 不存在，入口脚本会自动从 `config.sample.php` 复制一份。

### 使用 MySQL

修改 `docker-compose.yml`，启用 MySQL 服务：

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

启动服务：

```bash
docker compose up -d
```

## 反向代理与 HTTPS

生产环境中，建议在 Docker 前面加一层反向代理来处理 SSL 终结。将容器端口映射到非标准端口（如 `8080`），由反向代理转发：

```yaml
ports:
  - "127.0.0.1:8080:80"  # 仅监听本地，由反向代理转发
```

### Caddy 示例

```Caddyfile
fileroll.yourdomain.com {
    reverse_proxy localhost:8080
}
```

Caddy 会自动申请和续期 HTTPS 证书。

### Nginx 示例

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

## 更新

```bash
docker compose pull
docker compose up -d
```

## 备份与恢复

### 备份

```bash
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_config.tar.gz -C /data .
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine tar czf /backup/fileroll_storage.tar.gz -C /data .
```

### 恢复

```bash
# 恢复配置
docker run --rm -v fileroll_config:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_config.tar.gz"

# 恢复存储
docker run --rm -v fileroll_storage:/data -v $(pwd):/backup alpine sh -c "cd /data && tar xzf /backup/fileroll_storage.tar.gz"
```

## 镜像架构

镜像 `ghcr.io/laingyulee/fileroll` 基于 `php:8.4-fpm-alpine`，包含：

- **PHP-FPM 8.4** — 处理 PHP 请求
- **Nginx** — Web 服务器
- **Supervisor** — 进程管理，确保 PHP-FPM 和 Nginx 同时运行

通过 GitHub Actions 自动构建，支持 `linux/amd64` 和 `linux/arm64` 架构。

## 常见问题

### 上传大文件失败

容器内已预配置 `memory_limit=256M`、`upload_max_filesize=5G`、`post_max_size=5G`。如果使用反向代理，确保代理层也允许大文件上传（如 nginx 的 `client_max_body_size`）。

### 权限问题

入口脚本会自动设置 `storage/` 和 `config/` 的权限。如遇权限问题：

```bash
docker exec -it fileroll chown -R www-data:www-data storage/ config/
```

### 查看日志

```bash
docker logs -f fileroll
```

### 从源码构建

如果需要自行构建镜像：

```bash
git clone https://github.com/laingyulee/fileroll.git
cd fileroll
docker build -t fileroll .
```

或在 `docker-compose.yml` 中替换 image 为 build：

```yaml
services:
  fileroll:
    build: .
    # image: ghcr.io/laingyulee/fileroll:latest
```
