<?php
$activePage = 'invoices';
$pageTitle = '請求書一覧';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();

$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT i.*, cu.name AS customer_name
        FROM invoices i
        LEFT JOIN customers cu ON cu.id = i.customer_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND i.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY i.issue_date DESC, i.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>

<div class="page-header">
  <h2>請求書一覧</h2>
  <a href="invoice_form.php" class="btn">＋ 新規請求書</a>
</div>

<div class="toolbar">
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">すべてのステータス</option>
      <?php foreach (['未送付', '送付済み', '支払済み'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($invoices)): ?>
  <div class="empty-state">請求書がありません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>請求書番号</th>
      <th>顧客</th>
      <th>発行日</th>
      <th>支払期限</th>
      <th>金額（税込）</th>
      <th>ステータス</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><a href="invoice_form.php?id=<?= (int)$inv['id'] ?>"><?= e($inv['invoice_number'] ?? '(下書き)') ?></a></td>
      <td><?= e($inv['customer_name'] ?? '-') ?></td>
      <td><?= e($inv['issue_date']) ?></td>
      <td><?= e($inv['due_date'] ?? '-') ?></td>
      <td><?= money($inv['total_amount']) ?></td>
      <td><span class="badge status-<?= $inv['status'] === '支払済み' ? '完了' : ($inv['status'] === '送付済み' ? '進行中' : '未着手') ?>"><?= e($inv['status']) ?></span></td>
      <td><a href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank" class="btn secondary small">PDF</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
