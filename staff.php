<?php
$activePage = 'staff';
$pageTitle = 'スタッフ管理';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$staffList = $pdo->query('SELECT * FROM staff ORDER BY is_active DESC, id')->fetchAll();
?>

<div class="page-header">
  <h2>スタッフ管理</h2>
  <a href="staff_form.php" class="btn">＋ 新規スタッフ</a>
</div>
<p class="hint" style="margin-bottom:16px;">振込先の銀行口座など、給与支払いに必要な情報を管理します（管理者のみ閲覧・編集できます）。</p>

<?php if (empty($staffList)): ?>
  <div class="empty-state">スタッフが登録されていません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>氏名</th>
      <th>役職</th>
      <th>連絡先</th>
      <th>振込先</th>
      <th>状態</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($staffList as $s): ?>
    <tr>
      <td><a href="staff_form.php?id=<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a><?php if ($s['name_kana']): ?><div class="hint"><?= e($s['name_kana']) ?></div><?php endif; ?></td>
      <td><?= e($s['position'] ?: '-') ?></td>
      <td><?= e($s['email'] ?: '-') ?><?php if ($s['phone']): ?><div class="hint"><?= e($s['phone']) ?></div><?php endif; ?></td>
      <td>
        <?php if ($s['bank_name']): ?>
          <?= e($s['bank_name']) ?> <?= e($s['branch_name']) ?><br>
          <span class="hint"><?= e($s['account_type']) ?> <?= e($s['account_number']) ?></span>
        <?php else: ?>
          <span class="hint">未登録</span>
        <?php endif; ?>
      </td>
      <td><?php if (!$s['is_active']): ?><span class="badge inactive">無効</span><?php else: ?>有効<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
