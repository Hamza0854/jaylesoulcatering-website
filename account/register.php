<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect('/account');

$errors = [];
$old = ['name' => '', 'email' => ''];

if (is_post()) {
    csrf_check();
    $old['name']  = post_str('name', 80);
    $old['email'] = normalize_email(post_str('email', 190));
    $pw  = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password2'] ?? '');

    try {
        bot_check('register');
        if (!rate_limit('register:' . client_ip(), 5, 3600)) throw new UserError('Too many sign-ups from your network. Please try again in an hour.');

        if (mb_strlen($old['name']) < 2) $errors['name'] = 'Please enter your name.';
        if (!valid_email($old['email'])) $errors['email'] = 'Please enter a valid email address.';
        if ($p = password_problem($pw, $old['email'])) $errors['password'] = $p;
        elseif (!hash_equals($pw, $pw2)) $errors['password2'] = 'The passwords do not match.';
        if (empty($_POST['terms'])) $errors['terms'] = 'Please agree to the review guidelines.';

        if (!$errors && q('SELECT id FROM users WHERE email = ?', [$old['email']])->fetch()) {
            $errors['email'] = 'An account with this email already exists. Please log in instead.';
        }
        if (!$errors) {
            q('INSERT INTO users (name, email, password_hash, created_at) VALUES (?,?,?,?)',
              [$old['name'], $old['email'], hash_password($pw), now()]);
            $u = q('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()])->fetch();
            audit('user', (int) $u['id'], 'register', $u['email']);
            login_user($u);
            flash('success', 'Welcome, ' . $u['name'] . '! Your account is ready — share your experience below.');
            redirect('/account/submit');
        }
    } catch (UserError $e) {
        $errors['form'] = $e->getMessage();
    }
}

page_start('Create an Account | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true, 'captcha' => true]);
app_hero('Reviews', 'CREATE AN <em>ACCOUNT</em>', 'Share your Chef Jay experience');
?>
<section class="app-sec">
  <div class="app-card">
    <?= flashes_html() ?>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif ?>
    <form method="post" action="/account/register" class="app-form" novalidate>
      <?= csrf_field() ?>
      <div class="field<?= isset($errors['name']) ? ' error' : '' ?>" data-error="<?= e($errors['name'] ?? '') ?>">
        <input type="text" name="name" id="name" placeholder=" " maxlength="80" autocomplete="name" required value="<?= e($old['name']) ?>"><label for="name">Your Name (shown on your review)</label></div>
      <div class="field<?= isset($errors['email']) ? ' error' : '' ?>" data-error="<?= e($errors['email'] ?? '') ?>">
        <input type="email" name="email" id="email" placeholder=" " maxlength="190" autocomplete="email" required value="<?= e($old['email']) ?>"><label for="email">Email Address (never shown)</label></div>
      <div class="field<?= isset($errors['password']) ? ' error' : '' ?>" data-error="<?= e($errors['password'] ?? '') ?>">
        <input type="password" name="password" id="password" placeholder=" " minlength="<?= PASSWORD_MIN ?>" autocomplete="new-password" required><label for="password">Password (<?= PASSWORD_MIN ?>+ characters)</label>
        <button type="button" class="pw-toggle" data-pw="password" aria-label="Show password">Show</button></div>
      <div class="field<?= isset($errors['password2']) ? ' error' : '' ?>" data-error="<?= e($errors['password2'] ?? '') ?>">
        <input type="password" name="password2" id="password2" placeholder=" " autocomplete="new-password" required><label for="password2">Confirm Password</label></div>
      <label class="check<?= isset($errors['terms']) ? ' error' : '' ?>"><input type="checkbox" name="terms" value="1" required>
        <span>I'll only post honest reviews of my own experience. Reviews are checked before they're published.</span></label>
      <?= bot_fields('register') ?>
      <button type="submit" class="btn-red btn-send">Create Account <svg class="chev"><use href="#chev"/></svg></button>
      <p class="app-alt">Already have an account? <a href="/account/login">Log in</a></p>
    </form>
  </div>
</section>
<?php page_end();
