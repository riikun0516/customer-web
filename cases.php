<?php
$activePage = 'cases';
$pageTitle = '案件一覧';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();

$statusFilter = trim($_GET['status'] ?? '');
$assignedFilter = trim($_GET['assigned'] ?? '');

$sql = "SELECT c.*, cu.name AS customer_name, u.display_name AS assigned_name
        FROM cases c
        LEFT JOIN customers cu ON cu.id = c.customer_id
        LEFT JOIN users u ON u.id = c.assigned_user_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND c.status = ?';
    $params[] = $statusFilter;
}
if ($assignedFilter !== '') {
    $sql .= ' AND c.assigned_user_id = ?';
    $params[] = $assignedFilter;
}
$sql .= ' ORDER BY c.updated_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll();

$users = $pdo->query('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name')->fetchAll();
?>

<div class="page-header">
  <h2>案件一覧</h2>
  <a href="case_form.php" class="btn">＋ 新規案件</a>
</div>

<div class="toolbar">
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">すべてのステータス</option>
      <?php foreach (['未着手', '進行中', '保留', '完了'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="assigned" onchange="this.form.submit()">
      <option value="">すべての担当者</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= (string)$assignedFilter === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['display_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($cases)): ?>
  <div class="empty-state">案件がありません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>案件名</th>
      <th>顧客</th>
      <th>担当者</th>
      <th>ステータス</th>
      <th>金額</th>
      <th>期限</th>
      <th>更新日時</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cases as $c): ?>
    <tr>
      <td><a href="case_form.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></td>
      <td><?= e($c['customer_name'] ?? '-') ?></td>
      <td><?= e($c['assigned_name'] ?? '未割当') ?></td>
      <td><span class="<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
      <td><?= $c['amount'] !== null ? money($c['amount']) : '-' ?></td>
      <td><?= e($c['due_date'] ?? '-') ?></td>
      <td><?= e($c['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
