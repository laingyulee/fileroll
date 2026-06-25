# FileRoll WebDAV Integration Guide

English · [中文](./WEBDAV.zh.md)

This document is intended for third-party WebDAV client developers. It describes FileRoll's WebDAV endpoints, authentication, supported HTTP methods, path mapping, chunked upload protocol, and custom properties so clients can connect and operate correctly.

## 1. Compatibility

FileRoll is built on [SabreDAV](https://sabre.io/dav/) and partially emulates ownCloud/Nextcloud endpoints to support common desktop and mobile WebDAV clients (Windows Explorer, macOS Finder, Cyberduck, RaiDrive, Mountain Duck, ownCloud Desktop Client, etc.).

- Protocol: WebDAV 1.0 + HTTP/1.1
- Authentication: HTTP Basic Authentication
- Transport: HTTPS recommended
- Character encoding: UTF-8

## 2. WebDAV Endpoints

Clients may use any of the following endpoints. The recommended path is `/dav/files/<username>/`.

| Endpoint | Description |
|---|---|
| `https://<domain>/dav` | Generic root endpoint, maps to the authenticated user's root directory |
| `https://<domain>/dav/files/<username>/` | **Recommended** endpoint, ownCloud/Nextcloud compatible |
| `https://<domain>/remote.php/webdav/` | Legacy ownCloud endpoint |
| `https://<domain>/remote.php/dav/` | Legacy Nextcloud endpoint |
| `https://<domain>/remote.php/dav/files/<username>/` | Nextcloud user-files endpoint |
| `https://<domain>/dav/uploads/<username>/` | Chunked upload staging endpoint |
| `https://<domain>/remote.php/dav/uploads/<username>/` | Chunked upload staging endpoint (alternate) |

`<username>` is the FileRoll login username (not email or display name).

> All WebDAV requests are ultimately handled by `public/index.php`, so the web server must forward the above paths to PHP.

## 3. Authentication

FileRoll WebDAV supports **HTTP Basic Authentication** only.

- **Username**: FileRoll login username
- **Password**: FileRoll login password (same as the web UI; verified with `password_hash()`)
- **Realm**: `FileRoll WebDAV`
- The account must be active (`users.is_active = 1`)

Example header:

```http
Authorization: Basic dXNlcjpwYXNzd29yZA==
```

> Independent app passwords or tokens are not supported at this time.

## 4. Supported HTTP Methods

FileRoll WebDAV supports the following standard methods:

| Method | Purpose |
|---|---|
| `OPTIONS` | Query supported methods and Dav headers |
| `GET` | Download a file |
| `HEAD` | Retrieve file metadata without body |
| `PUT` | Upload or overwrite a file |
| `DELETE` | Delete a file or folder |
| `MKCOL` | Create a folder |
| `COPY` | Copy a file or folder |
| `MOVE` | Move or rename a file or folder |
| `PROPFIND` | Retrieve file/folder properties |
| `PROPPATCH` | Set properties (interface present, not persisted) |
| `LOCK` / `UNLOCK` | File locking via LockBackend |
| `REPORT` | Reserved |

## 5. Path Mapping

### 5.1 File paths

Using the recommended endpoint:

```
https://example.com/dav/files/alice/Photos/2026/cat.jpg
```

The logical path is:

```
/Photos/2026/cat.jpg
```

Everything after `dav/files/<username>/` is the user's file-space relative path.

### 5.2 Root directory

`dav/files/<username>/` corresponds to the user's root directory, listing all top-level files and folders.

### 5.3 Legacy endpoint compatibility

- `/remote.php/webdav/<path>` maps directly to the authenticated user's root directory.
- `/remote.php/dav/files/<username>/<path>` is equivalent to `/dav/files/<username>/<path>`.

## 6. WebDAV Properties

FileRoll returns the following properties in `PROPFIND`, grouped by namespace.

### 6.1 `{DAV:}` namespace

| Property | File | Folder | Notes |
|---|---|---|---|
| `displayname` | File name | Folder name | |
| `resourcetype` | Empty | `<d:collection/>` | |
| `getcontenttype` | MIME type | Not present | Falls back to `application/octet-stream` |
| `getcontentlength` | Size in bytes | `0` | |
| `getlastmodified` | Update timestamp | Update timestamp | Unix timestamp |
| `getetag` | `"<contentHash>"` | `"<md5(id-ts)>"` | Used for caching and conflict detection |

### 6.2 `{http://owncloud.org/ns}` namespace

| Property | File | Folder | Notes |
|---|---|---|---|
| `fileid` | File ID | Folder ID (`0` for root) | |
| `id` | `str_pad(fileid, 8, '0', STR_PAD_LEFT) + 'oc'` | Same as file | ownCloud-style ID |
| `permissions` | `RDNVW` | `RDNVCK` | Compatibility placeholder |
| `size` | File size | `0` | |

> These properties are returned for `allprops` or explicit requests. `PROPPATCH` is accepted but values are not persisted.

## 7. Chunked Upload Protocol

FileRoll supports ownCloud/Nextcloud-style chunked uploads to avoid single large `PUT` timeouts or server limits.

### 7.1 Flow

1. **Create an upload session**

   Create a numeric upload directory (`chunkId`) under the chunked upload endpoint, typically a timestamp or random number:

   ```http
   MKCOL /dav/uploads/alice/<chunkId>/
   ```

2. **Upload chunks**

   Upload each chunk as a separate file:

   ```http
   PUT /dav/uploads/alice/<chunkId>/<chunkNum>
   Content-Length: <chunk-size>

   <binary data>
   ```

   `<chunkNum>` is a numeric string such as `0`, `1`, `2`. The server sorts chunks lexicographically and concatenates them.

3. **Finalize**

   When all chunks are uploaded, send a `MOVE` request to the `.file` node with the final destination in the `Destination` header:

   ```http
   MOVE /dav/uploads/alice/<chunkId>/.file
   Destination: https://example.com/dav/files/alice/Photos/big.zip
   ```

   The server merges the chunks in order, moves the result to the destination, and cleans up the temporary directory.

### 7.2 Response headers

After a successful chunked upload finalization, the response may include:

| Header | Description |
|---|---|
| `ETag` | ETag of the final file |
| `OC-FileId` | ID of the final file |
| `Content-Length: 0` | |
| HTTP status | `201 Created` (new file) or `204 No Content` (overwrite) |

### 7.3 Capability advertisement

FileRoll declares chunked upload support via `/ocs/v2.php/cloud/capabilities`:

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

## 8. ownCloud/Nextcloud Compatibility Endpoints

To help clients auto-discover the service, FileRoll also provides the following non-WebDAV endpoints:

| Endpoint | Description |
|---|---|
| `GET /status.php` | ownCloud-style status (version, product name, etc.) |
| `GET /ocs/v2.php/cloud/capabilities` | Capability advertisement |
| `GET /ocs/v2.php/cloud/user` | Current user info, including `home` pointing to the WebDAV root |
| `GET /graph/v1.0/me/drives` | Microsoft Graph-style drive info, including `webDavUrl` |

These endpoints also use Basic Auth.

## 9. Web Server Configuration

For nginx, ensure `/dav` is forwarded to `index.php` and WebDAV methods are allowed:

```nginx
location /dav {
    try_files $uri /index.php?$query_string;
    limit_except GET HEAD POST PUT DELETE MKCOL COPY MOVE OPTIONS PROPFIND PROPPATCH LOCK UNLOCK REPORT {
        deny all;
    }
}
```

For subdirectory or LNMP deployments, see `nginx.conf.example`, `nginx.conf.subdir.example`, and `nginx.conf.lnmp.example` in the project root.

> Apache users should enable `mod_rewrite` and use the provided `.htaccess` in `public/`.

## 10. Client Adaptation Notes

1. Prefer `/dav/files/<username>/` as the root path for best compatibility.
2. Use chunked uploads for large files to avoid single-PUT limits.
3. Encode paths as UTF-8 and avoid control characters; the server URL-decodes paths.
4. ETags are based on `contentHash` for files and `id + updatedAt` for folders; use them for caching.
5. WebDAV locking is supported, but application-level conflict handling is still recommended.
6. WebDAV relies on PHP sessions to track the authenticated user; cookies should remain enabled.

## 11. Example Requests

### List directory

```bash
curl -u alice:password \
  -X PROPFIND \
  -H "Depth: 1" \
  https://example.com/dav/files/alice/
```

### Download a file

```bash
curl -u alice:password \
  -O \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### Upload a file

```bash
curl -u alice:password \
  -T cat.jpg \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### Create a folder

```bash
curl -u alice:password \
  -X MKCOL \
  https://example.com/dav/files/alice/Photos/
```

### Delete a file

```bash
curl -u alice:password \
  -X DELETE \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

### Move / rename

```bash
curl -u alice:password \
  -X MOVE \
  -H "Destination: https://example.com/dav/files/alice/Photos/kitten.jpg" \
  https://example.com/dav/files/alice/Photos/cat.jpg
```

## 12. Limitations and Notes

- The WebDAV password is the same as the web login password; independent app passwords are not supported.
- `PROPPATCH` is accepted but no values are persisted.
- Trash and versioning are available through the web UI or REST API; a direct WebDAV `DELETE` enters the trash (as determined by `FileService::delete`).
- Server settings such as `client_max_body_size`, PHP `post_max_size`, and `upload_max_filesize` affect single-PUT uploads; use chunked uploads for large files.

---

For further adaptation questions, refer to the implementation in `src/WebDAV/` or open an issue.
