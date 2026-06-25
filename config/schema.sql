CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(64) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(128),
    storage_quota BIGINT DEFAULT 10737418240,
    role VARCHAR(16) DEFAULT 'user',
    avatar_path VARCHAR(512),
    last_login_at DATETIME,
    is_active TINYINT DEFAULT 1,
    language VARCHAR(10) DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER REFERENCES files(id) ON DELETE CASCADE,
    name VARCHAR(512) NOT NULL,
    mime_type VARCHAR(128),
    size BIGINT DEFAULT 0,
    is_folder TINYINT DEFAULT 0,
    content_hash VARCHAR(64),
    storage_path VARCHAR(512),
    is_starred TINYINT DEFAULT 0,
    is_trashed TINYINT DEFAULT 0,
    trashed_at DATETIME,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    created_by INTEGER REFERENCES users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME
);

CREATE TABLE IF NOT EXISTS file_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    version_number INTEGER NOT NULL,
    content_hash VARCHAR(64) NOT NULL,
    storage_path VARCHAR(512) NOT NULL,
    size BIGINT NOT NULL,
    created_by INTEGER REFERENCES users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_level VARCHAR(16) NOT NULL,
    granted_by INTEGER REFERENCES users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(file_id, user_id)
);

CREATE TABLE IF NOT EXISTS shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
    shared_by INTEGER NOT NULL REFERENCES users(id),
    shared_with INTEGER REFERENCES users(id),
    token VARCHAR(64) UNIQUE NOT NULL,
    permission_level VARCHAR(16) DEFAULT 'read',
    password_hash VARCHAR(255),
    expires_at DATETIME,
    max_downloads INTEGER,
    download_count INTEGER DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(64) NOT NULL,
    resource_type VARCHAR(32),
    resource_id INTEGER,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_files_parent ON files(parent_id, name);
CREATE INDEX IF NOT EXISTS idx_files_owner ON files(owner_id);
CREATE INDEX IF NOT EXISTS idx_files_trash ON files(is_trashed, trashed_at);
CREATE INDEX IF NOT EXISTS idx_files_content_hash ON files(content_hash);
CREATE INDEX IF NOT EXISTS idx_versions_file ON file_versions(file_id, version_number);
CREATE INDEX IF NOT EXISTS idx_permissions_file ON permissions(file_id, user_id);
CREATE INDEX IF NOT EXISTS idx_shares_token ON shares(token);
CREATE INDEX IF NOT EXISTS idx_shares_file ON shares(file_id);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action, created_at);
CREATE TABLE IF NOT EXISTS webdav_locks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uri VARCHAR(512) NOT NULL,
    token VARCHAR(128) UNIQUE NOT NULL,
    created INTEGER NOT NULL,
    expires INTEGER NOT NULL,
    owner VARCHAR(255),
    depth INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_webdav_locks_uri ON webdav_locks(uri);
CREATE INDEX IF NOT EXISTS idx_webdav_locks_token ON webdav_locks(token);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(128) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

