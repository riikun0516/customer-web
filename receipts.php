<?php
$activePage = 'receipts';
$pageTitle = '領収書一覧';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$stmt = $pdo->query(
    "SELECT r.*, cu.name AS customer_name
     FROM receipts r LEFT JOIN customers cu ON cu.id = r.customer_id
     ORDER BY r.issue_date DESC, r.id DESC"
);
$receipts = $stmt->fetchAll();
?>

<div class="page-header">
  <h2>領収書一覧</h2>
  <a href="receipt_form.php" class="btn">＋ 新規領収書</a>
</div>

<?php if (empty($receipts)): ?>
  <div class="empty-state">領収書がありません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>領収書番号</th>
      <th>顧客</th>
      <th>発行日</th>
      <th>金額</th>
      <th>但し書き</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($receipts as $r): ?>
    <tr>
      <td><a href="receipt_form.php?id=<?= (int)$r['id'] ?>"><?= e($r['receipt_number'] ?? '(下書き)') ?></a></td>
      <td><?= e($r['customer_name'] ?? '-') ?></td>
      <td><?= e($r['issue_date']) ?></td>
      <td><?= money($r['amount']) ?></td>
      <td><?= e($r['description'] ?: '-') ?></td>
      <td><a href="receipt_pdf.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn secondary small">PDF</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
