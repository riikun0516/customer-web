<?php
$activePage = 'contracts';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();
$companySettings = get_company_settings($pdo);

$contractId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $contractId > 0;
$errors = [];

$contract = [
    'customer_id' => $_GET['customer_id'] ?? '',
    'case_id' => $_GET['case_id'] ?? '',
    'title' => '',
    'status' => '下書き',
    'start_date' => date('Y-m-d'),
    'end_date' => '',
    'amount' => '',
    'payment_terms' => '',
    'body' => $companySettings['contract_template'] ?? '',
    'notes' => '',
];

// 案件IDが指定された場合、その案件の顧客・件名・金額を初期値として引き継ぐ
if (!$isEdit && !empty($_GET['case_id'])) {
    $cStmt = $pdo->prepare('SELECT * FROM cases WHERE id = ?');
    $cStmt->execute([(int)$_GET['case_id']]);
    $caseRow = $cStmt->fetch();
    if ($caseRow) {
        $contract['customer_id'] = $caseRow['customer_id'];
        $contract['title'] = $caseRow['title'] . ' に関する契約';
        if ($caseRow['amount'] !== null) {
            $contract['amount'] = $caseRow['amount'];
        }
    }
}

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
    $stmt->execute([$contractId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定された契約書が見つかりません');
        redirect('contracts.php');
    }
    $contract = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        require_admin();
        $pdo->prepare('DELETE FROM contracts WHERE id=?')->execute([$contractId]);
        flash_set('success', '契約書を削除しました');
        redirect('contracts.php');
    }

    if ($action === 'save') {
        $contract = [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'case_id' => $_POST['case_id'] !== '' ? (int)$_POST['case_id'] : null,
            'title' => trim($_POST['title'] ?? ''),
            'status' => $_POST['status'] ?? '下書き',
            'start_date' => $_POST['start_date'] !== '' ? $_POST['start_date'] : null,
            'end_date' => $_POST['end_date'] !== '' ? $_POST['end_date'] : null,
            'amount' => $_POST['amount'] !== '' ? (float)$_POST['amount'] : null,
            'payment_terms' => trim($_POST['payment_terms'] ?? ''),
            'body' => $_POST['body'] ?? '',
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        if (!$contract['customer_id'] || !$contract['title']) {
            $errors[] = '顧客と件名は必須です';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE contracts SET customer_id=?, case_id=?, title=?, status=?, start_date=?, end_date=?, amount=?, payment_terms=?, body=?, notes=? WHERE id=?'
                );
                $stmt->execute([
                    $contract['customer_id'], $contract['case_id'], $contract['title'], $contract['status'],
                    $contract['start_date'], $contract['end_date'], $contract['amount'], $contract['payment_terms'],
                    $contract['body'], $contract['notes'], $contractId
                ]);
                flash_set('success', '契約書を更新しました');
                redirect('contract_form.php?id=' . $contractId);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO contracts (customer_id, case_id, title, status, start_date, end_date, amount, payment_terms, body, notes, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $contract['customer_id'], $contract['case_id'], $contract['title'], $contract['status'],
                    $contract['start_date'], $contract['end_date'], $contract['amount'], $contract['payment_terms'],
                    $contract['body'], $contract['notes'], $me['id']
                ]);
                $newId = (int)$pdo->lastInsertId();
                $contractNumber = 'CON-' . date('Y') . '-' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE contracts SET contract_number=? WHERE id=?')->execute([$contractNumber, $newId]);
                flash_set('success', '契約書を作成しました');
                redirect('contract_form.php?id=' . $newId);
            }
        }
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$cases = $pdo->query('SELECT id, title, amount FROM cases ORDER BY title')->fetchAll();
$pageTitle = $isEdit ? '契約書編集' : '新規契約書';
?>

<a href="contracts.php" class="back-link">← 契約書一覧に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? '契約書編集 ' . e($contract['contract_number'] ?? '') : '新規契約書' ?></h2>
  <?php if ($isEdit): ?>
    <a href="contract_pdf.php?id=<?= (int)$contractId ?>" target="_blank" class="btn secondary">PDFを表示</a>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:820px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$contractId ?>"><?php endif; ?>

    <div class="field-row">
      <div class="field">
        <label>顧客 *</label>
        <select name="customer_id" required>
          <option value="">選択してください</option>
          <?php foreach ($customers as $cu): ?>
            <option value="<?= (int)$cu['id'] ?>" <?= (int)$contract['customer_id'] === (int)$cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>関連案件（任意）</label>
        <select name="case_id" id="caseSelect">
          <option value="">なし</option>
          <?php foreach ($cases as $c): ?>
            <option value="<?= (int)$c['id'] ?>"
              data-title="<?= e($c['title']) ?> に関する契約" data-amount="<?= e($c['amount']) ?>"
              <?= (int)($contract['case_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label>契約件名 *</label>
      <input type="text" name="title" id="titleInput" value="<?= e($contract['title']) ?>" required placeholder="例: Webサイト制作業務委託契約">
    </div>

    <div class="field-row">
      <div class="field">
        <label>契約開始日</label>
        <input type="date" name="start_date" value="<?= e($contract['start_date']) ?>">
      </div>
      <div class="field">
        <label>契約終了日</label>
        <input type="date" name="end_date" value="<?= e($contract['end_date']) ?>">
      </div>
      <div class="field">
        <label>ステータス</label>
        <select name="status">
          <?php foreach (['下書き', '締結済み', '終了'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $contract['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label>契約金額（任意）</label>
        <input type="number" step="1" name="amount" id="amountInput" value="<?= e($contract['amount']) ?>">
      </div>
      <div class="field">
        <label>支払条件</label>
        <input type="text" name="payment_terms" value="<?= e($contract['payment_terms']) ?>" placeholder="例: 月末締め翌月末払い">
      </div>
    </div>

    <div class="field">
      <label>契約条項本文</label>
      <textarea name="body" rows="16" style="font-family: 'Hiragino Sans', 'Yu Gothic', monospace; font-size:13px;"><?= e($contract['body']) ?></textarea>
      <div class="hint">PDFにはこの内容がそのまま印字されます。第1条、第2条…のように条文形式で記載してください。既定の文言は自社情報設定の「契約書テンプレート」で編集できます。</div>
    </div>

    <div class="field">
      <label>備考（PDFには印字されません）</label>
      <textarea name="notes" rows="2"><?= e($contract['notes']) ?></textarea>
    </div>

    <div class="form-actions">
      <?php if ($isEdit && is_admin()): ?>
        <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('この契約書を削除しますか？');">削除</button>
      <?php endif; ?>
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<script>
document.getElementById('caseSelect').addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  if (!opt || !opt.value) return;
  const titleInput = document.getElementById('titleInput');
  const amountInput = document.getElementById('amountInput');
  if (!titleInput.value.trim()) titleInput.value = opt.dataset.title || '';
  if (!amountInput.value && opt.dataset.amount) amountInput.value = opt.dataset.amount;
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
