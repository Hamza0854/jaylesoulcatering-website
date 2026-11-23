<?php
require __DIR__ . '/../app/bootstrap.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null, '/account');
if (current_user()) redirect($next);

$error = '';
$email = '';
$unverified = false;

if (is_post()) {
    csrf_check();
    $email = normalize_email(post_str('email', 190));
    $pw = (string) ($_POST['password'] ?? '');
    $ipKey = 'ulogin-ip:' . client_ip();
    $emKey = 'ulogin-em:' . $email;

    try {
        bot_check('login', 1);
        if (rate_blocked($ipKey, 30, 900) || rate_blocked($emKey, 6, 900)) {
            throw new UserError('Too many failed attempts. Please wait 15 minutes and try again, or reset your password.');
        }
        $u = valid_email($email) ? (q('SELECT * FROM users WHERE email = ?', [$email])->fetch() ?: null) : null;
        if (!verify_password_timing_safe($u['password_hash'] ?? null, $pw)) {
            rate_hit($ipKey, 900);
            rate_hit($emKey, 900);
            throw new UserError('Wrong email or password.');
        }
        if ($u['status'] !== 'active') throw new UserError('This account has been suspended. Please contact us if you think this is a mistake.');

        if (!$u['email_verified_at']) {
            $unverified = true;
            if (!empty($_POST['resend']) && rate_limit('resend:' . $u['id'], 3, 3600)) {
                send_verification_email($u);
                $_SESSION['check_email'] = $u['email'];
                redirect('/account/check-email');
            }
            throw new UserError('Please confirm your email first — open the link we emailed you.');
        }
        rate_clear($emKey);
        maybe_rehash('users', $u, $pw);
        login_user($u);
        redirect($next);
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

page_start('Log In | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true, 'captcha' => true]);
app_hero('Reviews', 'LOG <em>IN</em>', 'Welcome back');
?>
<section class="app-sec">
  <div class="app-card">
    <?= flashes_html() ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
    <form method="post" action="/account/login" class="app-form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field"><input type="email" name="email" id="email" placeholder=" " autocomplete="email" required value="<?= e($email) ?>"><label for="email">Email Address</label></div>
      <div class="field"><input type="password" name="password" id="password" placeholder=" " autocomplete="current-password" required><label for="password">Password</label>
        <button type="button" class="pw-toggle" data-pw="password" aria-label="Show password">Show</button></div>
      <?php if ($unverified): ?>
        <label class="check"><input type="checkbox" name="resend" value="1"><span>Email me a new confirmation link</span></label>
      <?php endif ?>
      <?= bot_fields('login') ?>
      <button type="submit" class="btn-red btn-send">Log In <svg class="chev"><use href="#chev"/></svg></button>
      <p class="app-alt"><a href="/account/forgot">Forgot your password?</a></p>
      <p class="app-alt">New here? <a href="/account/register">Create an account</a> to write a review.</p>
    </form>
  </div>
</section>
<?php page_end();
