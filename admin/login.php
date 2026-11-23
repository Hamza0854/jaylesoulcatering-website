<?php
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';

if (current_admin()) redirect('/admin');

$error = '';
$email = '';
if (is_post()) {
    csrf_check();
    $email = normalize_email(post_str('email', 190));
    $pw = (string) ($_POST['password'] ?? '');
    $ipKey = 'alogin-ip:' . client_ip();
    $emKey = 'alogin-em:' . $email;
    try {
        bot_check('admin-login', 1);
        // strict: 5 wrong tries per account / 10 per network in 30 minutes
        if (rate_blocked($ipKey, 10, 1800) || rate_blocked($emKey, 5, 1800)) {
            throw new UserError('Too many failed attempts. Login is locked for 30 minutes.');
        }
        $a = valid_email($email) ? (q("SELECT * FROM admins WHERE email = ? AND status = 'active'", [$email])->fetch() ?: null) : null;
        if (!verify_password_timing_safe($a['password_hash'] ?? null, $pw)) {
            rate_hit($ipKey, 1800);
            rate_hit($emKey, 1800);
            audit('admin', $a['id'] ?? null, 'login_failed', $email);
            throw new UserError('Wrong email or password.');
        }
        maybe_rehash('admins', $a, $pw);
        rate_clear($emKey);
        if ((int) $a['totp_enabled']) {
            session_regenerate_id(true);
            $_SESSION['a_pending'] = (int) $a['id'];
            $_SESSION['a_pending_at'] = time();
            redirect('/admin/verify');
        }
        login_admin($a);          // no 2FA yet → require_admin() sends them to set it up
        redirect('/admin');
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

admin_start('Log in', null);
?>
<div class="adm-auth">
  <h1>Admin <em>Login</em></h1>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="/admin/login" class="adm-form" autocomplete="on">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <?= bot_fields('admin-login') ?>
    <button type="submit" class="adm-btn adm-btn-red">Continue</button>
  </form>
</div>
<?php admin_end();
