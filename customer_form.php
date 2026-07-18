<?php
$activePage = 'customers';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $customerId > 0;
$errors = [];

$customer = ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'address' => '', 'memo' => ''];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定された顧客が見つかりません');
        redirect('customers.php');
    }
    $customer = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        require_admin();
        $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$customerId]);
        flash_set('success', '顧客を削除しました');
        redirect('customers.php');
    }

    if ($action === 'save') {
        $customer = [
            'name' => trim($_POST['name'] ?? ''),
            'company' => trim($_POST['company'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'memo' => trim($_POST['memo'] ?? ''),
        ];
        if (!$customer['name']) {
            $errors[] = '顧客名は必須です';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE customers SET name=?, company=?, email=?, phone=?, address=?, memo=? WHERE id=?');
                $stmt->execute([$customer['name'], $customer['company'], $customer['email'], $customer['phone'], $customer['address'], $customer['memo'], $customerId]);
                flash_set('success', '顧客情報を更新しました');
                redirect('customer_form.php?id=' . $customerId);
            } else {
                $stmt = $pdo->prepare('INSERT INTO customers (name, company, email, phone, address, memo, created_by) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$customer['name'], $customer['company'], $customer['email'], $customer['phone'], $customer['address'], $customer['memo'], $me['id']]);
                $newId = $pdo->lastInsertId();
                flash_set('success', '顧客を登録しました');
                redirect('customer_form.php?id=' . $newId);
            }
        }
    }
}

$pageTitle = $isEdit ? '顧客編集' : '新規顧客';

// この顧客に紐づく案件（編集時のみ）
$relatedCases = [];
if ($isEdit) {
    $stmt = $pdo->prepare(
        'SELECT c.*, u.display_name AS assigned_name FROM cases c
         LEFT JOIN users u ON u.id = c.assigned_user_id
         WHERE c.customer_id = ? ORDER BY c.updated_at DESC'
    );
    $stmt->execute([$customerId]);
    $relatedCases = $stmt->fetchAll();
}
?>

<a href="customers.php" class="back-link">← 顧客一覧に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? '顧客編集' : '新規顧客' ?></h2>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="detail-grid">
  <div class="form-card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$customerId ?>"><?php endif; ?>

      <div class="field">
        <label>顧客名 *</label>
        <input type="text" name="name" value="<?= e($customer['name']) ?>" required>
      </div>
      <div class="field">
        <label>会社名</label>
        <input type="text" name="company" value="<?= e($customer['company']) ?>">
      </div>
      <div class="field-row">
        <div class="field">
          <label>メール</label>
          <input type="email" name="email" value="<?= e($customer['email']) ?>">
        </div>
        <div class="field">
          <label>電話番号</label>
          <input type="text" name="phone" value="<?= e($customer['phone']) ?>">
        </div>
      </div>
      <div class="field">
        <label>住所</label>
        <input type="text" name="address" value="<?= e($customer['address']) ?>">
      </div>
      <div class="field">
        <label>メモ</label>
        <textarea name="memo" rows="3"><?= e($customer['memo']) ?></textarea>
      </div>

      <div class="form-actions">
        <?php if ($isEdit && is_admin()): ?>
          <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('この顧客を削除しますか？関連する案件も削除されます。');">削除</button>
        <?php endif; ?>
        <span class="spacer"></span>
        <button type="submit" class="btn">保存</button>
      </div>
    </form>
  </div>

  <?php if ($isEdit): ?>
  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">この顧客の案件</h3>
    <?php if (empty($relatedCases)): ?>
      <div class="hint">案件はまだありません</div>
    <?php else: ?>
      <?php foreach ($relatedCases as $rc): ?>
        <div class="activity-item">
          <div><a href="case_form.php?id=<?= (int)$rc['id'] ?>"><?= e($rc['title']) ?></a></div>
          <div class="meta">
            <span class="<?= status_badge_class($rc['status']) ?>"><?= e($rc['status']) ?></span>
            ・担当: <?= e($rc['assigned_name'] ?? '未割当') ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <a href="case_form.php?customer_id=<?= (int)$customerId ?>" class="btn secondary small full" style="margin-top:8px;">＋ この顧客の案件を追加</a>
  </div>

  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">見積書・請求書・領収書</h3>
    <a href="quote_form.php?customer_id=<?= (int)$customerId ?>" class="btn secondary small full" style="margin-bottom:8px;">＋ 見積書を作成</a>
    <a href="invoice_form.php?customer_id=<?= (int)$customerId ?>" class="btn secondary small full" style="margin-bottom:8px;">＋ 請求書を発行</a>
    <a href="receipt_form.php?customer_id=<?= (int)$customerId ?>" class="btn secondary small full">＋ 領収書を発行</a>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
