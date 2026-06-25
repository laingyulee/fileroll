# FileRoll

[English](../README.md) · [中文](./README.zh.md) · 日本語 · [Español](./README.es.md)

FileRoll は WebDAV に対応した個人用クラウドストレージアプリケーションで、ファイル管理、バージョン管理、共有、多ユーザー管理などの機能を提供します。PHP 8+ と SQLite/MySQL で構築されており、ブラウザまたは任意の WebDAV クライアントからアクセスできます。

## 機能

- **ファイルとフォルダの管理**：ドラッグ＆ドロップアップロード、移動、コピー、名前変更、ゴミ箱、お気に入り
- **バージョン管理**：ファイル履歴バージョンの保存とワンクリック復元
- **共有機能**：公開 / パスワード保護 / 期限付き共有リンクの生成
- **多ユーザーと権限**：管理者がユーザー、クォータ、ロールを管理可能
- **WebDAV 対応**：Windows エクスプローラー、macOS Finder、Cyberduck、RaiDrive などのクライアントと互換
- **セキュリティ強化**：bcrypt パスワードハッシュ、CSRF 保護、パストラバーサル防止、XSS フィルタリング、レート制限
- **国際化**：8 言語の UI に対応
- **CLI 管理**：マイグレーション、ユーザー作成、パスワードリセット、クリーンアップタスクなどの組み込みコマンド

## 技術スタック

- PHP 8.0+
- SQLite / MySQL / MariaDB
- Composer 依存関係管理
- PSR-4 オートロード、MVC アーキテクチャ
- nginx / Apache デプロイ

## クイックスタート

### 環境要件

| 項目 | 要件 |
|---|---|
| PHP | >= 8.0 |
| 拡張機能 | PDO、pdo_sqlite/pdo_mysql、json、mbstring、session、ctype、filter、fileinfo、gd |
| Web サーバー | nginx または Apache (mod_rewrite) |
| データベース | SQLite（デフォルト）または MySQL 5.7+ / MariaDB 10.3+ |

### 1分間デプロイ

```bash
git clone <リポジトリURL> fileroll
cd fileroll
composer install --no-dev
mkdir -p storage/content storage/temp storage/trash
chmod -R 775 storage/ config/
```

次に、Web サーバーを `public/`（推奨）またはプロジェクトルート（LNMP 方式）に向けるよう設定し、ドメインにアクセスしてインストールウィザードを開始します。

> **詳細なデプロイ手順**（nginx/Apache の完全な設定、LNMP ワンクリックパッケージ、権限、FAQ など）は [DEPLOYMENT.ja.md](./DEPLOYMENT.ja.md) を参照してください。

### インストールウィザード

ブラウザで `https://yourdomain.com/` にアクセスし、ガイドに従ってください。

1. 環境チェック
2. データベース設定（SQLite または MySQL）
3. 管理者アカウントの作成
4. インストール完了

インストール後は `install/` ディレクトリを削除することを推奨します。

```bash
rm -rf install/
```

## ディレクトリ構成

```
├── public/              # Web エントリーポイント（標準デプロイでは DocumentRoot がここを指す）
│   ├── index.php
│   └── assets/
├── config/              # 設定（config.php はインストーラーが生成し、Git には含めない）
├── src/                 # PHP ソースコード（PSR-4: FileRoll\\）
├── templates/           # ビューテンプレート
├── storage/             # アップロードファイル、一時ファイル、ゴミ箱、データベース、ログ
├── install/             # Web インストールウィザード（インストール後に削除）
├── vendor/              # Composer 依存関係
├── lang/                # 国際化ファイル
├── tests/               # PHPUnit テスト
├── scripts/console.php  # CLI 管理スクリプト
└── DEPLOYMENT.md        # 詳細デプロイガイド
```

## CLI 管理

```bash
php scripts/console.php migrate          # データベースマイグレーションを実行
php scripts/console.php create-user      # ユーザーを作成
php scripts/console.php reset-password   # パスワードをリセット
php scripts/console.php storage-stats    # ストレージ統計
php scripts/console.php cleanup-sessions # 期限切れセッションをクリーンアップ
```

## WebDAV の使用

FileRoll は標準の WebDAV エンドポイントを提供します。

```
https://yourdomain.com/dav
```

Basic Auth でログインすると、ローカルディスクのようにクラウドファイルを管理できます。大容量ファイルのチャンクアップロードに対応し、Nextcloud/ownCloud クライアントの部分同期プロトコルとも互換性があります。

## セキュリティに関する注意

- パスワードは bcrypt（cost=12）で保存されます
- すべてのフォーム送信は CSRF トークンを検証します
- ファイルはコンテンツハッシュで保存され、重複を避けつつバージョン巻き戻しをサポートします
- アップロードされたファイル名とパスは厳格にサニタイズされ、パストラバーサルを防止します
- WebDAV アップロードは認証されたユーザー自身のみが実行でき、他ユーザーのアクセスは禁止されています
- 本番環境では、WebDAV HTML ブラウザプラグインとデバッグログがデフォルトで無効になっています
- 詳細なセキュリティ修正履歴はコミット履歴と [DEPLOYMENT.ja.md](./DEPLOYMENT.ja.md) のサーバー設定章を参照してください

## 設定

すべての設定は `config/config.php` に集中しており、インストールウィザードによって生成されます。主な項目：

```php
'app' => [
    'url' => 'https://fileroll.yourdomain.com',
    'debug' => false,          // 本番環境では必ず無効にする
],
'session' => [
    'cookie_params' => [
        'secure' => true,      // HTTPS 環境で有効にする
        'httponly' => true,
        'samesite' => 'Lax',
    ],
],
```

その他の設定項目については `config/config.sample.php` を参照してください。

## テストの実行

```bash
composer install          # 開発依存関係をインストール
vendor/bin/phpunit        # すべてのテストを実行
```

## ライセンス

MIT License
