<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/schema.php';

$configPath = __DIR__ . '/config/config.php';
$configExists = file_exists($configPath);

$errors = [];
$step = 1;
$dbValues = ['host' => 'localhost', 'port' => '3306', 'name' => '', 'user' => '', 'pass' => ''];


function try_connect($host, $port, $name, $user, $pass) {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// 既に config.php があり、接続もでき、管理者も存在する場合はセットアップ済みとみなす
$adminExists = false;
$pdoIfConfigured = null;
if ($configExists) {
    require_once $configPath;
    try {
        $pdoIfConfigured = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        foreach (schema_statements() as $stmt) {
            $pdoIfConfigured->exec($stmt);
        }
        run_migrations($pdoIfConfigured);
        $cnt = $pdoIfConfigured->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $adminExists = $cnt > 0;
    } catch (Exception $ex) {
        $errors[] = '既存の config.php で接続できませんでした: ' . $ex->getMessage() .
            '（config/config.php を修正するか削除して再セットアップしてください）';
    }
}

if ($configExists && $adminExists) {
    redirect('login.php');
}
if ($configExists && $pdoIfConfigured && !$adminExists) {
    $step = 2;
}

// ---- POST処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_db') {
        $dbValues = [
            'host' => trim($_POST['host'] ?? ''),
            'port' => trim($_POST['port'] ?? '3306'),
            'name' => trim($_POST['name'] ?? ''),
            'user' => trim($_POST['user'] ?? ''),
            'pass' => (string)($_POST['pass'] ?? ''),
        ];
        if (!$dbValues['host'] || !$dbValues['name'] || !$dbValues['user']) {
            $errors[] = 'ホスト・データベース名・ユーザー名は必須です';
        } else {
            try {
                $pdo = try_connect($dbValues['host'], $dbValues['port'], $dbValues['name'], $dbValues['user'], $dbValues['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                foreach (schema_statements() as $stmt) {
                    $pdo->exec($stmt);
                }
                run_migrations($pdo);

                $configContent = "<?php\n" .
                    "define('DB_HOST', " . var_export($dbValues['host'], true) . ");\n" .
                    "define('DB_PORT', " . var_export($dbValues['port'], true) . ");\n" .
                    "define('DB_NAME', " . var_export($dbValues['name'], true) . ");\n" .
                    "define('DB_USER', " . var_export($dbValues['user'], true) . ");\n" .
                    "define('DB_PASS', " . var_export($dbValues['pass'], true) . ");\n";

                if (!is_writable(__DIR__ . '/config')) {
                    $errors[] = 'config ディレクトリに書き込み権限がありません。サーバー側でパーミッションを確認してください（例: chmod 755 config）。';
                } else {
                    file_put_contents($configPath, $configContent);
                    $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                    if ($cnt > 0) {
                        redirect('login.php');
                    }
                    $step = 2;
                }
            } catch (Exception $ex) {
                $errors[] = '接続に失敗しました: ' . $ex->getMessage();
            }
        }
    } elseif ($action === 'create_admin') {
        if (!$configExists && !file_exists($configPath)) {
            $errors[] = 'DB設定が完了していません。最初からやり直してください。';
        } else {
            require_once $configPath;
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            if (!$username || !$displayName || !$password) {
                $errors[] = '全ての項目を入力してください';
                $step = 2;
            } else {
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
                        DB_USER, DB_PASS,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?,?,?,"admin",1)');
                    $stmt->execute([$username, $hash, $displayName]);
                    redirect('login.php');
                } catch (Exception $ex) {
                    $errors[] = '管理者作成に失敗しました: ' . $ex->getMessage();
                    $step = 2;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>初期セットアップ - 顧客管理ツール</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="center-screen">
  <div class="card wide">
    <h1 class="title">初期セットアップ</h1>
    <p class="subtitle">利用開始前にデータベース接続と管理者アカウントを設定します</p>

    <div class="step-indicator">
      <div class="dot <?= $step === 1 ? 'active' : '' ?>"></div>
      <div class="dot <?= $step === 2 ? 'active' : '' ?>"></div>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="msg error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_db">
      <div class="field-row">
        <div class="field">
          <label>DBホスト</label>
          <input type="text" name="host" value="<?= e($dbValues['host']) ?>" required>
        </div>
        <div class="field" style="max-width:120px;">
          <label>ポート</label>
          <input type="text" name="port" value="<?= e($dbValues['port']) ?>">
        </div>
      </div>
      <div class="field">
        <label>DBユーザー名</label>
        <input type="text" name="user" value="<?= e($dbValues['user']) ?>" required>
      </div>
      <div class="field">
        <label>DBパスワード</label>
        <input type="password" name="pass">
      </div>
      <div class="field">
        <label>データベース名</label>
        <input type="text" name="name" value="<?= e($dbValues['name']) ?>" required>
        <div class="hint">レンタルサーバーの管理画面で事前にデータベースを作成しておいてください</div>
      </div>
      <button type="submit" class="btn full">接続テストして次へ</button>
    </form>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_admin">
      <div class="field">
        <label>管理者ユーザー名</label>
        <input type="text" name="username" required>
      </div>
      <div class="field">
        <label>表示名</label>
        <input type="text" name="display_name" required>
      </div>
      <div class="field">
        <label>パスワード</label>
        <input type="password" name="password" required>
        <div class="hint">8文字以上を推奨します</div>
      </div>
      <button type="submit" class="btn full">管理者アカウントを作成して開始</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
