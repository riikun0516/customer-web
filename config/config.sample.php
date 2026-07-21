<?php
/**
 * このファイルは初期セットアップ(setup.php)によって自動生成されます。
 * 手動で設置する場合はこの内容をコピーして config.php を作成してください。
 */
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'customer_management');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

// ---- 以下は任意設定（GitHub連携アップデート機能） ----
// アップデート元リポジトリを変更する場合のみ定義してください（未定義時は既定値が使われます）
// define('GH_UPDATE_OWNER', 'riikun0516');
// define('GH_UPDATE_REPO', 'customer-web');
// リポジトリが private の場合、Personal Access Token（repoスコープ）を設定してください
// define('GH_UPDATE_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx');
