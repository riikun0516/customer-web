<?php
$activePage = 'account';
$pageTitle = 'アカウント設定';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();

$profileErrors = [];
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $displayName = trim($_POST['display_name'] ?? '');
        if (!$displayName) {
            $profileErrors[] = '表示名を入力してください';
        } else {
            $pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$displayName, $me['id']]);
            $_SESSION['user']['display_name'] = $displayName;
            flash_set('success', '表示名を更新しました');
            redirect('account.php');
        }
    } elseif ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$me['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $passwordErrors[] = '現在のパスワードが正しくありません';
        } elseif (strlen($new) < 8) {
            $passwordErrors[] = '新しいパスワードは8文字以上で入力してください';
        } elseif ($new !== $confirm) {
            $passwordErrors[] = '新しいパスワード（確認）が一致しません';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $me['id']]);
            flash_set('success', 'パスワードを変更しました');
            redirect('account.php');
        }
    }
}
?>

<div class="page-header">
  <h2>アカウント設定</h2>
</div>

<div class="detail-grid">
  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">プロフィール</h3>
    <?php foreach ($profileErrors as $err): ?>
      <div class="msg error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">
      <div class="field">
        <label>ユーザー名</label>
        <input type="text" value="<?= e($me['username']) ?>" readonly>
        <div class="hint">ユーザー名は変更できません</div>
      </div>
      <div class="field">
        <label>表示名</label>
        <input type="text" name="display_name" value="<?= e($me['display_name']) ?>" required>
      </div>
      <div class="field">
        <label>権限</label>
        <input type="text" value="<?= $me['role'] === 'admin' ? '管理者' : '一般ユーザー' ?>" readonly>
      </div>
      <div class="form-actions">
        <span class="spacer"></span>
        <button type="submit" class="btn">表示名を保存</button>
      </div>
    </form>
  </div>

  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">パスワード変更</h3>
    <?php foreach ($passwordErrors as $err): ?>
      <div class="msg error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="field">
        <label>現在のパスワード</label>
        <input type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label>新しいパスワード</label>
        <input type="password" name="new_password" required autocomplete="new-password">
        <div class="hint">8文字以上で入力してください</div>
      </div>
      <div class="field">
        <label>新しいパスワード（確認）</label>
        <input type="password" name="new_password_confirm" required autocomplete="new-password">
      </div>
      <div class="form-actions">
        <span class="spacer"></span>
        <button type="submit" class="btn">パスワードを変更</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
