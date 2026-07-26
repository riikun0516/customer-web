<?php
/**
 * 共通ヘルパー関数
 */

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function flash_set($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('不正なリクエストです（CSRFトークン不一致）。ページを再読み込みしてやり直してください。');
    }
}

function status_badge_class($status) {
    return 'badge status-' . e($status);
}

/**
 * このコードベース自体のバージョン番号を返す（web/VERSION ファイルから読み込む）。
 * GitHub連携の「システム更新」機能とは独立して、手動デプロイの場合でも
 * 常にアプリ自身が現在のバージョンを表示できるようにするためのもの。
 * リリースごとに VERSION ファイルの中身を更新してタグを打つ運用を想定。
 */
function cobis_version() {
    static $version = null;
    if ($version === null) {
        $path = __DIR__ . '/../VERSION';
        $version = is_file($path) ? trim(file_get_contents($path)) : '不明';
    }
    return $version;
}
