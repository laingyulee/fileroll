# FileRoll

[English](../README.md) · 中文 · [日本語](./README.ja.md) · [Español](./README.es.md)

FileRoll 是一个支持 WebDAV 的个人云存储应用，提供文件管理、版本控制、分享、多用户管理等功能。采用 PHP 8+ 与 SQLite/MySQL 构建，可通过浏览器或任意 WebDAV 客户端访问。

## 功能特性

- **文件与文件夹管理**：拖拽上传、移动、复制、重命名、回收站、星标
- **版本控制**：文件历史版本保存与一键恢复
- **分享功能**：生成公开/密码保护/限时分享链接
- **多用户与权限**：管理员可管理用户、配额与角色
- **WebDAV 支持**：兼容 Windows 资源管理器、macOS Finder、Cyberduck、RaiDrive 等客户端
- **安全加固**：bcrypt 密码哈希、CSRF 保护、路径遍历防护、XSS 过滤、速率限制
- **国际化**：支持 8 种语言界面
- **CLI 管理**：内置迁移、创建用户、密码重置、清理任务等命令

## 技术栈

- PHP 8.0+
- SQLite / MySQL / MariaDB
- Composer 依赖管理
- PSR-4 自动加载，MVC 架构
- nginx / Apache 部署

## 快速开始

### 环境要求

| 项目 | 要求 |
|---|---|
| PHP | >= 8.0 |
| 扩展 | PDO, pdo_sqlite/pdo_mysql, json, mbstring, session, ctype, filter, fileinfo, gd |
| Web 服务器 | nginx 或 Apache (mod_rewrite) |
| 数据库 | SQLite（默认）或 MySQL 5.7+ / MariaDB 10.3+ |

### 一分钟部署

```bash
git clone <仓库地址> fileroll
cd fileroll
composer install --no-dev
mkdir -p storage/content storage/temp storage/trash
chmod -R 775 storage/ config/
```

然后配置 Web 服务器指向 `public/`（推荐）或项目根目录（LNMP 方案），访问域名进入安装向导。

> **详细部署说明**（含 nginx/Apache 完整配置、LNMP 一键包、权限、FAQ）请参阅 [DEPLOYMENT.zh.md](./DEPLOYMENT.zh.md)。

### 安装向导

浏览器访问 `https://yourdomain.com/`，按引导完成：

1. 环境检查
2. 数据库配置（SQLite 或 MySQL）
3. 创建管理员账号
4. 完成安装

安装完成后建议删除 `install/` 目录：

```bash
rm -rf install/
```

## 目录结构

```
├── public/              # Web 入口（标准部署下 DocumentRoot 指向这里）
│   ├── index.php
│   └── assets/
├── config/              # 配置（config.php 由安装生成，不进入 Git）
├── src/                 # PHP 源码（PSR-4: FileRoll\\）
├── templates/           # 视图模板
├── storage/             # 上传文件、临时文件、回收站、数据库、日志
├── install/             # Web 安装向导（安装后删除）
├── vendor/              # Composer 依赖
├── lang/                # 国际化文件
├── tests/               # PHPUnit 测试
├── scripts/console.php  # CLI 管理脚本
└── DEPLOYMENT.md        # 详细部署指南
```

## CLI 管理

```bash
php scripts/console.php migrate          # 运行数据库迁移
php scripts/console.php create-user      # 创建用户
php scripts/console.php reset-password   # 重置密码
php scripts/console.php storage-stats    # 存储统计
php scripts/console.php cleanup-sessions # 清理过期会话
```

## WebDAV 使用

FileRoll 提供标准 WebDAV 端点：

```
https://yourdomain.com/dav
```

使用 Basic Auth 登录后即可像操作本地磁盘一样管理云端文件。支持大文件分块上传，兼容 Nextcloud/ownCloud 客户端的部分同步协议。

## 安全说明

- 密码使用 bcrypt（cost=12）存储
- 所有表单提交均验证 CSRF Token
- 文件按内容哈希存储，避免重复并支持版本回溯
- 上传文件名与路径经过严格消毒，防止路径遍历
- WebDAV 上传只能由认证用户本人完成，禁止跨用户访问
- 生产环境默认禁用 WebDAV HTML 浏览器插件与调试日志
- 详细安全修复记录见提交历史与 [DEPLOYMENT.zh.md](./DEPLOYMENT.zh.md) 服务器配置章节

## 配置

所有配置集中在 `config/config.php`，由安装向导生成。关键项：

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',
    'debug' => false,          // 生产环境务必关闭
],
'session' => [
    'cookie_params' => [
        'secure' => true,      // HTTPS 环境下启用
        'httponly' => true,
        'samesite' => 'Lax',
    ],
],
```

更多配置项参考 `config/config.sample.php`。

## 运行测试

```bash
composer install          # 安装开发依赖
vendor/bin/phpunit        # 运行全部测试
```

## 许可证

MIT License
