<?php
$activePage = 'users';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$me = current_user();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $userId > 0;
$errors = [];

$user = ['username' => '', 'display_name' => '', 'role' => 'general', 'is_active' => 1];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定されたユーザーが見つかりません');
        redirect('users.php');
    }
    $user = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        if ($userId === (int)$me['id']) {
            $errors[] = '自分自身は削除できません';
        } else {
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
            flash_set('success', 'ユーザーを削除しました');
            redirect('users.php');
        }
    }

    if ($action === 'save') {
        $username = trim($_POST['username'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $role = $_POST['role'] ?? 'general';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');

        $user = ['username' => $username, 'display_name' => $displayName, 'role' => $role, 'is_active' => $isActive];

        if (!$username || !$displayName) {
            $errors[] = 'ユーザー名と表示名は必須です';
        } elseif (!$isEdit && !$password) {
            $errors[] = '新規作成時はパスワードが必須です';
        } else {
            try {
                if ($isEdit) {
                    if ($password) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE users SET display_name=?, role=?, is_active=?, password_hash=? WHERE id=?');
                        $stmt->execute([$displayName, $role, $isActive, $hash, $userId]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET display_name=?, role=?, is_active=? WHERE id=?');
                        $stmt->execute([$displayName, $role, $isActive, $userId]);
                    }
                    flash_set('success', 'ユーザー情報を更新しました');
                    redirect('user_form.php?id=' . $userId);
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?,?,?,?,?)');
                    $stmt->execute([$username, $hash, $displayName, $role, $isActive]);
                    $newId = $pdo->lastInsertId();
                    flash_set('success', 'ユーザーを作成しました');
                    redirect('user_form.php?id=' . $newId);
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $errors[] = 'そのユーザー名は既に使用されています';
                } else {
                    $errors[] = '保存に失敗しました: ' . $ex->getMessage();
                }
            }
        }
    }
}

$pageTitle = $isEdit ? 'ユーザー編集' : '新規ユーザー';
?>

<a href="users.php" class="back-link">← ユーザー管理に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? 'ユーザー編集' : '新規ユーザー' ?></h2>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$userId ?>"><?php endif; ?>

    <div class="field">
      <label>ユーザー名 *</label>
      <input type="text" name="username" value="<?= e($user['username']) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
      <?php if ($isEdit): ?><div class="hint">ユーザー名は変更できません</div><?php endif; ?>
    </div>
    <div class="field">
      <label>表示名 *</label>
      <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" required>
    </div>
    <div class="field">
      <label>パスワード <?php if ($isEdit): ?><span class="hint">（変更する場合のみ入力）</span><?php endif; ?></label>
      <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>>
    </div>
    <div class="field">
      <label>権限</label>
      <select name="role">
        <option value="general" <?= $user['role'] === 'general' ? 'selected' : '' ?>>一般ユーザー</option>
        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>管理者</option>
      </select>
    </div>
    <div class="field" style="display:flex; align-items:center; gap:8px;">
      <input type="checkbox" name="is_active" value="1" id="isActiveCheck" style="width:auto;" <?= $user['is_active'] ? 'checked' : '' ?>>
      <label for="isActiveCheck" style="margin:0;">アカウントを有効にする</label>
    </div>

    <div class="form-actions">
      <?php if ($isEdit): ?>
        <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('このユーザーを削除しますか？');">削除</button>
      <?php endif; ?>
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
