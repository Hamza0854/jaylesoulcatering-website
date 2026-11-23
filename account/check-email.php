<?php
require __DIR__ . '/../app/bootstrap.php';
$email = $_SESSION['check_email'] ?? '';

page_start('Check Your Email | Chef Jay LeSoul Catering', 'page-app', ['noindex' => true]);
app_hero('Almost done', 'CHECK YOUR <em>EMAIL</em>');
?>
<section class="app-sec">
  <div class="app-card app-msg">
    <?= flashes_html() ?>
    <p>We sent a confirmation link to <strong><?= e($email ?: 'your email address') ?></strong>.</p>
    <p>Open it to activate your account, then log in to write your review. The link works for 48 hours.</p>
    <p class="muted">Nothing there? Check your spam or promotions folder. You can request a new link from the <a href="/account/login">login page</a>.</p>
  </div>
</section>
<?php page_end();
