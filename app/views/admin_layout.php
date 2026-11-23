<?php
/* Admin panel layout. */

function admin_start(string $title, ?array $admin, string $active = ''): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    $pending = 0;
    if ($admin) {
        try { $pending = (int) q("SELECT COUNT(*) FROM testimonials WHERE status = 'pending'")->fetchColumn(); } catch (Throwable) {}
    }
    $links = ['reviews' => ['/admin', 'Reviews'], 'add' => ['/admin/edit', 'Add Testimonial'],
              'users' => ['/admin/users', 'Customers'], 'security' => ['/admin/security', 'Security']];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Admin · Chef Jay LeSoul</title>
  <link rel="icon" href="/assets/images/fav.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@400;500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/admin.css?v=<?= ASSET_V ?>">
  <?php if (turnstile_enabled() && !$admin): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif ?>
</head>
<body class="adm<?= $admin ? '' : ' adm-guest' ?>">
  <header class="adm-top">
    <a class="adm-brand" href="/admin"><b>JAY</b><i>LeSOUL</i><span>Admin</span></a>
    <?php if ($admin): ?>
    <nav class="adm-nav">
      <?php foreach ($links as $key => [$href, $label]): ?>
        <a href="<?= $href ?>"<?= $key === $active ? ' class="on"' : '' ?>><?= e($label) ?><?= $key === 'reviews' && $pending ? ' <em>' . $pending . '</em>' : '' ?></a>
      <?php endforeach ?>
    </nav>
    <div class="adm-me">
      <a href="/testimonials" target="_blank" rel="noopener">View site ↗</a>
      <form method="post" action="/admin/logout"><?= csrf_field() ?><button type="submit">Log out</button></form>
    </div>
    <?php endif ?>
  </header>
  <main class="adm-main">
    <?= flashes_html() ?>
<?php
}

function admin_end(array $scripts = []): void
{
    ?>
  </main>
  <?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>"></script><?php endforeach ?>
  <script src="/assets/js/admin.js?v=<?= ASSET_V ?>"></script>
</body>
</html>
<?php
}
