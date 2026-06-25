# FileRoll WebDAV 接口适配指南

[English](./WEBDAV.md) · 中文

本文档面向第三方 WebDAV 客户端开发者，说明 FileRoll 的 WebDAV 端点、认证方式、支持的 HTTP 方法、路径映射、分块上传协议以及自定义属性，便于客户端正确连接与操作。

## 1. 兼容性说明

FileRoll 基于 [SabreDAV](https://sabre.io/dav/) 实现，并在接口层面模拟 ownCloud/Nextcloud 的部分端点，以兼容常见桌面与移动端 WebDAV 客户端（如 Windows 资源管理器、macOS Finder、Cyberduck、RaiDrive、Mountain Duck、ownCloud 桌面客户端等）。

- 协议：WebDAV 1.0 + HTTP/1.1
- 认证：HTTP Basic Authentication
- 传输：建议使用 HTTPS
- 字符编码：UTF-8

## 2. WebDAV 端点

客户端可使用以下任意端点访问文件。推荐优先使用 `/dav/files/<用户名>/`。

| 端点 | 说明 |
|---|---|
| `https://<域名>/dav` | 旧版 / 通用根端点，映射到当前登录用户的根目录 |
| `https://<域名>/dav/files/<用户名>/` | 推荐端点，与 ownCloud/Nextcloud 兼容 |
| `https://<域名>/remote.php/webdav/` | ownCloud 传统端点 |
| `https://<域名>/remote.php/dav/` | Nextcloud 传统端点 |
| `https://<域名>/remote.php/dav/files/<用户名>/` | Nextcloud 用户文件端点 |
| `https://<域名>/dav/uploads/<用户名>/` | 分块上传临时端点 |
| `https://<域名>/remote.php/dav/uploads/<用户名>/` | 分块上传临时端点（兼容写法） |

`<用户名>` 为 FileRoll 登录用户名（非邮箱、非显示名）。

> 所有 WebDAV 请求最终都会由 `public/index.php` 统一处理，因此 Web 服务器需将上述路径正确转发到 PHP。

## 3. 认证

FileRoll WebDAV 仅支持 **HTTP Basic Authentication**。

- **用户名**：FileRoll 登录用户名
- **密码**：FileRoll 登录密码（与网页端一致，使用 `password_hash()` 校验）
- **Realm**：`FileRoll WebDAV`
- 账号必须处于激活状态（`users.is_active = 1`）

示例请求头：

```http
Authorization: Basic dXNlcjpwYXNzd29yZA==
```

> 当前不支持生成独立的“应用密码”或 Token，客户端直接使用用户账号密码。

## 4. 支持的 HTTP 方法

FileRoll WebDAV 支持以下标准 WebDAV 方法：

| 方法 | 用途 |
|---|---|
| `OPTIONS` | 查询支持的方法与 Dav 头 |
| `GET` | 下载文件 |
| `HEAD` | 获取文件元信息（无响应体） |
| `PUT` | 上传/覆盖文件 |
| `DELETE` | 删除文件或文件夹 |
| `MKCOL` | 创建文件夹 |
| `COPY` | 复制文件或文件夹 |
| `MOVE` | 移动/重命名文件或文件夹 |
| `PROPFIND` | 获取文件/文件夹属性 |
| `PROPPATCH` | 设置属性（当前保留接口，不持久化） |
| `LOCK` / `UNLOCK` | 文件锁定（由 LockBackend 维护） |
| `REPORT` | 保留 |

## 5. 路径映射

### 5.1 文件路径

以推荐端点为例：

```
https://example.com/dav/files/alice/Photos/2026/cat.jpg
```

逻辑路径为：

```
/Photos/2026/cat.jpg
```

即 `dav/files/<用户名>/` 之后的部分为用户文件空间内的相对路径。

### 5.2 根目录

`dav/files/<用户名>/` 本身对应用户的根目录，内部展示该用户所有顶层文件与文件夹。

### 5.3 远程端点兼容

- `/remote.php/webdav/<路径>` 直接映射到登录用户的根目录。
- `/remote.php/dav/files/<用户名>/<路径>` 与 `/dav/files/<用户名>/<路径>` 等价。

## 6. WebDAV 属性

FileRoll 在 `PROPFIND` 中返回以下常用属性，分为 DAV 标准命名空间与 ownCloud 扩展命名空间。

### 6.1 `{DAV:}` 命名空间

| 属性 | 文件 | 文件夹 | 说明 |
|---|---|---|---|
| `displayname` | 文件名 | 文件夹名 | |
| `resourcetype` | 空 | `<d:collection/>` | |
| `getcontenttype` | MIME 类型 | 不存在 | 未知时返回 `application/octet-stream` |
| `getcontentlength` | 字节大小 | `0` | |
| `getlastmodified` | 更新时间戳 | 更新时间戳 | Unix 时间戳 |
| `getetag` | `"<contentHash>"` | `"<md5(id-ts)>"` | 用于缓存与冲突检测 |

### 6.2 `{http://owncloud.org/ns}` 命名空间

| 属性 | 文件 | 文件夹 | 说明 |
|---|---|---|---|
| `fileid` | 文件 ID | 文件夹 ID（根目录为 `0`） | |
| `id` | `str_pad(fileid, 8, '0', STR_PAD_LEFT) + 'oc'` | 同上 | ownCloud 风格 ID |
| `permissions` | `RDNVW` | `RDNVCK` | 兼容属性，当前为固定值 |
| `size` | 文件大小 | `0` | |

> 客户端请求 `allprops` 或显式请求上述属性时均可获得。`PROPPATCH` 目前不持久化任何值，仅返回成功。

## 7. 分块上传协议

FileRoll 支持 ownCloud/Nextcloud 风格的“大文件分块上传”，用于避免单个大文件 PUT 超时或被服务器限制。

### 7.1 流程

1. **创建上传会话**

   在分块上传端点下创建一个以数字 ID 命名的目录作为 `chunkId`，通常使用时间戳或随机数：

   ```http
   MKCOL /dav/files/alice/Photos/big.zip
   ```

   客户端实际应先使用：

   ```http
   MKCOL /dav/uploads/alice/<chunkId>/
   ```

   然后逐个上传分块：

   ```http
   PUT /dav/uploads/alice/<chunkId>/<chunkNum>
   Content-Length: <分块大小>

   <二进制数据>
   ```

   `<chunkNum>` 为数字，例如 `0`、`1`、`2`，服务端按字符串排序后拼接。

2. **提交合并**

   所有分块上传完成后，向 `.file` 节点发送 `MOVE` 请求，并在 `Destination` 头中指定最终文件路径：

   ```http
   MOVE /dav/uploads/alice/<chunkId>/.file
   Destination: https://example.com/dav/files/alice/Photos/big.zip
   ```

   服务端会按 `<chunkNum>` 排序合并所有分块，移动到目标路径，并清理临时目录。

### 7.2 响应头

分块上传合并成功后，响应可能包含以下头：

| 头 | 说明 |
|---|---|
| `ETag` | 最终文件 ETag |
| `OC-FileId` | 最终文件 ID |
| `Content-Length: 0` | |
| HTTP 状态码 | `201 Created`（新建）或 `204 No Content`（覆盖） |

### 7.3 能力声明

FileRoll 在 `/ocs/v2.php/cloud/capabilities` 中声明：

```json
{
  "ocs": {
    "data": {
      "capabilities": {
        "dav": {
          "chunking": "1.0"
        },
        "files": {
          "bigfilechunking": true,
          "undelete": true,
          "versioning": true
        }
      }
    }
  }
}
```

## 8. ownCloud/Nextcloud 兼容接口

为便于客户端自动发现，FileRoll 还提供以下非 WebDAV 接口：

| 端点 | 说明 |
|---|---|
| `GET /status.php` | 返回 ownCloud 风格状态（版本、产品名等） |
| `GET /ocs/v2.php/cloud/capabilities` | 能力声明 |
| `GET /ocs/v2.php/cloud/user` | 当前用户信息，包含 `home` 字段指向 WebDAV 根路径 |
| `GET /graph/v1.0/me/drives` | Microsoft Graph 风格驱动器信息，包含 `webDavUrl` |

上述接口同样使用 Basic Auth。

## 9. Web 服务器配置

以 nginx 为例，确保 `/dav` 路径被转发到 `index.php`，并允许 WebDAV 相关方法：

```nginx
location /dav {
    try_files $uri /index.php?$query_string;
    limit_except GET HEAD POST PUT DELETE MKCOL COPY MOVE OPTIONS PROPFIND PROPPATCH LOCK UNLOCK REPORT {
        deny all;
    }
}
```

如果使用子目录部署或 LNMP 一键包，请参考项目根目录下的 `nginx.conf.example`、`nginx.conf.subdir.example` 与 `nginx.conf.lnmp.example`。

> Apache 用户请确保已启用 `mod_rewrite`，并使用项目提供的 `.htaccess`（位于 `public/`）。

## 10. 客户端适配建议

1. **优先使用 `/dav/files/<用户名>/`** 作为根路径，兼容性最好。
2. **大文件上传** 建议走分块上传流程（chunking），避免单 PUT 被服务器限制。
3. **路径编码**：服务端会对 URL 路径进行解码，客户端应使用 UTF-8 编码并避免特殊控制字符。
4. **Etag 使用**：文件 ETag 基于 `contentHash`，文件夹 ETag 基于 `id + updatedAt`，可用于缓存。
5. **锁**：支持 WebDAV 锁，但多客户端并发编辑时仍建议由应用层控制冲突。
6. **会话**：WebDAV 使用 PHP Session 记录登录用户，Cookie 支持应保持开启。

## 11. 示例请求

### 列目录

```bash
curl -u alice:password \
  -X PROPFIND \
  -H "Depth: 1" \
  https://example.com/dav/files/alice/
```

### 下载文件

```bash
curl -u alice:password \
  -O \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### 上传文件

```bash
curl -u alice:password \
  -T cat.jpg \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### 创建文件夹

```bash
curl -u alice:password \
  -X MKCOL \
  https://example.com/dav/files/alice/Photos/
```

### 删除文件

```bash
curl -u alice:password \
  -X DELETE \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### 移动/重命名

```bash
curl -u alice:password \
  -X MOVE \
  -H "Destination: https://example.com/dav/files/alice/Photos/kitten.jpg" \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

## 12. 限制与注意事项

- 当前 WebDAV 密码与网页端密码相同，暂不支持独立应用密码。
- `PROPPATCH` 已开放但不做持久化，自定义属性会被忽略。
- 回收站、版本控制功能通过 Web UI 或 REST API 使用，WebDAV 层面直接 `DELETE` 会进入回收站（由 `FileService::delete` 决定）。
- 服务器 `client_max_body_size` 与 PHP `post_max_size` / `upload_max_filesize` 会影响单文件 PUT 上限；大文件请使用分块上传。

---

如有其他适配问题，请参考项目源码 `src/WebDAV/` 目录下的实现，或在 Issue 区反馈。
