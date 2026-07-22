<?php
$activePage = 'contracts';
$pageTitle = '契約書一覧';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();

$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT c.*, cu.name AS customer_name
        FROM contracts c
        LEFT JOIN customers cu ON cu.id = c.customer_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND c.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY c.start_date DESC, c.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();
?>

<div class="page-header">
  <h2>契約書一覧</h2>
  <a href="contract_form.php" class="btn">＋ 新規契約書</a>
</div>

<div class="toolbar">
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">すべてのステータス</option>
      <?php foreach (['下書き', '締結済み', '終了'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($contracts)): ?>
  <div class="empty-state">契約書がありません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>契約書番号</th>
      <th>件名</th>
      <th>顧客</th>
      <th>契約期間</th>
      <th>金額</th>
      <th>ステータス</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($contracts as $c): ?>
    <tr>
      <td><a href="contract_form.php?id=<?= (int)$c['id'] ?>"><?= e($c['contract_number'] ?? '(下書き)') ?></a></td>
      <td><?= e($c['title']) ?></td>
      <td><?= e($c['customer_name'] ?? '-') ?></td>
      <td><?= e($c['start_date'] ?: '-') ?> 〜 <?= e($c['end_date'] ?: '-') ?></td>
      <td><?= $c['amount'] !== null ? money($c['amount']) : '-' ?></td>
      <td>
        <?php
          $badgeMap = ['下書き' => '未着手', '締結済み' => '進行中', '終了' => '完了'];
          $badgeClass = $badgeMap[$c['status']] ?? '未着手';
        ?>
        <span class="badge status-<?= e($badgeClass) ?>"><?= e($c['status']) ?></span>
      </td>
      <td><a href="contract_pdf.php?id=<?= (int)$c['id'] ?>" target="_blank" class="btn secondary small">PDF</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
