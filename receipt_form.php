<?php
$activePage = 'receipts';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();

$receiptId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $receiptId > 0;
$errors = [];

$receipt = [
    'customer_id' => $_GET['customer_id'] ?? '',
    'case_id' => $_GET['case_id'] ?? '',
    'issue_date' => date('Y-m-d'),
    'amount' => '',
    'description' => '',
    'notes' => '',
];

// 案件IDが指定された場合、その案件の顧客・金額・件名を初期値として引き継ぐ
if (!$isEdit && !empty($_GET['case_id'])) {
    $cStmt = $pdo->prepare('SELECT * FROM cases WHERE id = ?');
    $cStmt->execute([(int)$_GET['case_id']]);
    $caseRow = $cStmt->fetch();
    if ($caseRow) {
        $receipt['customer_id'] = $caseRow['customer_id'];
        $receipt['description'] = $caseRow['title'];
        if ($caseRow['amount'] !== null) {
            $receipt['amount'] = $caseRow['amount'];
        }
    }
}

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM receipts WHERE id = ?');
    $stmt->execute([$receiptId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定された領収書が見つかりません');
        redirect('receipts.php');
    }
    $receipt = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        require_admin();
        $pdo->prepare('DELETE FROM receipts WHERE id=?')->execute([$receiptId]);
        flash_set('success', '領収書を削除しました');
        redirect('receipts.php');
    }

    if ($action === 'save') {
        $receipt = [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'case_id' => $_POST['case_id'] !== '' ? (int)$_POST['case_id'] : null,
            'issue_date' => $_POST['issue_date'] ?: date('Y-m-d'),
            'amount' => (float)($_POST['amount'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        if (!$receipt['customer_id'] || $receipt['amount'] <= 0) {
            $errors[] = '顧客と金額（0より大きい値）は必須です';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE receipts SET customer_id=?, case_id=?, issue_date=?, amount=?, description=?, notes=? WHERE id=?'
                );
                $stmt->execute([
                    $receipt['customer_id'], $receipt['case_id'], $receipt['issue_date'],
                    $receipt['amount'], $receipt['description'], $receipt['notes'], $receiptId
                ]);
                flash_set('success', '領収書を更新しました');
                redirect('receipt_form.php?id=' . $receiptId);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO receipts (customer_id, case_id, issue_date, amount, description, notes, created_by) VALUES (?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $receipt['customer_id'], $receipt['case_id'], $receipt['issue_date'],
                    $receipt['amount'], $receipt['description'], $receipt['notes'], $me['id']
                ]);
                $newId = (int)$pdo->lastInsertId();
                $recNumber = 'REC-' . date('Y') . '-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE receipts SET receipt_number=? WHERE id=?')->execute([$recNumber, $newId]);
                flash_set('success', '領収書を作成しました');
                redirect('receipt_form.php?id=' . $newId);
            }
        }
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$cases = $pdo->query('SELECT id, title, amount FROM cases ORDER BY title')->fetchAll();
$pageTitle = $isEdit ? '領収書編集' : '新規領収書';
?>

<a href="receipts.php" class="back-link">← 領収書一覧に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? '領収書編集 ' . e($receipt['receipt_number'] ?? '') : '新規領収書' ?></h2>
  <?php if ($isEdit): ?>
    <a href="receipt_pdf.php?id=<?= (int)$receiptId ?>" target="_blank" class="btn secondary">PDFを表示</a>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$receiptId ?>"><?php endif; ?>

    <div class="field">
      <label>顧客 *</label>
      <select name="customer_id" required>
        <option value="">選択してください</option>
        <?php foreach ($customers as $cu): ?>
          <option value="<?= (int)$cu['id'] ?>" <?= (int)$receipt['customer_id'] === (int)$cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>関連案件（任意）</label>
      <select name="case_id" id="caseSelect">
        <option value="">なし</option>
        <?php foreach ($cases as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
            data-title="<?= e($c['title']) ?>" data-amount="<?= e($c['amount']) ?>"
            <?= (int)($receipt['case_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-row">
      <div class="field">
        <label>発行日</label>
        <input type="date" name="issue_date" value="<?= e($receipt['issue_date']) ?>">
      </div>
      <div class="field">
        <label>金額（税込） *</label>
        <input type="number" step="1" name="amount" id="amountInput" value="<?= e($receipt['amount']) ?>" required>
      </div>
    </div>
    <div class="field">
      <label>但し書き</label>
      <input type="text" name="description" id="descriptionInput" value="<?= e($receipt['description']) ?>" placeholder="例: コンサルティング費用として">
    </div>
    <div class="field">
      <label>備考</label>
      <textarea name="notes" rows="2"><?= e($receipt['notes']) ?></textarea>
    </div>

    <div class="form-actions">
      <?php if ($isEdit && is_admin()): ?>
        <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('この領収書を削除しますか？');">削除</button>
      <?php endif; ?>
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<script>
// 案件を選択したとき、金額・但し書きが空欄のままなら自動入力する
document.getElementById('caseSelect').addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  if (!opt || !opt.value) return;
  const amountInput = document.getElementById('amountInput');
  const descInput = document.getElementById('descriptionInput');
  if (!amountInput.value && opt.dataset.amount) {
    amountInput.value = opt.dataset.amount;
  }
  if (!descInput.value.trim()) {
    descInput.value = opt.dataset.title || '';
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
