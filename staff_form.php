<?php
$activePage = 'staff';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();

$staffId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $staffId > 0;
$errors = [];

$staff = [
    'name' => '', 'name_kana' => '', 'position' => '', 'email' => '', 'phone' => '', 'is_active' => 1,
    'bank_name' => '', 'branch_name' => '', 'account_type' => '普通', 'account_number' => '', 'account_holder' => '',
    'notes' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ?');
    $stmt->execute([$staffId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash_set('error', '指定されたスタッフが見つかりません');
        redirect('staff.php');
    }
    $staff = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isEdit) {
        $pdo->prepare('DELETE FROM staff WHERE id=?')->execute([$staffId]);
        flash_set('success', 'スタッフを削除しました');
        redirect('staff.php');
    }

    if ($action === 'save') {
        $staff = [
            'name' => trim($_POST['name'] ?? ''),
            'name_kana' => trim($_POST['name_kana'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'branch_name' => trim($_POST['branch_name'] ?? ''),
            'account_type' => $_POST['account_type'] ?? '普通',
            'account_number' => trim($_POST['account_number'] ?? ''),
            'account_holder' => trim($_POST['account_holder'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        if (!$staff['name']) {
            $errors[] = '氏名は必須です';
        } elseif ($staff['email'] !== '' && !filter_var($staff['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません';
        } else {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE staff SET name=?, name_kana=?, position=?, email=?, phone=?, is_active=?,
                     bank_name=?, branch_name=?, account_type=?, account_number=?, account_holder=?, notes=?
                     WHERE id=?'
                );
                $stmt->execute([
                    $staff['name'], $staff['name_kana'], $staff['position'], $staff['email'], $staff['phone'], $staff['is_active'],
                    $staff['bank_name'], $staff['branch_name'], $staff['account_type'], $staff['account_number'], $staff['account_holder'],
                    $staff['notes'], $staffId
                ]);
                flash_set('success', 'スタッフ情報を更新しました');
                redirect('staff_form.php?id=' . $staffId);
            } else {
                $me = current_user();
                $stmt = $pdo->prepare(
                    'INSERT INTO staff (name, name_kana, position, email, phone, is_active,
                     bank_name, branch_name, account_type, account_number, account_holder, notes, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $staff['name'], $staff['name_kana'], $staff['position'], $staff['email'], $staff['phone'], $staff['is_active'],
                    $staff['bank_name'], $staff['branch_name'], $staff['account_type'], $staff['account_number'], $staff['account_holder'],
                    $staff['notes'], $me['id']
                ]);
                $newId = $pdo->lastInsertId();
                flash_set('success', 'スタッフを登録しました');
                redirect('staff_form.php?id=' . $newId);
            }
        }
    }
}

$pageTitle = $isEdit ? 'スタッフ編集' : '新規スタッフ';
?>

<a href="staff.php" class="back-link">← スタッフ管理に戻る</a>
<div class="page-header">
  <h2><?= $isEdit ? 'スタッフ編集' : '新規スタッフ' ?></h2>
</div>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:640px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$staffId ?>"><?php endif; ?>

    <div class="field-row">
      <div class="field">
        <label>氏名 *</label>
        <input type="text" name="name" value="<?= e($staff['name']) ?>" required>
      </div>
      <div class="field">
        <label>フリガナ</label>
        <input type="text" name="name_kana" value="<?= e($staff['name_kana']) ?>" placeholder="ヤマダ タロウ">
      </div>
    </div>
    <div class="field">
      <label>役職・部署</label>
      <input type="text" name="position" value="<?= e($staff['position']) ?>" placeholder="例: 営業部 主任">
    </div>
    <div class="field-row">
      <div class="field">
        <label>メールアドレス</label>
        <input type="email" name="email" value="<?= e($staff['email']) ?>">
      </div>
      <div class="field">
        <label>電話番号</label>
        <input type="text" name="phone" value="<?= e($staff['phone']) ?>">
      </div>
    </div>
    <div class="field" style="display:flex; align-items:center; gap:8px;">
      <input type="checkbox" name="is_active" value="1" id="isActiveCheck" style="width:auto;" <?= $staff['is_active'] ? 'checked' : '' ?>>
      <label for="isActiveCheck" style="margin:0;">在籍中（有効）</label>
    </div>

    <h3 style="font-size:14px; margin:24px 0 12px; border-top:1px solid var(--border); padding-top:20px;">振込先口座</h3>
    <div class="field-row">
      <div class="field">
        <label>銀行名</label>
        <input type="text" name="bank_name" value="<?= e($staff['bank_name']) ?>" placeholder="○○銀行">
      </div>
      <div class="field">
        <label>支店名</label>
        <input type="text" name="branch_name" value="<?= e($staff['branch_name']) ?>" placeholder="○○支店">
      </div>
    </div>
    <div class="field-row">
      <div class="field" style="max-width:160px;">
        <label>口座種別</label>
        <select name="account_type">
          <option value="普通" <?= $staff['account_type'] === '普通' ? 'selected' : '' ?>>普通</option>
          <option value="当座" <?= $staff['account_type'] === '当座' ? 'selected' : '' ?>>当座</option>
        </select>
      </div>
      <div class="field">
        <label>口座番号</label>
        <input type="text" name="account_number" value="<?= e($staff['account_number']) ?>">
      </div>
    </div>
    <div class="field">
      <label>口座名義（カナ）</label>
      <input type="text" name="account_holder" value="<?= e($staff['account_holder']) ?>" placeholder="ヤマダ タロウ">
    </div>

    <div class="field">
      <label>備考</label>
      <textarea name="notes" rows="3"><?= e($staff['notes']) ?></textarea>
    </div>

    <div class="form-actions">
      <?php if ($isEdit): ?>
        <button type="submit" name="action" value="delete" class="btn danger" onclick="return confirm('このスタッフを削除しますか？');">削除</button>
      <?php endif; ?>
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
