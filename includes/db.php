<?php
/**
 * DB接続
 * config/config.php が存在しない場合は初期セットアップへ誘導する
 */

/**
 * IIS + PHP(FastCGI)環境では、error_log が未設定のままだとPHPの警告・エラーが
 * 標準エラー出力(stderr)に書き込まれ、ページ自体は正常でもIISが500を返してしまうことがある。
 * これを避けるため、error_log が明示的に設定されていない場合は自動的にファイルへ固定する。
 * 副次効果として、COBIS専用のエラーログが web/logs/php_errors.log にまとまるため、
 * 他のサービスと混在したサーバーログを探す必要がなくなる。
 */
if (!ini_get('error_log')) {
    $cobisLogDir = __DIR__ . '/../logs';
    if (!is_dir($cobisLogDir)) {
        @mkdir($cobisLogDir, 0755, true);
    }
    if (is_dir($cobisLogDir) && is_writable($cobisLogDir)) {
        @ini_set('log_errors', '1');
        @ini_set('error_log', $cobisLogDir . '/php_errors.log');
    }
}

$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    // includes/db.php はセッション開始前に呼ばれる想定のファイルもあるため
    // ここでは単純にリダイレクトする（setup.php 自身からの読み込みは避けること）
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($current !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
    return; // setup.php からの include ならここで終了（DB未設定状態）
}

require_once $configPath;

function get_pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
