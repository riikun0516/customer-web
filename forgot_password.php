<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf_helper.php'; // get_company_settings() を使うため
require_once __DIR__ . '/includes/mailer.php';

if (current_user()) {
    redirect('cases.php');
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');

    if ($username !== '') {
        try {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // ユーザーが存在するか・メール送信が成功したかに関わらず、
            // 表示するメッセージは常に同じにする（ユーザー名の存在を外部に漏らさないため）
            if ($user && $user['is_active'] && !empty($user['email'])) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expires = date('Y-m-d H:i:s', time() + 30 * 60);
                $pdo->prepare('UPDATE users SET reset_token_hash=?, reset_token_expires=? WHERE id=?')
                    ->execute([$tokenHash, $expires, $user['id']]);

                $company = get_company_settings($pdo);
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                    . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                $resetUrl = rtrim($baseUrl, '/') . '/reset_password.php?token=' . $rawToken;

                $subject = '【COBIS】パスワード再設定のご案内';
                $body = $user['display_name'] . " 様\n\n"
                    . "COBISのパスワード再設定のリクエストを受け付けました。\n"
                    . "以下のリンクから、新しいパスワードを設定してください（有効期限: 30分）。\n\n"
                    . $resetUrl . "\n\n"
                    . "このリクエストに心当たりがない場合は、このメールを無視してください。\n";

                try {
                    smtp_send_mail($user['email'], $user['display_name'], $subject, $body, $company);
                } catch (Exception $mailEx) {
                    // メール送信の成否はユーザーに開示しない（管理者向けにログだけ残す）
                    error_log('[COBIS] パスワード再設定メール送信失敗: ' . $mailEx->getMessage());
                }
            }
        } catch (Exception $ex) {
            error_log('[COBIS] パスワード再設定処理エラー: ' . $ex->getMessage());
        }
    }

    $submitted = true;
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
    <h1 class="title" style="font-size:20px;">パスワード再設定</h1>

    <?php if ($submitted): ?>
      <div class="msg success show">
        アカウントが存在し、メールアドレスが登録されている場合、パスワード再設定の手順を記載したメールを送信しました。しばらくしても届かない場合は、管理者にお問い合わせください。
      </div>
      <p style="text-align:center; margin-top:18px;">
        <a href="login.php" style="font-size:12px; color:var(--text-sub);">ログイン画面に戻る</a>
      </p>
    <?php else: ?>
      <p class="subtitle">登録済みのユーザー名を入力してください。ご本人確認のためのメールをお送りします。</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label>ユーザー名</label>
          <input type="text" name="username" autofocus required>
        </div>
        <button type="submit" class="btn full">再設定メールを送信</button>
      </form>
      <p style="text-align:center; margin-top:18px;">
        <a href="login.php" style="font-size:12px; color:var(--text-sub);">ログイン画面に戻る</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
