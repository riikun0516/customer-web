<?php
/**
 * 認証・権限管理
 * このファイルを読み込む前に session_start() 済みであること
 */

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!current_user()) {
        redirect('login.php');
    }
}

function require_admin() {
    require_login();
    if (current_user()['role'] !== 'admin') {
        http_response_code(403);
        die('この操作には管理者権限が必要です。<a href="cases.php">一覧に戻る</a>');
    }
}

function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function login_user(array $userRow) {
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'username' => $userRow['username'],
        'display_name' => $userRow['display_name'],
        'role' => $userRow['role'],
    ];
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
