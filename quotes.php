<?php
$activePage = 'quotes';
$pageTitle = '見積書一覧';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();

$statusFilter = trim($_GET['status'] ?? '');
$sql = "SELECT q.*, cu.name AS customer_name
        FROM quotes q
        LEFT JOIN customers cu ON cu.id = q.customer_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND q.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY q.issue_date DESC, q.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();
?>

<div class="page-header">
  <h2>見積書一覧</h2>
  <a href="quote_form.php" class="btn">＋ 新規見積書</a>
</div>

<div class="toolbar">
  <form method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">すべてのステータス</option>
      <?php foreach (['下書き', '送付済み', '受注', '失注'] as $s): ?>
        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($quotes)): ?>
  <div class="empty-state">見積書がありません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>見積書番号</th>
      <th>顧客</th>
      <th>発行日</th>
      <th>有効期限</th>
      <th>金額（税込）</th>
      <th>ステータス</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($quotes as $q): ?>
    <tr>
      <td><a href="quote_form.php?id=<?= (int)$q['id'] ?>"><?= e($q['quote_number'] ?? '(下書き)') ?></a></td>
      <td><?= e($q['customer_name'] ?? '-') ?></td>
      <td><?= e($q['issue_date']) ?></td>
      <td><?= e($q['valid_until'] ?? '-') ?></td>
      <td><?= money($q['total_amount']) ?></td>
      <td>
        <?php
          $badgeMap = ['下書き' => '未着手', '送付済み' => '進行中', '受注' => '完了', '失注' => '完了'];
          $badgeClass = $badgeMap[$q['status']] ?? '未着手';
        ?>
        <span class="badge status-<?= e($badgeClass) ?>"><?= e($q['status']) ?></span>
      </td>
      <td><a href="quote_pdf.php?id=<?= (int)$q['id'] ?>" target="_blank" class="btn secondary small">PDF</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
