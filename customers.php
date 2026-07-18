<?php
$activePage = 'customers';
$pageTitle = '顧客一覧';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$keyword = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM customers';
$params = [];
if ($keyword !== '') {
    $sql .= ' WHERE name LIKE ? OR company LIKE ? OR email LIKE ?';
    $like = "%$keyword%";
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY updated_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="page-header">
  <h2>顧客一覧</h2>
  <a href="customer_form.php" class="btn">＋ 新規顧客</a>
</div>

<div class="toolbar">
  <form method="get">
    <input type="text" name="q" placeholder="名前・会社名・メールで検索" value="<?= e($keyword) ?>">
    <button type="submit" class="btn secondary small">検索</button>
  </form>
</div>

<?php if (empty($customers)): ?>
  <div class="empty-state">顧客が登録されていません</div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>顧客名</th>
      <th>会社名</th>
      <th>メール</th>
      <th>電話番号</th>
      <th>更新日時</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><a href="customer_form.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></td>
      <td><?= e($c['company'] ?? '-') ?></td>
      <td><?= e($c['email'] ?? '-') ?></td>
      <td><?= e($c['phone'] ?? '-') ?></td>
      <td><?= e($c['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
