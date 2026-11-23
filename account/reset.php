<?php
require __DIR__ . '/../app/bootstrap.php';

$raw = is_string($_REQUEST['token'] ?? null) ? $_REQUEST['token'] : '';
if (!rate_limit('reset-view:' . client_ip(), 40, 3600)) exit('Too many attempts. Please try again later.');
$tok = token_find($raw, 'reset');
if (!$tok) {
    flash('error', 'That reset link is invalid or has expired. Please request a new one.');
    redirect('/account/forgot');
}
$u = q('SELECT * FROM users WHERE id = ?', [$tok['user_id']])->fetch();
$error = '';

if (is_post()) {
    csrf_check();
    $pw  = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');
    if ($p = password_problem($pw, $u['email'])) $error = $p;
    elseif (!hash_equals($pw, $pw2)) $error = 'The passwords do not match.';
    else {
        // new password + log out every other session + the email link proves ownership
        q('UPDATE users SET password_hash = ?, session_version = session_version + 1, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
          [hash_password($pw), now(), $u['id']]);
        token_delete_all((int) $u['id'], 'reset');
        audit('user', (int) $u['id'], 'password_reset');
        send_mail($u['email'], 'Your password was changed', "Hi {$u['name']},\n\nYour Chef Jay LeSoul Catering password was just changed. If this wasn't you, reply to this email right away.");
        flash('success', 'Your password has been changed. Please log in.');
        redirect('/account/login');
    }
}

page_start('Choose a New Password | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true]);
app_hero('Account', 'NEW <em>PASSWORD</em>');
?>
<section class="app-sec">
  <div class="app-card">
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <form method="post" action="/account/reset" class="app-form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($raw) ?>">
      <div class="field"><input type="password" name="password" id="password" placeholder=" " minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" required><label for="password">New Password (<?= PASSWORD_MIN ?>+ characters)</label>
        <button type="button" class="pw-toggle" data-pw="password" aria-label="Show password">Show</button></div>
      <div class="field"><input type="password" name="password2" id="password2" placeholder=" " autocomplete="new-password" required><label for="password2">Confirm New Password</label></div>
      <button type="submit" class="btn-red btn-send">Save Password <svg class="chev"><use href="#chev"/></svg></button>
    </form>
  </div>
</section>
<?php page_end();
