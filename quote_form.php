<?php
$activePage = 'quotes';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();
$companySettings = get_company_settings($pdo);

$quoteId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $quoteId > 0;
$errors = [];

$quote = [
    'customer_id' => $_GET['customer_id'] ?? '',
    'case_id' => $_GET['case_id'] ?? '',
    'status' => '下書き',
    'issue_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'tax_rate' => $companySettings['default_tax_rate'] ?? 10,
    'notes' => $companySettings['invoice_note'] ?? '',
];
$items = [['name' => '', 'quantity' => 1, 'unit_price' => 0]];

// 案件IDが指定された場合、その案件の顧客・金額を初期値として引き継ぐ
if (!$isEdit && !empty($_GET['case_id'])) {
    $cStmt = $pdo->prepare('SELECT * FROM cases WHERE id = ?');
    $cStmt->execute([(int)$_GET['case_id']]);
    $caseRow = $cStmt->fetch();
    if ($caseRow) {
        $quote['customer_id'] = $caseRow['customer_id'];
        if ($caseRow['amount'] !== null) {
            $items = [['name' => $caseRow['title'], 'quantity' => 1, 'unit_price' => $caseRow['amount']]];
        }
    }
}

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = ?');
    $stmt->execute([$quoteId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定された見積書が見つかりません');
        redirect('quotes.php');
    }
    $quote = $existing;

    $stmt = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order, id');
    $stmt->execute([$quoteId]);
    $loadedItems = $stmt->fetchAll();
    if ($loadedItems) $items = $loadedItems;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        require_admin();
        $pdo->prepare('DELETE FROM quotes WHERE id=?')->execute([$quoteId]);
        flash_set('success', '見積書を削除しました');
        redirect('quotes.php');
    }

    if ($action === 'save') {
        $quote = [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'case_id' => $_POST['case_id'] !== '' ? (int)$_POST['case_id'] : null,
            'status' => $_POST['status'] ?? '下書き',
            'issue_date' => $_POST['issue_date'] ?: date('Y-m-d'),
            'valid_until' => $_POST['valid_until'] !== '' ? $_POST['valid_until'] : null,
            'tax_rate' => (float)($_POST['tax_rate'] ?? 10),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        $itemNames = $_POST['item_name'] ?? [];
        $itemQtys = $_POST['item_qty'] ?? [];
        $itemPrices = $_POST['item_price'] ?? [];
        $items = [];
        $subtotal = 0.0;
        foreach ($itemNames as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            $qty = (float)($itemQtys[$i] ?? 0);
            $price = (float)($itemPrices[$i] ?? 0);
            $amount = round($qty * $price, 2);
            $subtotal += $amount;
            $items[] = ['name' => $name, 'quantity' => $qty, 'unit_price' => $price, 'amount' => $amount];
        }
        $taxAmount = round($subtotal * ($quote['tax_rate'] / 100), 0);
        $total = $subtotal + $taxAmount;

        if (!$quote['customer_id']) {
            $errors[] = '顧客は必須です';
        } elseif (empty($items)) {
            $errors[] = '明細を1件以上入力してください';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE quotes SET customer_id=?, case_id=?, status=?, issue_date=?, valid_until=?, tax_rate=?, subtotal=?, tax_amount=?, total_amount=?, notes=? WHERE id=?'
                );
                $stmt->execute([
                    $quote['customer_id'], $quote['case_id'], $quote['status'], $quote['issue_date'],
                    $quote['valid_until'], $quote['tax_rate'], $subtotal, $taxAmount, $total, $quote['notes'], $quoteId
                ]);
                $pdo->prepare('DELETE FROM quote_items WHERE quote_id=?')->execute([$quoteId]);
                $targetId = $quoteId;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO quotes (customer_id, case_id, status, issue_date, valid_until, tax_rate, subtotal, tax_amount, total_amount, notes, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $quote['customer_id'], $quote['case_id'], $quote['status'], $quote['issue_date'],
                    $quote['valid_until'], $quote['tax_rate'], $subtotal, $taxAmount, $total, $quote['notes'], $me['id']
                ]);
                $targetId = (int)$pdo->lastInsertId();
                $quoteNumber = 'QUO-' . date('Y') . '-' . str_pad($targetId, 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE quotes SET quote_number=? WHERE id=?')->execute([$quoteNumber, $targetId]);
            }

            $itemStmt = $pdo->prepare(
                'INSERT INTO quote_items (quote_id, name, quantity, unit_price, amount, sort_order) VALUES (?,?,?,?,?,?)'
            );
            foreach ($items as $idx => $it) {
                $itemStmt->execute([$targetId, $it['name'], $it['quantity'], $it['unit_price'], $it['amount'], $idx]);
            }

            flash_set('success', $isEdit ? '見積書を更新しました' : '見積書を作成しました');
            redirect('quote_form.php?id=' . $targetId);
        }
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$cases = $pdo->query('SELECT id, title, customer_id, amount FROM cases ORDER BY title')->fetchAll();

$pageTitle = $isEdit ? '見積書編集' : '新規見積書';
?>

<a href="quotes.php" class="back-link">← 見積書一覧に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? '見積書編集 ' . e($quote['quote_number'] ?? '') : '新規見積書' ?></h2>
  <?php if ($isEdit): ?>
    <a href="quote_pdf.php?id=<?= (int)$quoteId ?>" target="_blank" class="btn secondary">PDFを表示</a>
  <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:820px;">
  <form method="post" id="quoteForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$quoteId ?>"><?php endif; ?>

    <div class="field-row">
      <div class="field">
        <label>顧客 *</label>
        <select name="customer_id" required>
          <option value="">選択してください</option>
          <?php foreach ($customers as $cu): ?>
            <option value="<?= (int)$cu['id'] ?>" <?= (int)$quote['customer_id'] === (int)$cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?></option>
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
              <?= (int)($quote['case_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label>発行日</label>
        <input type="date" name="issue_date" value="<?= e($quote['issue_date']) ?>">
      </div>
      <div class="field">
        <label>有効期限</label>
        <input type="date" name="valid_until" value="<?= e($quote['valid_until']) ?>">
      </div>
      <div class="field">
        <label>ステータス</label>
        <select name="status">
          <?php foreach (['下書き', '送付済み', '受注', '失注'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $quote['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3 style="font-size:14px; margin:20px 0 10px;">明細</h3>
    <table id="itemsTable" style="margin-bottom:10px;">
      <thead>
        <tr>
          <th>品目</th>
          <th style="width:90px;">数量</th>
          <th style="width:120px;">単価</th>
          <th style="width:110px;">金額</th>
          <th style="width:40px;"></th>
        </tr>
      </thead>
      <tbody id="itemsBody">
        <?php foreach ($items as $it): ?>
        <tr class="item-row">
          <td><input type="text" name="item_name[]" value="<?= e($it['name']) ?>" placeholder="品目名"></td>
          <td><input type="number" step="0.01" name="item_qty[]" value="<?= e($it['quantity']) ?>" class="qty-input"></td>
          <td><input type="number" step="0.01" name="item_price[]" value="<?= e($it['unit_price']) ?>" class="price-input"></td>
          <td class="amount-cell">¥0</td>
          <td><button type="button" class="btn danger small remove-row">×</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="button" class="btn secondary small" id="addRowBtn">＋ 明細行を追加</button>

    <div style="margin-top:16px; display:flex; justify-content:flex-end;">
      <table style="width:280px;">
        <tr><td>小計</td><td style="text-align:right;" id="subtotalDisp">¥0</td></tr>
        <tr>
          <td>消費税
            <input type="number" step="0.01" name="tax_rate" id="taxRateInput" value="<?= e($quote['tax_rate']) ?>" style="width:60px; display:inline-block; padding:4px;">%
          </td>
          <td style="text-align:right;" id="taxDisp">¥0</td>
        </tr>
        <tr><td><strong>合計</strong></td><td style="text-align:right;"><strong id="totalDisp">¥0</strong></td></tr>
      </table>
    </div>

    <div class="field" style="margin-top:16px;">
      <label>備考</label>
      <textarea name="notes" rows="2"><?= e($quote['notes']) ?></textarea>
    </div>

    <div class="form-actions">
      <?php if ($isEdit && is_admin()): ?>
        <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('この見積書を削除しますか？');">削除</button>
      <?php endif; ?>
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<template id="rowTemplate">
  <tr class="item-row">
    <td><input type="text" name="item_name[]" placeholder="品目名"></td>
    <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty-input"></td>
    <td><input type="number" step="0.01" name="item_price[]" value="0" class="price-input"></td>
    <td class="amount-cell">¥0</td>
    <td><button type="button" class="btn danger small remove-row">×</button></td>
  </tr>
</template>

<script>
function recalc() {
  let subtotal = 0;
  document.querySelectorAll('#itemsBody .item-row').forEach(row => {
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    const amount = qty * price;
    row.querySelector('.amount-cell').textContent = '¥' + amount.toLocaleString();
    subtotal += amount;
  });
  const taxRate = parseFloat(document.getElementById('taxRateInput').value) || 0;
  const tax = Math.round(subtotal * taxRate / 100);
  const total = subtotal + tax;
  document.getElementById('subtotalDisp').textContent = '¥' + subtotal.toLocaleString();
  document.getElementById('taxDisp').textContent = '¥' + tax.toLocaleString();
  document.getElementById('totalDisp').textContent = '¥' + total.toLocaleString();
}

document.getElementById('itemsBody').addEventListener('input', recalc);
document.getElementById('taxRateInput').addEventListener('input', recalc);

document.getElementById('addRowBtn').addEventListener('click', () => {
  const tpl = document.getElementById('rowTemplate');
  const clone = tpl.content.cloneNode(true);
  document.getElementById('itemsBody').appendChild(clone);
  recalc();
});

document.getElementById('itemsBody').addEventListener('click', (e) => {
  if (e.target.classList.contains('remove-row')) {
    const rows = document.querySelectorAll('#itemsBody .item-row');
    if (rows.length > 1) {
      e.target.closest('tr').remove();
      recalc();
    }
  }
});

// 案件を選択したとき、明細が空欄のままなら案件名・金額を自動入力する
document.getElementById('caseSelect').addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  if (!opt || !opt.value) return;
  const firstRow = document.querySelector('#itemsBody .item-row');
  if (!firstRow) return;
  const nameInput = firstRow.querySelector('input[name="item_name[]"]');
  if (nameInput.value.trim() !== '') return; // 既に入力済みなら上書きしない
  nameInput.value = opt.dataset.title || '';
  if (opt.dataset.amount) {
    firstRow.querySelector('.price-input').value = opt.dataset.amount;
  }
  recalc();
});

recalc();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
