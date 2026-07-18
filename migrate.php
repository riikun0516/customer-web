<?php
/**
 * 既存環境に新しいテーブル（company_settings / invoices / invoice_items / receipts など）を
 * 追加するための移行スクリプト。CREATE TABLE IF NOT EXISTS のみなので何度実行しても安全です。
 */
$activePage = 'migrate';
$pageTitle = 'DBスキーマ更新';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/header.php';
require_admin();

$pdo = get_pdo();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        foreach (schema_statements() as $stmt) {
            $pdo->exec($stmt);
        }
        $mig = run_migrations($pdo);
        $msg = 'スキーマを最新の状態に更新しました。';
        if ($mig['applied']) {
            $msg .= '（新規に適用: ' . count($mig['applied']) . '件）';
        }
        $result = ['ok' => true, 'message' => $msg];
    } catch (Exception $ex) {
        $result = ['ok' => false, 'message' => '更新に失敗しました: ' . $ex->getMessage()];
    }
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
  <h2>DBスキーマ更新</h2>
</div>

<div class="form-card">
  <p class="hint" style="margin-bottom:16px;">
    請求書・領収書機能などの追加にともない新しいテーブルが必要な場合、ここから追加できます。
    既存のテーブルやデータは変更・削除されません（<code>CREATE TABLE IF NOT EXISTS</code> のみ実行します）。
  </p>

  <?php if ($result): ?>
    <div class="msg <?= $result['ok'] ? 'success' : 'error' ?>"><?= e($result['message']) ?></div>
  <?php endif; ?>

  <p style="font-size:12px; color:var(--text-sub); margin-bottom:14px;">現在のテーブル: <?= e(implode(', ', $tables)) ?></p>

  <form method="post">
    <?= csrf_field() ?>
    <button type="submit" class="btn">最新のスキーマに更新する</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
