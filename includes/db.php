<?php
/**
 * DB接続
 * config/config.php が存在しない場合は初期セットアップへ誘導する
 */

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
