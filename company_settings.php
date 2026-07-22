<?php
$activePage = 'company_settings';
$pageTitle = '自社情報設定';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$errors = [];

$settings = get_company_settings($pdo);
$exists = $pdo->query('SELECT COUNT(*) FROM company_settings WHERE id = 1')->fetchColumn() > 0;

$uploadDir = __DIR__ . '/uploads/logos/';
$maxLogoBytes = 2 * 1024 * 1024; // 2MB
$allowedLogoTypes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $settings = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'logo_path' => $settings['logo_path'] ?? null,
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'tel' => trim($_POST['tel'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'registration_number' => trim($_POST['registration_number'] ?? ''),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'branch_name' => trim($_POST['branch_name'] ?? ''),
        'account_type' => $_POST['account_type'] ?? '普通',
        'account_number' => trim($_POST['account_number'] ?? ''),
        'account_holder' => trim($_POST['account_holder'] ?? ''),
        'default_tax_rate' => (float)($_POST['default_tax_rate'] ?? 10),
        'invoice_note' => trim($_POST['invoice_note'] ?? ''),
        'contract_template' => $_POST['contract_template'] ?? '',
    ];

    // ロゴ削除チェックボックス
    if (!empty($_POST['remove_logo']) && $settings['logo_path']) {
        $oldFile = __DIR__ . '/' . $settings['logo_path'];
        if (is_file($oldFile)) @unlink($oldFile);
        $settings['logo_path'] = null;
    }

    // ロゴアップロード
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['logo']['tmp_name'];
        $size = $_FILES['logo']['size'];
        $mime = @mime_content_type($tmpPath);

        if ($size > $maxLogoBytes) {
            $errors[] = 'ロゴ画像は2MB以下にしてください';
        } elseif (!isset($allowedLogoTypes[$mime])) {
            $errors[] = 'ロゴ画像はPNG・JPEG・GIFのいずれかにしてください';
        } else {
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            if (!is_writable($uploadDir)) {
                $errors[] = 'uploads/logos ディレクトリに書き込み権限がありません';
            } else {
                $ext = $allowedLogoTypes[$mime];
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($tmpPath, $uploadDir . $filename)) {
                    // 古いロゴが残っていれば削除
                    if ($settings['logo_path']) {
                        $oldFile = __DIR__ . '/' . $settings['logo_path'];
                        if (is_file($oldFile)) @unlink($oldFile);
                    }
                    $settings['logo_path'] = 'uploads/logos/' . $filename;
                } else {
                    $errors[] = 'ロゴ画像のアップロードに失敗しました';
                }
            }
        }
    }

    if (!$settings['company_name']) {
        $errors[] = '会社名（発行者名）は必須です';
    }

    if (empty($errors)) {
        if ($exists) {
            $stmt = $pdo->prepare(
                'UPDATE company_settings SET company_name=?, logo_path=?, postal_code=?, address=?, tel=?, email=?, registration_number=?,
                 bank_name=?, branch_name=?, account_type=?, account_number=?, account_holder=?, default_tax_rate=?, invoice_note=?, contract_template=?
                 WHERE id=1'
            );
            $stmt->execute(array_values($settings));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO company_settings (id, company_name, logo_path, postal_code, address, tel, email, registration_number,
                 bank_name, branch_name, account_type, account_number, account_holder, default_tax_rate, invoice_note, contract_template)
                 VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute(array_values($settings));
            $exists = true;
        }
        flash_set('success', '自社情報を保存しました');
        redirect('company_settings.php');
    }
}
?>

<div class="page-header">
  <h2>自社情報設定</h2>
</div>
<p class="hint" style="margin-bottom:16px;">ここで設定した内容が、請求書・領収書PDFの発行者情報および振込先として印字されます。</p>

<?php foreach ($errors as $err): ?>
  <div class="msg error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:720px;">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="field">
      <label>会社ロゴ</label>
      <?php if (!empty($settings['logo_path']) && is_file(__DIR__ . '/' . $settings['logo_path'])): ?>
        <div style="margin-bottom:10px;">
          <img src="<?= e($settings['logo_path']) ?>?t=<?= time() ?>" alt="会社ロゴ" style="max-height:60px; max-width:240px; display:block; margin-bottom:8px; border:1px solid var(--border); border-radius:6px; padding:4px;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-sub);">
            <input type="checkbox" name="remove_logo" value="1" style="width:auto;"> このロゴを削除する
          </label>
        </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/gif">
      <div class="hint">PNG・JPEG・GIF、2MBまで。請求書・領収書・見積書のPDF上部に印字されます。</div>
    </div>

    <div class="field">
      <label>会社名・屋号 *</label>
      <input type="text" name="company_name" value="<?= e($settings['company_name']) ?>" required>
    </div>
    <div class="field-row">
      <div class="field" style="max-width:160px;">
        <label>郵便番号</label>
        <input type="text" name="postal_code" value="<?= e($settings['postal_code']) ?>" placeholder="123-4567">
      </div>
      <div class="field">
        <label>住所</label>
        <input type="text" name="address" value="<?= e($settings['address']) ?>">
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>電話番号</label>
        <input type="text" name="tel" value="<?= e($settings['tel']) ?>">
      </div>
      <div class="field">
        <label>メールアドレス</label>
        <input type="email" name="email" value="<?= e($settings['email']) ?>">
      </div>
    </div>
    <div class="field">
      <label>インボイス登録番号</label>
      <input type="text" name="registration_number" value="<?= e($settings['registration_number']) ?>" placeholder="T1234567890123">
    </div>

    <h3 style="font-size:14px; margin:24px 0 12px; border-top:1px solid var(--border); padding-top:20px;">振込先（請求書に印字されます）</h3>
    <div class="field-row">
      <div class="field">
        <label>銀行名</label>
        <input type="text" name="bank_name" value="<?= e($settings['bank_name']) ?>" placeholder="○○銀行">
      </div>
      <div class="field">
        <label>支店名</label>
        <input type="text" name="branch_name" value="<?= e($settings['branch_name']) ?>" placeholder="○○支店">
      </div>
    </div>
    <div class="field-row">
      <div class="field" style="max-width:160px;">
        <label>口座種別</label>
        <select name="account_type">
          <option value="普通" <?= $settings['account_type'] === '普通' ? 'selected' : '' ?>>普通</option>
          <option value="当座" <?= $settings['account_type'] === '当座' ? 'selected' : '' ?>>当座</option>
        </select>
      </div>
      <div class="field">
        <label>口座番号</label>
        <input type="text" name="account_number" value="<?= e($settings['account_number']) ?>">
      </div>
    </div>
    <div class="field">
      <label>口座名義（カナ）</label>
      <input type="text" name="account_holder" value="<?= e($settings['account_holder']) ?>" placeholder="カ）〇〇〇〇">
    </div>

    <h3 style="font-size:14px; margin:24px 0 12px; border-top:1px solid var(--border); padding-top:20px;">請求書の初期設定</h3>
    <div class="field" style="max-width:160px;">
      <label>標準消費税率（%）</label>
      <input type="number" step="0.01" name="default_tax_rate" value="<?= e($settings['default_tax_rate']) ?>">
    </div>
    <div class="field">
      <label>請求書の備考欄（デフォルト文言）</label>
      <textarea name="invoice_note" rows="2" placeholder="お振込手数料はご負担いただきますようお願いいたします。"><?= e($settings['invoice_note']) ?></textarea>
    </div>

    <h3 style="font-size:14px; margin:24px 0 12px; border-top:1px solid var(--border); padding-top:20px;">契約書の初期設定</h3>
    <div class="field">
      <label>契約書テンプレート（デフォルトの条項本文）</label>
      <textarea name="contract_template" rows="10" style="font-family: 'Hiragino Sans', 'Yu Gothic', monospace; font-size:13px;" placeholder="第1条（目的）&#10;甲は乙に対し、本契約に定める業務を委託し、乙はこれを受託する。&#10;&#10;第2条（契約期間）&#10;…"><?= e($settings['contract_template']) ?></textarea>
      <div class="hint">新規契約書を作成する際、この内容が条項本文の初期値として入力されます。契約ごとに編集できます。</div>
    </div>

    <div class="form-actions">
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
