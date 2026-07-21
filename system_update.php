<?php
$activePage = 'system_update';
$pageTitle = 'システム更新';
require_once __DIR__ . '/includes/updater.php';
require_once __DIR__ . '/includes/header.php';
require_admin();

$owner = GH_UPDATE_OWNER;
$repo = GH_UPDATE_REPO;

$latest = $_SESSION['gh_latest'] ?? null;
$releaseList = $_SESSION['gh_release_list'] ?? null;
$integrity = $_SESSION['gh_integrity'] ?? null;
$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if (!gh_update_configured()) {
        $missing = gh_update_missing_requirements();
        $actionError = 'この機能を使うには ' . implode(' / ', $missing) . ' が必要です。サーバーの管理画面でPHP拡張の有効化をご確認ください。';
    } else {
        try {
            if ($action === 'check_latest') {
                $latest = gh_get_latest_release($owner, $repo);
                $_SESSION['gh_latest'] = $latest;
                $_SESSION['gh_integrity'] = null;
                $integrity = null;
            } elseif ($action === 'list_releases') {
                $releaseList = gh_list_releases($owner, $repo, 15);
                $_SESSION['gh_release_list'] = $releaseList;
            } elseif ($action === 'apply_update') {
                $tag = trim($_POST['tag'] ?? '');
                if (!$tag) {
                    $rel = gh_get_latest_release($owner, $repo);
                    $tag = $rel['tag_name'];
                }
                // メタ情報（表示用）を先に取得しておく。取れなくても更新自体は続行する
                $meta = ['name' => $tag];
                try {
                    foreach (gh_list_releases($owner, $repo, 30) as $r) {
                        if ($r['tag_name'] === $tag) {
                            $meta = ['name' => $r['name'], 'published_at' => $r['published_at']];
                            break;
                        }
                    }
                } catch (Exception $ignore) { /* メタ情報の付加取得に失敗しても更新自体は成功扱い */ }

                gh_apply_update($owner, $repo, $tag);
                gh_save_installed_version($tag, $meta);

                $_SESSION['gh_latest'] = null;
                $_SESSION['gh_release_list'] = null;
                $_SESSION['gh_integrity'] = null;
                flash_set('success', 'バージョン ' . $tag . ' を適用しました。');
                redirect('system_update.php');
            } elseif ($action === 'check_integrity') {
                $installed = gh_get_installed_version();
                $ref = $installed['tag_name'] ?? null;
                if (!$ref) {
                    $ref = gh_get_latest_release($owner, $repo)['tag_name'];
                }
                $integrity = gh_check_integrity($owner, $repo, $ref);
                $integrity['ref'] = $ref;
                $_SESSION['gh_integrity'] = $integrity;
            } elseif ($action === 'reinstall') {
                $installed = gh_get_installed_version();
                $tag = $installed['tag_name'] ?? null;
                if (!$tag) {
                    $tag = gh_get_latest_release($owner, $repo)['tag_name'];
                }
                gh_apply_update($owner, $repo, $tag);
                gh_save_installed_version($tag, $installed ?: []);
                $_SESSION['gh_integrity'] = null;
                flash_set('success', 'バージョン ' . $tag . ' でファイルを再インストールしました。');
                redirect('system_update.php');
            }
        } catch (Exception $ex) {
            $actionError = $ex->getMessage();
        }
    }
}

$installed = gh_get_installed_version();
$requirementsMissing = gh_update_missing_requirements();
$installedTag = $installed['tag_name'] ?? null;
?>

<div class="page-header">
  <h2>システム更新</h2>
</div>

<p class="hint" style="margin-bottom:16px;">
  リポジトリ: <a href="https://github.com/<?= e($owner) ?>/<?= e($repo) ?>" target="_blank"><?= e($owner) ?>/<?= e($repo) ?></a>
  ／ <a href="https://github.com/<?= e($owner) ?>/<?= e($repo) ?>/releases" target="_blank">リリース一覧を見る</a>
</p>

<?php if ($requirementsMissing): ?>
  <div class="msg error">この機能を使うには <?= e(implode(' / ', $requirementsMissing)) ?> が必要です。</div>
<?php endif; ?>
<?php if ($actionError): ?>
  <div class="msg error"><?= e($actionError) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:760px; margin-bottom:20px;">
  <h3 style="margin-top:0; font-size:14px;">現在のバージョン</h3>
  <?php if ($installedTag): ?>
    <p style="font-size:13px;">
      バージョン: <span class="badge status-完了"><?= e($installedTag) ?></span>
      <?php if (!empty($installed['name']) && $installed['name'] !== $installedTag): ?>（<?= e($installed['name']) ?>）<?php endif; ?><br>
      適用日時: <?= e($installed['updated_at'] ?? '-') ?>
    </p>
  <?php else: ?>
    <p class="hint">まだアップデートを適用したことがありません（現在のバージョンは記録されていません）。「最新情報を確認」→「アップデートを適用」を一度実行すると、以後バージョンが記録されます。</p>
  <?php endif; ?>

  <form method="post" style="display:inline-block; margin-right:8px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="check_latest">
    <button type="submit" class="btn secondary">最新リリースを確認</button>
  </form>
  <form method="post" style="display:inline-block;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="list_releases">
    <button type="submit" class="btn secondary">リリース一覧から選ぶ</button>
  </form>

  <?php if ($latest): ?>
    <div style="margin-top:16px; padding:14px; background:#f7f9f6; border-radius:8px;">
      <p style="font-size:13px; margin:0 0 8px;">
        最新リリース: <span class="badge status-進行中"><?= e($latest['tag_name']) ?></span>
        <?php if ($latest['prerelease']): ?><span class="badge inactive">プレリリース</span><?php endif; ?><br>
        名称: <?= e($latest['name']) ?><br>
        公開日: <?= e($latest['published_at']) ?><br>
        <a href="<?= e($latest['html_url']) ?>" target="_blank">GitHubで見る</a>
      </p>
      <?php if (!empty($latest['body'])): ?>
        <details style="font-size:12px; color:var(--text-sub); margin-bottom:10px;">
          <summary style="cursor:pointer;">リリースノートを表示</summary>
          <div style="white-space:pre-wrap; margin-top:6px;"><?= e($latest['body']) ?></div>
        </details>
      <?php endif; ?>

      <?php if ($installedTag && !gh_version_is_newer($latest['tag_name'], $installedTag)): ?>
        <p style="font-size:13px; color:#2e7d32; margin:0;">✓ 現在のバージョンは最新です</p>
      <?php else: ?>
        <form method="post" onsubmit="return confirm('バージョン <?= e($latest['tag_name']) ?> を適用します。config.php とアップロード済みロゴ以外のファイルが上書きされます。よろしいですか？');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="apply_update">
          <input type="hidden" name="tag" value="<?= e($latest['tag_name']) ?>">
          <button type="submit" class="btn" style="margin-top:6px;">バージョン <?= e($latest['tag_name']) ?> を適用</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($releaseList): ?>
    <div style="margin-top:16px;">
      <p style="font-size:12px; font-weight:600; margin-bottom:6px;">リリース一覧（新しい順）</p>
      <table>
        <thead>
          <tr><th>バージョン</th><th>名称</th><th>公開日</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($releaseList as $r): ?>
          <tr>
            <td>
              <?= e($r['tag_name']) ?>
              <?php if ($installedTag === $r['tag_name']): ?><span class="badge status-完了">適用中</span><?php endif; ?>
              <?php if ($r['prerelease']): ?><span class="badge inactive">プレリリース</span><?php endif; ?>
            </td>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['published_at']) ?></td>
            <td>
              <?php if ($installedTag !== $r['tag_name']): ?>
              <form method="post" onsubmit="return confirm('バージョン <?= e($r['tag_name']) ?> を適用します（config.php とロゴは保護されます）。よろしいですか？');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply_update">
                <input type="hidden" name="tag" value="<?= e($r['tag_name']) ?>">
                <button type="submit" class="btn secondary small">このバージョンを適用</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="hint" style="margin-top:8px;">古いバージョンを選んで適用すると、ロールバック（切り戻し）としても利用できます。</p>
    </div>
  <?php endif; ?>
</div>

<div class="form-card" style="max-width:760px;">
  <h3 style="margin-top:0; font-size:14px;">ファイルの整合性確認・再インストール</h3>
  <p class="hint" style="margin-bottom:14px;">
    現在インストールされているバージョン時点のGitHub上のファイルと、サーバー上の実際のファイルを比較します。
    改変・欠落が見つかった場合は「再インストール」でファイルを修復できます（config.php とアップロード済みロゴは対象外です）。
  </p>

  <form method="post" style="display:inline-block; margin-right:8px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="check_integrity">
    <button type="submit" class="btn secondary">ファイルの整合性を確認</button>
  </form>

  <form method="post" style="display:inline-block;" onsubmit="return confirm('現在のバージョンのファイルを再取得して上書きします（config.php とアップロード済みロゴは保護されます）。よろしいですか？');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reinstall">
    <button type="submit" class="btn danger">再インストール（ファイルを修復）</button>
  </form>

  <?php if ($integrity): ?>
    <div style="margin-top:16px;">
      <p style="font-size:13px;">
        確認対象: <span class="badge status-完了"><?= e($integrity['ref']) ?></span> 時点／
        全 <?= (int)$integrity['total'] ?> ファイル中、
        <span style="color:#2e7d32;">一致 <?= (int)$integrity['ok_count'] ?></span>、
        <span style="color:#ef6c00;">変更あり <?= count($integrity['modified']) ?></span>、
        <span style="color:#c0392b;">見つかりません <?= count($integrity['missing']) ?></span>
      </p>

      <?php if (empty($integrity['modified']) && empty($integrity['missing'])): ?>
        <p style="font-size:13px; color:#2e7d32;">✓ 全てのファイルがGitHub上のバージョンと一致しています</p>
      <?php else: ?>
        <?php if (!empty($integrity['modified'])): ?>
          <p style="font-size:12px; font-weight:600; margin-bottom:4px;">変更されているファイル</p>
          <ul style="font-size:12px; color:var(--text-sub); margin-top:0;">
            <?php foreach ($integrity['modified'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (!empty($integrity['missing'])): ?>
          <p style="font-size:12px; font-weight:600; margin-bottom:4px;">見つからないファイル</p>
          <ul style="font-size:12px; color:var(--text-sub); margin-top:0;">
            <?php foreach ($integrity['missing'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
