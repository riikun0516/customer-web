<?php
/**
 * 共通ヘッダー（ログイン後の全ページで include する）
 * 呼び出し側で $activePage ( 'cases' | 'customers' | 'users' ) を定義しておくこと
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

$me = current_user();
$activePage = $activePage ?? '';
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>顧客管理ツール</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div class="brand">顧客管理ツール</div>
    <nav>
      <a href="cases.php" class="nav-link <?= $activePage === 'cases' ? 'active' : '' ?>">案件一覧</a>
      <a href="customers.php" class="nav-link <?= $activePage === 'customers' ? 'active' : '' ?>">顧客一覧</a>
      <a href="quotes.php" class="nav-link <?= $activePage === 'quotes' ? 'active' : '' ?>">見積書</a>
      <a href="invoices.php" class="nav-link <?= $activePage === 'invoices' ? 'active' : '' ?>">請求書</a>
      <a href="receipts.php" class="nav-link <?= $activePage === 'receipts' ? 'active' : '' ?>">領収書</a>
      <?php if (is_admin()): ?>
      <a href="users.php" class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>">ユーザー管理</a>
      <a href="company_settings.php" class="nav-link <?= $activePage === 'company_settings' ? 'active' : '' ?>">自社情報設定</a>
      <a href="migrate.php" class="nav-link <?= $activePage === 'migrate' ? 'active' : '' ?>" style="font-size:11px; opacity:0.75;">DBスキーマ更新</a>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <div class="name"><?= e($me['display_name']) ?></div>
      <div class="role"><?= $me['role'] === 'admin' ? '管理者' : '一般ユーザー' ?></div>
      <form method="post" action="logout.php">
        <button type="submit" class="btn secondary small full">ログアウト</button>
      </form>
    </div>
  </div>
  <div class="main-area">
    <?php if ($flash): ?>
      <div class="msg show <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
