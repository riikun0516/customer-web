<?php
$activePage = 'users';
$pageTitle = 'ユーザー管理';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$users = $pdo->query('SELECT id, username, display_name, role, is_active, created_at FROM users ORDER BY id')->fetchAll();
?>

<div class="page-header">
  <h2>ユーザー管理</h2>
  <a href="user_form.php" class="btn">＋ 新規ユーザー</a>
</div>

<table>
  <thead>
    <tr>
      <th>ユーザー名</th>
      <th>表示名</th>
      <th>権限</th>
      <th>状態</th>
      <th>登録日</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><a href="user_form.php?id=<?= (int)$u['id'] ?>"><?= e($u['username']) ?></a></td>
      <td><?= e($u['display_name']) ?></td>
      <td><span class="badge role-<?= e($u['role']) ?>"><?= $u['role'] === 'admin' ? '管理者' : '一般ユーザー' ?></span></td>
      <td><?php if (!$u['is_active']): ?><span class="badge inactive">無効</span><?php else: ?>有効<?php endif; ?></td>
      <td><?= e($u['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
