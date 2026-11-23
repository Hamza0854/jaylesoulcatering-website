<?php
require __DIR__ . '/../app/bootstrap.php';

$error = '';
$sent = false;
if (is_post()) {
    csrf_check();
    $email = normalize_email(post_str('email', 190));
    try {
        bot_check('forgot');
        if (!rate_limit('forgot-ip:' . client_ip(), 5, 3600)) throw new UserError('Too many requests. Please try again in an hour.');
        if (valid_email($email) && rate_limit('forgot-em:' . $email, 3, 3600)) {
            $u = q("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email])->fetch();
            if ($u) {
                $token = token_create((int) $u['id'], 'reset', 3600);
                $link = rtrim(cfg('site_url'), '/') . '/account/reset?token=' . $token;
                send_mail($u['email'], 'Reset your password — Chef Jay LeSoul Catering',
                    "Hi {$u['name']},\n\nUse this link to choose a new password (works for 1 hour):\n\n$link\n\n"
                    . "If you didn't ask for this, ignore this email — your password stays the same.");
            }
        }
        $sent = true;   // same answer whether or not the account exists
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

page_start('Forgot Password | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true, 'captcha' => true]);
app_hero('Account', 'FORGOT <em>PASSWORD?</em>');
?>
<section class="app-sec">
  <div class="app-card">
    <?php if ($sent): ?>
      <div class="alert alert-success" role="status">If an account exists for that email, a reset link is on its way. Check your inbox (and spam folder).</div>
      <p class="app-alt"><a href="/account/login">Back to log in</a></p>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
      <form method="post" action="/account/forgot" class="app-form" novalidate>
        <?= csrf_field() ?>
        <p class="muted">Enter your email and we'll send you a link to choose a new password.</p>
        <div class="field"><input type="email" name="email" id="email" placeholder=" " autocomplete="email" required><label for="email">Email Address</label></div>
        <?= bot_fields('forgot') ?>
        <button type="submit" class="btn-red btn-send">Send Reset Link <svg class="chev"><use href="#chev"/></svg></button>
        <p class="app-alt"><a href="/account/login">Back to log in</a></p>
      </form>
    <?php endif ?>
  </div>
</section>
<?php page_end();
