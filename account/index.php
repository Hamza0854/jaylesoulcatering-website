<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_user();

$pwError = '';
if (is_post() && ($_POST['action'] ?? '') === 'password') {
    csrf_check();
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['password'] ?? '');
    if (!rate_limit('pwchange:' . $u['id'], 5, 900)) $pwError = 'Too many attempts. Please wait 15 minutes.';
    elseif (!password_verify($cur, $u['password_hash'])) $pwError = 'Your current password is not correct.';
    elseif ($p = password_problem($new, $u['email'])) $pwError = $p;
    else {
        q('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [hash_password($new), $u['id']]);
        $u = q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch();
        login_user($u);   // stay logged in here, every other device is logged out
        audit('user', (int) $u['id'], 'password_change');
        flash('success', 'Password updated. You were logged out on other devices.');
        redirect('/account');
    }
}

$mine = with_media(q('SELECT * FROM testimonials WHERE user_id = ? ORDER BY created_at DESC', [$u['id']])->fetchAll());

page_start('My Account | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true]);
app_hero('My Account', 'HI, <em>' . e(mb_strtoupper(explode(' ', $u['name'])[0])) . '</em>');
?>
<section class="app-sec app-wide">
  <?= flashes_html() ?>
  <div class="acct-head">
    <h2>My Reviews</h2>
    <div class="acct-actions">
      <a href="/account/submit" class="btn-red">Write a Review <svg class="chev"><use href="#chev"/></svg></a>
      <form method="post" action="/account/logout"><?= csrf_field() ?><button class="btn-gold" type="submit">Log Out</button></form>
    </div>
  </div>

  <?php if (!$mine): ?>
    <div class="app-card app-msg"><p>You haven't written a review yet. <a href="/account/submit">Write your first one</a> — it only takes a minute.</p></div>
  <?php endif ?>
  <div class="my-list">
    <?php foreach ($mine as $t): ?>
      <article class="my-item">
        <div class="my-top"><?= $t['rating'] ? stars_html((float) $t['rating']) : '' ?><?= status_badge($t['status']) ?></div>
        <?php if ($t['title']): ?><h3><?= e($t['title']) ?></h3><?php endif ?>
        <p><?= nl2br(e(mb_strimwidth((string) $t['body'], 0, 400, '…'))) ?></p>
        <small class="muted">Sent <?= e(fmt_date($t['created_at'])) ?> · <?= count($t['media']) ?> attachment<?= count($t['media']) === 1 ? '' : 's' ?></small>
        <?php if ($t['status'] === 'rejected' && $t['admin_note']): ?><p class="my-note">Note from our team: <?= e($t['admin_note']) ?></p><?php endif ?>
      </article>
    <?php endforeach ?>
  </div>

  <details class="app-card acct-pw" <?= $pwError ? 'open' : '' ?>>
    <summary>Change password</summary>
    <?php if ($pwError): ?><div class="alert alert-error" role="alert"><?= e($pwError) ?></div><?php endif ?>
    <form method="post" action="/account" class="app-form" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><input type="password" name="current" id="current" placeholder=" " autocomplete="current-password" required><label for="current">Current Password</label></div>
      <div class="field"><input type="password" name="password" id="password" placeholder=" " autocomplete="new-password" required><label for="password">New Password (<?= PASSWORD_MIN ?>+ characters)</label></div>
      <button type="submit" class="btn-red btn-send">Update Password</button>
    </form>
  </details>
</section>
<?php page_end();
