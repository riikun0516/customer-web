<?php
$activePage = 'cases';
require_once __DIR__ . '/includes/header.php';

$pdo = get_pdo();
$me = current_user();

$caseId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $caseId > 0;
$errors = [];

$case = [
    'customer_id' => $_GET['customer_id'] ?? '',
    'title' => '',
    'status' => '未着手',
    'assigned_user_id' => '',
    'amount' => '',
    'description' => '',
    'due_date' => '',
];

function load_case($pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM cases WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

if ($isEdit) {
    $existing = load_case($pdo, $caseId);
    if (!$existing) {
        flash_set('error', '指定された案件が見つかりません');
        redirect('cases.php');
    }
    if (!is_admin() && (int)$existing['assigned_user_id'] !== (int)$me['id']) {
        http_response_code(403);
        die('この案件を編集する権限がありません（担当者以外は編集できません）。<a href="cases.php">一覧に戻る</a>');
    }
    $case = $existing;
}

// ---- POST処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $case = [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'title' => trim($_POST['title'] ?? ''),
            'status' => $_POST['status'] ?? '未着手',
            'assigned_user_id' => $_POST['assigned_user_id'] !== '' ? (int)$_POST['assigned_user_id'] : null,
            'amount' => $_POST['amount'] !== '' ? (float)$_POST['amount'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'due_date' => $_POST['due_date'] !== '' ? $_POST['due_date'] : null,
        ];

        if ($isEdit && !is_admin()) {
            $existing = load_case($pdo, $caseId);
            if (!$existing || (int)$existing['assigned_user_id'] !== (int)$me['id']) {
                http_response_code(403);
                die('この案件を編集する権限がありません。');
            }
        }

        if (!$case['customer_id'] || !$case['title']) {
            $errors[] = '顧客と案件名は必須です';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE cases SET customer_id=?, title=?, status=?, assigned_user_id=?, amount=?, description=?, due_date=? WHERE id=?');
                $stmt->execute([$case['customer_id'], $case['title'], $case['status'], $case['assigned_user_id'], $case['amount'], $case['description'], $case['due_date'], $caseId]);
                flash_set('success', '案件を更新しました');
                redirect('case_form.php?id=' . $caseId);
            } else {
                $stmt = $pdo->prepare('INSERT INTO cases (customer_id, title, status, assigned_user_id, amount, description, due_date, created_by) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$case['customer_id'], $case['title'], $case['status'], $case['assigned_user_id'], $case['amount'], $case['description'], $case['due_date'], $me['id']]);
                $newId = $pdo->lastInsertId();
                flash_set('success', '案件を作成しました');
                redirect('case_form.php?id=' . $newId);
            }
        }
    } elseif ($action === 'add_activity' && $isEdit) {
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            $stmt = $pdo->prepare('INSERT INTO case_activities (case_id, user_id, note) VALUES (?,?,?)');
            $stmt->execute([$caseId, $me['id'], $note]);
        }
        redirect('case_form.php?id=' . $caseId);
    } elseif ($action === 'delete' && $isEdit) {
        require_admin();
        $pdo->prepare('DELETE FROM cases WHERE id=?')->execute([$caseId]);
        flash_set('success', '案件を削除しました');
        redirect('cases.php');
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$users = $pdo->query('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name')->fetchAll();

$activities = [];
if ($isEdit) {
    $stmt = $pdo->prepare(
        'SELECT a.*, u.display_name AS user_name FROM case_activities a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.case_id = ? ORDER BY a.created_at DESC'
    );
    $stmt->execute([$caseId]);
    $activities = $stmt->fetchAll();
}

$pageTitle = $isEdit ? '案件編集' : '新規案件';
?>

<a href="cases.php" class="back-link">← 案件一覧に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? '案件編集' : '新規案件' ?></h2>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="detail-grid">
  <div class="form-card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$caseId ?>"><?php endif; ?>

      <div class="field">
        <label>顧客</label>
        <select name="customer_id" required>
          <option value="">選択してください</option>
          <?php foreach ($customers as $cu): ?>
            <option value="<?= (int)$cu['id'] ?>" <?= (int)$case['customer_id'] === (int)$cu['id'] ? 'selected' : '' ?>><?= e($cu['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>案件名</label>
        <input type="text" name="title" value="<?= e($case['title']) ?>" required>
      </div>

      <div class="field-row">
        <div class="field">
          <label>ステータス</label>
          <select name="status">
            <?php foreach (['未着手', '進行中', '保留', '完了'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $case['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>担当者</label>
          <select name="assigned_user_id">
            <option value="">未割当</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= (int)($case['assigned_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label>金額（任意・税抜）</label>
        <input type="number" step="1" name="amount" value="<?= e($case['amount']) ?>" placeholder="例: 100000">
        <div class="hint">請求書・見積書・領収書を作成する際、この金額が明細の初期値として使われます。</div>
      </div>

      <div class="field">
        <label>期限</label>
        <input type="date" name="due_date" value="<?= e($case['due_date']) ?>">
      </div>

      <div class="field">
        <label>詳細・メモ</label>
        <textarea name="description" rows="4"><?= e($case['description']) ?></textarea>
      </div>

      <div class="form-actions">
        <?php if ($isEdit && is_admin()): ?>
          <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('この案件を削除しますか？元に戻せません。');">削除</button>
        <?php endif; ?>
        <span class="spacer"></span>
        <button type="submit" class="btn">保存</button>
      </div>
    </form>
  </div>

  <?php if ($isEdit): ?>
  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">見積書・請求書・領収書</h3>
    <a href="quote_form.php?case_id=<?= (int)$caseId ?>" class="btn secondary small full" style="margin-bottom:8px;">＋ 見積書を作成</a>
    <a href="invoice_form.php?case_id=<?= (int)$caseId ?>" class="btn secondary small full" style="margin-bottom:8px;">＋ 請求書を発行</a>
    <a href="receipt_form.php?case_id=<?= (int)$caseId ?>" class="btn secondary small full">＋ 領収書を発行</a>
  </div>

  <div class="form-card">
    <h3 style="margin-top:0; font-size:14px;">進捗メモ</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_activity">
      <div class="field" style="display:flex; gap:8px; margin-bottom:8px;">
        <input type="text" name="note" placeholder="対応内容を記録" required style="flex:1;">
        <button type="submit" class="btn secondary small">追加</button>
      </div>
    </form>
    <div class="activity-list">
      <?php if (empty($activities)): ?>
        <div class="hint">まだメモがありません</div>
      <?php endif; ?>
      <?php foreach ($activities as $a): ?>
        <div class="activity-item">
          <div><?= nl2br(e($a['note'])) ?></div>
          <div class="meta"><?= e($a['user_name'] ?? '不明') ?> ・ <?= e($a['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
