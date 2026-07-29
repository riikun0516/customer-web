<?php
$activePage = 'company_settings';
$pageTitle = '自社情報設定';
require_once __DIR__ . '/includes/pdf_helper.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$errors = [];
$testMailResult = null;

$settings = get_company_settings($pdo);
$exists = $pdo->query('SELECT COUNT(*) FROM company_settings WHERE id = 1')->fetchColumn() > 0;

$uploadDir = __DIR__ . '/uploads/logos/';
$maxLogoBytes = 2 * 1024 * 1024; // 2MB
$allowedLogoTypes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'send_test_mail') {
        $testTo = trim($_POST['test_email_to'] ?? '');
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $testMailResult = ['ok' => false, 'message' => '送信先メールアドレスの形式が正しくありません'];
        } else {
            try {
                smtp_send_mail(
                    $testTo,
                    '',
                    '【COBIS】テストメール',
                    "これはCOBISのSMTP設定確認用のテストメールです。\nこのメールが届いていれば、SMTP設定は正しく機能しています。",
                    $settings
                );
                $testMailResult = ['ok' => true, 'message' => $testTo . ' 宛にテストメールを送信しました。届いているか確認してください。'];
            } catch (Exception $ex) {
                $testMailResult = ['ok' => false, 'message' => '送信に失敗しました: ' . $ex->getMessage()];
            }
        }
    } elseif ($action === 'save_settings') {
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
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_username' => trim($_POST['smtp_username'] ?? ''),
            // パスワードは空欄のまま送信された場合、既存の値を維持する（毎回入力し直す必要をなくすため）
            'smtp_password' => ($_POST['smtp_password'] ?? '') !== '' ? $_POST['smtp_password'] : ($settings['smtp_password'] ?? ''),
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
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
                 bank_name=?, branch_name=?, account_type=?, account_number=?, account_holder=?, default_tax_rate=?, invoice_note=?, contract_template=?,
                 smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, smtp_password=?, smtp_from_email=?, smtp_from_name=?
                 WHERE id=1'
            );
            $stmt->execute(array_values($settings));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO company_settings (id, company_name, logo_path, postal_code, address, tel, email, registration_number,
                 bank_name, branch_name, account_type, account_number, account_holder, default_tax_rate, invoice_note, contract_template,
                 smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, smtp_from_email, smtp_from_name)
                 VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute(array_values($settings));
            $exists = true;
        }
        flash_set('success', '自社情報を保存しました');
        redirect('company_settings.php');
    }
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
    <input type="hidden" name="action" value="save_settings">

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

    <h3 style="font-size:14px; margin:24px 0 12px; border-top:1px solid var(--border); padding-top:20px;">メール送信設定（SMTP）</h3>
    <p class="hint" style="margin-bottom:14px;">ユーザーの「パスワードをお忘れですか？」からのパスワード再設定メール送信に使用します。Gmail等の外部SMTPサーバーの利用を想定しています。</p>
    <div class="field-row">
      <div class="field">
        <label>SMTPホスト</label>
        <input type="text" name="smtp_host" value="<?= e($settings['smtp_host']) ?>" placeholder="smtp.gmail.com">
      </div>
      <div class="field" style="max-width:120px;">
        <label>ポート</label>
        <input type="number" name="smtp_port" value="<?= e($settings['smtp_port']) ?>" placeholder="587">
      </div>
      <div class="field" style="max-width:140px;">
        <label>暗号化方式</label>
        <select name="smtp_encryption">
          <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
          <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
          <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>なし</option>
        </select>
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>SMTPユーザー名</label>
        <input type="text" name="smtp_username" value="<?= e($settings['smtp_username']) ?>" autocomplete="off">
      </div>
      <div class="field">
        <label>SMTPパスワード <?php if (!empty($settings['smtp_password'])): ?><span class="hint">（変更する場合のみ入力）</span><?php endif; ?></label>
        <input type="password" name="smtp_password" autocomplete="new-password">
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>送信元メールアドレス</label>
        <input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email']) ?>" placeholder="noreply@example.com">
      </div>
      <div class="field">
        <label>送信元表示名</label>
        <input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name']) ?>" placeholder="COBIS">
      </div>
    </div>

    <div class="form-actions">
      <span class="spacer"></span>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>

  <?php if ($testMailResult): ?>
    <div class="msg <?= $testMailResult['ok'] ? 'success' : 'error' ?>" style="margin-top:20px;"><?= e($testMailResult['message']) ?></div>
  <?php endif; ?>

  <form method="post" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border); display:flex; gap:8px; align-items:flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="send_test_mail">
    <div class="field" style="flex:1; margin-bottom:0;">
      <label>テストメール送信先</label>
      <input type="email" name="test_email_to" placeholder="test@example.com" required>
    </div>
    <button type="submit" class="btn secondary">テストメールを送信</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
