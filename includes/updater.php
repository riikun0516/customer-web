<?php
/**
 * GitHubリポジトリからのアップデート・整合性確認・再インストール機能
 *
 * 前提:
 * - 対象リポジトリは public（匿名アクセス可能）であること
 *   ※ private リポジトリの場合は config.php に define('GH_UPDATE_TOKEN', '...'); を追加すると
 *     Personal Access Token を使って認証付きでアクセスします。
 * - PHPに curl 拡張、zip 拡張（ZipArchive）が必要です
 * - リポジトリ直下（web公開ディレクトリ相当）にこのツール一式が置かれている前提です
 */

// ---- リポジトリ設定（config.php で上書き可能） ----
if (!defined('GH_UPDATE_OWNER')) define('GH_UPDATE_OWNER', 'riikun0516');
if (!defined('GH_UPDATE_REPO')) define('GH_UPDATE_REPO', 'customer-web');

// アップデート時に絶対に上書き・削除しないパス（相対パス完全一致 or 前方一致）
const UPDATE_PROTECTED_EXACT = [
    'config/config.php',
];
const UPDATE_PROTECTED_PREFIX = [
    'uploads/logos/',
];

function gh_update_configured() {
    return function_exists('curl_init') && class_exists('ZipArchive');
}

function gh_update_missing_requirements() {
    $missing = [];
    if (!function_exists('curl_init')) $missing[] = 'PHP の curl 拡張';
    if (!class_exists('ZipArchive')) $missing[] = 'PHP の zip 拡張（ZipArchive）';
    return $missing;
}

/**
 * GitHub API に GET リクエストを送る（JSON応答をデコードして返す）
 */
function gh_api_get($path) {
    $url = 'https://api.github.com' . $path;
    $headers = [
        'User-Agent: cobis-updater',
        'Accept: application/vnd.github+json',
    ];
    if (defined('GH_UPDATE_TOKEN') && GH_UPDATE_TOKEN) {
        $headers[] = 'Authorization: Bearer ' . GH_UPDATE_TOKEN;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('GitHub APIへの接続に失敗しました: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($body, true);
    if ($status >= 400) {
        $msg = $data['message'] ?? ('HTTP ' . $status);
        throw new Exception('GitHub APIエラー: ' . $msg);
    }
    return $data;
}

function gh_get_default_branch($owner, $repo) {
    $info = gh_api_get("/repos/$owner/$repo");
    return $info['default_branch'] ?? 'main';
}

/**
 * 単一のリリース情報を扱いやすい形に整形する
 */
function gh_normalize_release($data) {
    return [
        'tag_name' => $data['tag_name'],
        'name' => $data['name'] ?: $data['tag_name'],
        'published_at' => $data['published_at'] ?? ($data['created_at'] ?? ''),
        'body' => $data['body'] ?? '',
        'html_url' => $data['html_url'] ?? '',
        'prerelease' => !empty($data['prerelease']),
        'draft' => !empty($data['draft']),
    ];
}

/**
 * 最新リリース（Release Tag）を取得
 */
function gh_get_latest_release($owner, $repo) {
    $data = gh_api_get("/repos/$owner/$repo/releases/latest");
    return gh_normalize_release($data);
}

/**
 * リリース一覧を取得（新しい順、既定で最大15件。ドラフトは除外）
 */
function gh_list_releases($owner, $repo, $perPage = 15) {
    $data = gh_api_get("/repos/$owner/$repo/releases?per_page=" . (int)$perPage);
    $releases = [];
    foreach ($data as $item) {
        if (!empty($item['draft'])) continue;
        $releases[] = gh_normalize_release($item);
    }
    return $releases;
}

/**
 * バージョン文字列の先頭の "v"/"V" を除去する（例: "v1.2.0" -> "1.2.0"）
 */
function gh_normalize_version_string($v) {
    return ltrim((string)$v, 'vV');
}

/**
 * $latestTag が $installedTag より新しいバージョンかどうかを判定する。
 * セマンティックバージョニング（例: v1.2.0）を想定し、比較できない形式の場合は
 * 単純な文字列不一致で「更新あり」とみなす。
 */
function gh_version_is_newer($latestTag, $installedTag) {
    if (!$installedTag) return true;
    if ($latestTag === $installedTag) return false;
    $a = gh_normalize_version_string($latestTag);
    $b = gh_normalize_version_string($installedTag);
    $cmp = version_compare($a, $b);
    if ($cmp === 0) return false;
    return $cmp > 0;
}

/**
 * 指定コミットのファイルツリー（再帰）を取得
 * 戻り値: [ 'path/to/file.php' => ['sha' => blob_sha, 'size' => bytes], ... ] （blob = ファイルのみ）
 * $ref はコミットSHAだけでなく、ブランチ名・タグ名も指定可能
 */
function gh_get_tree($owner, $repo, $ref) {
    $data = gh_api_get("/repos/$owner/$repo/git/trees/" . rawurlencode($ref) . "?recursive=1");
    $result = [];
    foreach ($data['tree'] ?? [] as $item) {
        if ($item['type'] === 'blob') {
            $result[$item['path']] = ['sha' => $item['sha'], 'size' => $item['size'] ?? null];
        }
    }
    if (!empty($data['truncated'])) {
        throw new Exception('リポジトリのファイル数が多すぎて一覧を取得しきれませんでした（GitHub API制限）。');
    }
    return $result;
}

/**
 * Gitのblob SHA-1を計算する（"blob <size>\0<content>" のSHA-1）
 */
function git_blob_sha1($filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) return null;
    return sha1('blob ' . strlen($content) . "\0" . $content);
}

/**
 * 指定パスがアップデート対象から保護されているか
 */
function gh_update_is_protected($relPath) {
    $relPath = str_replace('\\', '/', $relPath);
    if (in_array($relPath, UPDATE_PROTECTED_EXACT, true)) return true;
    foreach (UPDATE_PROTECTED_PREFIX as $prefix) {
        if (strpos($relPath, $prefix) === 0) return true;
    }
    return false;
}

/**
 * ブランチ/コミットのZIPをダウンロードして一時ファイルに保存
 */
function gh_download_zip($owner, $repo, $ref, $destPath) {
    $url = "https://codeload.github.com/$owner/$repo/zip/" . rawurlencode($ref);
    $fp = fopen($destPath, 'wb');
    if (!$fp) throw new Exception('一時ファイルを作成できませんでした: ' . $destPath);

    $headers = ['User-Agent: cobis-updater'];
    if (defined('GH_UPDATE_TOKEN') && GH_UPDATE_TOKEN) {
        $headers[] = 'Authorization: Bearer ' . GH_UPDATE_TOKEN;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $ok = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $status >= 400) {
        @unlink($destPath);
        throw new Exception('ZIPのダウンロードに失敗しました: ' . ($err ?: ('HTTP ' . $status)));
    }
    return true;
}

/**
 * ディレクトリを再帰的にコピーする（$srcにあるファイルを$dstへ上書きコピー。
 * $dstにしか無いファイルは削除しない。保護パスはスキップする）
 */
function gh_recursive_copy($src, $dst, $baseDstForProtectionCheck) {
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = $src . '/' . $item;
        $dstPath = $dst . '/' . $item;
        $relPath = ltrim(str_replace($baseDstForProtectionCheck, '', $dstPath), '/');

        if (gh_update_is_protected($relPath)) {
            continue;
        }

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) @mkdir($dstPath, 0755, true);
            gh_recursive_copy($srcPath, $dstPath, $baseDstForProtectionCheck);
        } else {
            @copy($srcPath, $dstPath);
        }
    }
}

/**
 * $dir配下を再帰的に削除する（一時展開フォルダの掃除用）
 */
function gh_rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            gh_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function gh_installed_version_path() {
    return __DIR__ . '/../config/installed_version.json';
}

function gh_get_installed_version() {
    $path = gh_installed_version_path();
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return $data ?: null;
}

function gh_save_installed_version($tagName, $meta = []) {
    $data = array_merge(['tag_name' => $tagName, 'updated_at' => date('Y-m-d H:i:s')], $meta);
    @file_put_contents(gh_installed_version_path(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 指定refの内容を取得・展開して現在のディレクトリへ適用する（アップデート／再インストール共通処理）
 */
function gh_apply_update($owner, $repo, $ref) {
    $tmpDir = sys_get_temp_dir() . '/cmt_update_' . uniqid();
    @mkdir($tmpDir, 0755, true);
    $zipPath = $tmpDir . '/update.zip';

    try {
        gh_download_zip($owner, $repo, $ref, $zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('ダウンロードしたZIPを開けませんでした');
        }
        $extractDir = $tmpDir . '/extracted';
        @mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        // codeload.github.com のZIPは "リポジトリ名-ref/" という単一フォルダを含むので中身を特定
        $entries = array_values(array_diff(scandir($extractDir), ['.', '..']));
        if (count($entries) !== 1 || !is_dir($extractDir . '/' . $entries[0])) {
            throw new Exception('ZIPの構造が想定と異なります');
        }
        $sourceRoot = $extractDir . '/' . $entries[0];

        $webRoot = realpath(__DIR__ . '/..');
        gh_recursive_copy($sourceRoot, $webRoot, $webRoot);

        // スキーマを最新化（新しいテーブル・列があれば追加）
        require_once __DIR__ . '/schema.php';
        require_once __DIR__ . '/db.php';
        $pdo = get_pdo();
        foreach (schema_statements() as $stmt) {
            $pdo->exec($stmt);
        }
        run_migrations($pdo);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return true;
    } finally {
        gh_rrmdir($tmpDir);
    }
}

/**
 * ローカルファイルと指定ref時点のGitHub上のファイルを比較する
 * 戻り値: ['modified' => [...], 'missing' => [...], 'ok_count' => N, 'total' => N]
 */
function gh_check_integrity($owner, $repo, $ref) {
    $tree = gh_get_tree($owner, $repo, $ref);
    $webRoot = realpath(__DIR__ . '/..');

    $modified = [];
    $missing = [];
    $okCount = 0;

    foreach ($tree as $relPath => $info) {
        if (gh_update_is_protected($relPath)) continue;
        $localPath = $webRoot . '/' . $relPath;
        if (!is_file($localPath)) {
            $missing[] = $relPath;
            continue;
        }
        $localSha = git_blob_sha1($localPath);
        if ($localSha === $info['sha']) {
            $okCount++;
        } else {
            $modified[] = $relPath;
        }
    }

    return [
        'modified' => $modified,
        'missing' => $missing,
        'ok_count' => $okCount,
        'total' => count($tree),
    ];
}
