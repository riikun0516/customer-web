<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('cases.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'ユーザー名とパスワードを入力してください';
    } else {
        $stmt = get_pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'ユーザー名またはパスワードが違います';
        } elseif (!$user['is_active']) {
            $error = 'このアカウントは無効化されています';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'ユーザー名またはパスワードが違います';
        } else {
            login_user($user);
            redirect('cases.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログイン - COBIS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card narrow">
    <h1 class="title" style="font-size:26px; letter-spacing:0.5px;">COBIS</h1>
    <p style="font-size:11px; color:var(--text-sub); letter-spacing:0.5px; margin:0 0 18px;">Connect Of Business System</p>
    <p class="subtitle">アカウント情報でログインしてください</p>

    <?php if ($error): ?>
      <div class="msg error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>ユーザー名</label>
        <input type="text" name="username" autofocus required>
      </div>
      <div class="field">
        <label>パスワード</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn full">ログイン</button>
    </form>

    <p style="text-align:center; margin-top:18px;">
      <a href="setup.php" style="font-size:12px; color:var(--text-sub);">DB接続設定を変更する</a>
    </p>
  </div>
</div>
</body>
</html>
