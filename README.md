# COBIS（Connect Of Business System） - Web本体（PHP + MySQL）

顧客・案件・見積書・契約書・請求書・領収書をまとめて管理するビジネス管理ツール「COBIS」のWeb本体です。PHP + MySQLで動作し、共用レンタルサーバー（さくらインターネット、Xserverなど）でもComposer不要でそのまま利用できます。

デスクトップから使うためのランチャー（Electron版）は別リポジトリです。
👉 https://github.com/riikun0516/customer.git

## 主な機能

- **案件管理**: 誰がどの案件を担当しているか、ステータス（未着手/進行中/保留/完了）、金額、期限を一覧表示。進捗メモを時系列で記録
- **顧客管理**: 顧客情報の登録・編集、紐づく案件の確認
- **見積書 / 契約書 / 請求書 / 領収書のPDF発行**:
  - 案件に設定した金額が明細・金額欄の初期値として自動反映され、工賃や交通費などをその場で追加可能
  - 契約書は顧客（甲）・自社（乙）の当事者情報を自動反映し、条項本文は自社情報設定のテンプレートから引き継ぎ可能。署名・捺印欄付き
  - 請求書には自社情報設定で登録した振込先（銀行口座）を印字
  - 会社ロゴをアップロードしておくと、PDF上部に自動で印字
  - 消費税の自動計算、書類番号の自動採番（`INV-2026-0001` 形式）
- **ユーザー管理・権限**:
  - 管理者: 全データの閲覧・編集・削除、ユーザー管理、自社情報設定
  - 一般ユーザー: 案件・顧客の閲覧は全件可能。編集は自分が担当する案件のみ
- **システム更新（管理者のみ）**: このGitHubリポジトリの **Releaseタグ** と連携し、最新バージョンの確認・リリース一覧からの選択適用（ロールバック含む）・ファイル整合性確認・再インストール（修復）が行えます

## 動作要件

- PHP 7.4以上（PHP 8.x 推奨）
- MySQL 5.7以上 または MySQL 8.x
- PHP拡張: `pdo_mysql`, `mbstring`, `curl`, `zip`（`ZipArchive`）（ほとんどの共用ホスティングで標準有効）
- Composerは不要です（PDF生成ライブラリ [tFPDF](https://github.com/Setasign/tFPDF) と日本語フォント[IPAexゴシック](https://moji.or.jp/ipafont/)を同梱済み）
- サーバーから `api.github.com` ・ `codeload.github.com` へのHTTPSアウトバウンド通信（システム更新機能を使う場合）

## セットアップ手順

1. このリポジトリを公開ディレクトリにクローン（またはZIPをダウンロードしてアップロード）します。

   ```bash
   git clone https://github.com/riikun0516/customer-web.git
   ```

2. レンタルサーバーの管理画面でMySQLデータベースを1つ作成し、接続用のユーザー名・パスワードを控えます。
3. `config/` ディレクトリと `uploads/` ディレクトリに書き込み権限があることを確認します（`config/config.php` の自動生成、ロゴ画像の保存に使用します）。
4. ブラウザで `https://your-domain.com/` にアクセスすると初期セットアップ画面が表示されます。
   - **Step1**: DBホスト・ポート・ユーザー名・パスワード・データベース名を入力 →「接続テストして次へ」（成功すると自動でテーブルを作成します）
   - **Step2**: 管理者アカウント（ユーザー名・表示名・パスワード）を作成
5. セットアップ完了後、ログイン画面から利用開始できます。

## 既存環境をアップデートする場合

管理者アカウントでログイン後、メニューの **「システム更新」** から、このリポジトリの [Releases](https://github.com/riikun0516/customer-web/releases) で公開したバージョン（例: `v1.1.0`）を選んでワンクリックで適用できます。`config/config.php` とアップロード済みロゴは保護され、上書きされません。過去のバージョンを選べばロールバックも可能です。

新しいバージョンをリリースする際は、GitHubの「Releases」からタグ（例: `v1.1.0`）を作成して公開してください。

手動で更新したい場合は、最新版のファイルを上書きアップロードした後、管理者アカウントでログインし、メニューの **「DBスキーマ更新」** を実行してください。`CREATE TABLE IF NOT EXISTS` / 列の追加のみを行うため、既存のデータが失われることはありません。

## ディレクトリ構成（抜粋）

```
.
├── config/               DB接続設定（初期セットアップ時に自動生成）
├── includes/             共通処理（DB接続・認証・PDFヘルパー・スキーマ定義）
├── vendor/tfpdf/         PDF生成ライブラリ（tFPDF・日本語フォント同梱）
├── uploads/logos/        アップロードした会社ロゴの保存先
├── setup.php             初期セットアップウィザード
├── migrate.php           既存環境向けDBスキーマ更新（管理者専用）
├── login.php / logout.php
├── cases.php / case_form.php           案件一覧・編集
├── customers.php / customer_form.php   顧客一覧・編集
├── quotes.php / quote_form.php / quote_pdf.php       見積書
├── contracts.php / contract_form.php / contract_pdf.php 契約書
├── invoices.php / invoice_form.php / invoice_pdf.php 請求書
├── receipts.php / receipt_form.php / receipt_pdf.php 領収書
├── users.php / user_form.php           ユーザー管理（管理者専用）
└── company_settings.php                自社情報・振込先・ロゴ設定（管理者専用）
```

## セキュリティ

- パスワードは `password_hash()` によりハッシュ化して保存
- 全フォーム送信をCSRFトークンで保護
- `config/` `includes/` は `.htaccess` により直接アクセスを禁止（Apache環境向け。Nginxの場合は別途アクセス制限が必要です）
- 本番運用ではHTTPS化を強く推奨します

## ライセンス

社内利用を想定した非公開プロジェクトです。
