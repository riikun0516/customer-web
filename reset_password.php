<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('cases.php');
}

$pdo = get_pdo();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$done = false;

function find_user_by_reset_token($pdo, $token) {
    if (!$token) return null;
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE reset_token_hash = ? AND reset_token_expires > NOW()');
    $stmt->execute([$hash]);
    return $stmt->fetch();
}

$user = find_user_by_reset_token($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user = find_user_by_reset_token($pdo, $token);

    if (!$user) {
        $errors[] = 'リンクが無効か、有効期限が切れています。もう一度パスワード再設定をリクエストしてください。';
    } else {
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        if (strlen($new) < 8) {
            $errors[] = '新しいパスワードは8文字以上で入力してください';
        } elseif ($new !== $confirm) {
            $errors[] = '新しいパスワード（確認）が一致しません';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=?, reset_token_hash=NULL, reset_token_expires=NULL WHERE id=?')
                ->execute([$hash, $user['id']]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>パスワード再設定 - COBIS</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card narrow">
    <h1 class="title" style="font-size:20px;">新しいパスワードの設定</h1>

    <?php if ($done): ?>
      <div class="msg success show">パスワードを変更しました。新しいパスワードでログインしてください。</div>
      <p style="text-align:center; margin-top:18px;">
        <a href="login.php" class="btn full" style="text-decoration:none; display:block; box-sizing:border-box;">ログイン画面へ</a>
      </p>
    <?php elseif (!$user): ?>
      <div class="msg error show">リンクが無効か、有効期限が切れています。</div>
      <p style="text-align:center; margin-top:18px;">
        <a href="forgot_password.php" style="font-size:12px; color:var(--text-sub);">もう一度リクエストする</a>
      </p>
    <?php else: ?>
      <p class="subtitle"><?= e($user['display_name']) ?> さんの新しいパスワードを設定してください。</p>
      <?php foreach ($errors as $err): ?>
        <div class="msg error"><?= e($err) ?></div>
      <?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label>新しいパスワード</label>
          <input type="password" name="new_password" required autocomplete="new-password">
          <div class="hint">8文字以上で入力してください</div>
        </div>
        <div class="field">
          <label>新しいパスワード（確認）</label>
          <input type="password" name="new_password_confirm" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn full">パスワードを変更</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
