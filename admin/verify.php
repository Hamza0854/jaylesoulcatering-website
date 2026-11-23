<?php
/* Step 2 of admin login: 6-digit authenticator code (or a one-time recovery code). */
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';

$id = (int) ($_SESSION['a_pending'] ?? 0);
if (!$id || time() - (int) ($_SESSION['a_pending_at'] ?? 0) > 300) {
    unset($_SESSION['a_pending'], $_SESSION['a_pending_at']);
    flash('error', 'Please log in again.');
    redirect('/admin/login');
}
$a = q("SELECT * FROM admins WHERE id = ? AND status = 'active'", [$id])->fetch();
if (!$a) redirect('/admin/login');

$error = '';
if (is_post()) {
    csrf_check();
    $code = post_str('code', 20);
    $key = 'a2fa:' . $id;
    if (rate_blocked($key, 5, 900)) {
        unset($_SESSION['a_pending'], $_SESSION['a_pending_at']);
        audit('admin', $id, '2fa_locked');
        flash('error', 'Too many wrong codes. Please wait 15 minutes and log in again.');
        redirect('/admin/login');
    }
    $step = totp_match((string) $a['totp_secret'], $code, $a['totp_last_step'] !== null ? (int) $a['totp_last_step'] : null);
    if ($step !== null) {
        q('UPDATE admins SET totp_last_step = ? WHERE id = ?', [$step, $id]);
        rate_clear($key);
        login_admin($a);
        redirect('/admin');
    }
    $left = recovery_code_use($a['recovery_codes'], $code);
    if ($left !== null) {
        q('UPDATE admins SET recovery_codes = ? WHERE id = ?', [$left, $id]);
        rate_clear($key);
        login_admin($a);
        audit('admin', $id, 'recovery_code_used', count(json_decode($left, true)) . ' left');
        flash('info', 'You used a recovery code. ' . count(json_decode($left, true)) . ' left — create new ones on the Security page if you are running low.');
        redirect('/admin');
    }
    rate_hit($key, 900);
    $error = 'That code is not correct. Check your authenticator app and try again.';
}

admin_start('Two-factor check', null);
?>
<div class="adm-auth">
  <h1>Enter your <em>code</em></h1>
  <p class="muted">Open your authenticator app and type the 6-digit code for <b>Chef Jay LeSoul Admin</b>.</p>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="/admin/verify" class="adm-form">
    <?= csrf_field() ?>
    <label>Authentication code<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus class="code-input"></label>
    <button type="submit" class="adm-btn adm-btn-red">Verify</button>
    <p class="muted small">Lost your phone? Type one of your recovery codes instead (format XXXXX-XXXXX).</p>
  </form>
</div>
<?php admin_end();
