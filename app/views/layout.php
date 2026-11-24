<?php
/* Shared header/footer for the PHP pages (same look as the static .html pages). */

const ASSET_V = '5';

function page_start(string $title, string $bodyClass, array $opt = []): void
{
    $desc = $opt['description'] ?? 'Chef Jay LeSoul Catering — Cleveland, OH.';
    $active = $opt['active'] ?? '';
    $nav = [
        '/' => 'Home', '/about' => 'About', '/catering' => 'Catering', '/catering#menu' => 'Menu', '/shop' => 'Shop',
        '/gallery' => 'Gallery', '/cooking-in-the-hood' => 'Cooking in the Hood', '/testimonials' => 'Testimonials', '/contact' => 'Contact',
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <?php if (!empty($opt['noindex'])): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
  <meta name="theme-color" content="#0a0a0a">
  <link rel="icon" href="/assets/images/fav.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Mr+Dafoe&family=Oswald:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= ASSET_V ?>">
  <link rel="stylesheet" href="/assets/css/app.css?v=<?= ASSET_V ?>">
  <?php if (turnstile_enabled() && !empty($opt['captcha'])): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif ?>
</head>
<body class="<?= e($bodyClass) ?>">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
      <linearGradient id="goldGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8a6512"/><stop offset=".35" stop-color="#d4af37"/><stop offset=".55" stop-color="#fff2b3"/><stop offset=".8" stop-color="#d4af37"/><stop offset="1" stop-color="#8a6512"/></linearGradient>
      <linearGradient id="goldGradH" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#9a7418"/><stop offset=".3" stop-color="#e2bf52"/><stop offset=".5" stop-color="#fff2b3"/><stop offset=".75" stop-color="#d4af37"/><stop offset="1" stop-color="#b08a26"/></linearGradient>
      <linearGradient id="goldGradV" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff0b0"/><stop offset=".4" stop-color="#d4af37"/><stop offset="1" stop-color="#8a6512"/></linearGradient>
      <linearGradient id="gRed" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ea1840"/><stop offset="1" stop-color="#6e0615"/></linearGradient>
      <linearGradient id="gBlk" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a3a3a"/><stop offset="1" stop-color="#000"/></linearGradient>
      <symbol id="hat" viewBox="0 0 102 80"><g class="hat-o"><path d="M26 54C12 53 4 44 6 33 8 22 20 16 31 20 33 9 43 3 53 4c9 1 17 7 19 15 10-5 23 1 24 12 1 11-7 20-19 22"/><path d="M26 54v20h51V54M26 63h51M40 63v11M63 63v11"/><path d="M38 34c2-6 4-10 8-13M60 34c-1-5-3-9-6-12"/></g></symbol>
      <symbol id="chev" viewBox="0 0 10 16"><path d="M2.5 2l6 6-6 6"/></symbol>
    </defs>
  </svg>

  <div class="progress" id="progress"></div>

  <header class="site-header" id="header">
    <div class="stage header-inner">
      <a class="logo" href="/" style="--s:40" aria-label="Chef Jay LeSoul Catering home">
        <span class="l-top"><small>CHEF</small><svg class="l-hat"><use href="#hat"/></svg></span>
        <span class="l-main"><b>JAY</b><i>LeSOUL</i></span>
        <span class="l-sub">CATERING</span>
      </a>
      <nav class="nav" id="nav">
        <ul class="nav-list">
          <?php foreach ($nav as $href => $label): ?>
          <li><a href="<?= e($href) ?>"<?= $href === $active ? ' class="active"' : '' ?>><?= e($label) ?></a></li>
          <?php endforeach ?>
        </ul>
      </nav>
      <a class="btn-red header-cta" href="/contact#event">Book Now</a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
    </div>
  </header>

  <main id="main">
<?php
}

function page_end(array $scripts = []): void
{
    ?>
  </main>

  <footer class="site-footer">
    <div class="f-img"><img src="/assets/images/gallery-13.jpeg" alt="" loading="lazy"></div>
    <div class="stage">
      <a class="logo f-logo" href="/" style="--s:60" aria-label="Chef Jay LeSoul Catering home">
        <span class="l-top"><small>CHEF</small><svg class="l-hat"><use href="#hat"/></svg></span>
        <span class="l-main"><b>JAY</b><i>LeSOUL</i></span>
        <span class="l-sub">CATERING</span>
      </a>
      <div class="f-col f-links">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="/"><i class="fa fa-angle-right"></i>Home</a></li>
          <li><a href="/about"><i class="fa fa-angle-right"></i>About</a></li>
          <li><a href="/catering"><i class="fa fa-angle-right"></i>Catering</a></li>
          <li><a href="/catering#menu"><i class="fa fa-angle-right"></i>Menu</a></li>
          <li><a href="/gallery"><i class="fa fa-angle-right"></i>Gallery</a></li>
          <li><a href="/cooking-in-the-hood"><i class="fa fa-angle-right"></i>Cooking in the Hood</a></li>
          <li><a href="/testimonials"><i class="fa fa-angle-right"></i>Testimonials</a></li>
          <li><a href="/contact"><i class="fa fa-angle-right"></i>Contact</a></li>
        </ul>
      </div>
      <div class="f-col f-contact">
        <h4>Contact Us</h4>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.1.37 2.3.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg><a href="tel:+12166737299">(216) 673-7299</a></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/></svg><a href="mailto:chef@jaylesoulcatering.com">chef@jaylesoulcatering.com</a></li>
          <li><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>Cleveland, OH</li>
        </ul>
        <div class="socials">
          <a href="https://www.facebook.com/profile.php?id=61592830652017" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 8h3V4h-3c-2.8 0-4.5 1.8-4.5 4.6V11H7v4h2.5v7h4v-7H16l.5-4h-3V9c0-.6.4-1 1-1z"/></svg></a>
          <a href="https://www.instagram.com/jamesthomasgarrett/" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg></a>
          <a href="https://www.youtube.com/@jamesthomasgarrettiii3124" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .4-3.8 31 31 0 0 0-.4-3.8zM10 15V9l5.2 3z"/></svg></a>
        </div>
      </div>
    </div>
  </footer>

  <a href="#" class="to-top" id="toTop" aria-label="Back to top">↑</a>
  <script src="/assets/js/script.js?v=<?= ASSET_V ?>"></script>
  <script src="/assets/js/app.js?v=<?= ASSET_V ?>"></script>
  <?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>"></script><?php endforeach ?>
</body>
</html>
<?php
}

/** Small page hero used by account pages. */
function app_hero(string $eyebrow, string $titleHtml, string $script = ''): void
{
    ?>
    <section class="phero app-hero">
      <svg class="phero-streak" viewBox="0 0 260 500" preserveAspectRatio="none" aria-hidden="true">
        <polygon points="150,0 236,0 110,500 24,500" fill="url(#goldGradV)" opacity=".45"/>
      </svg>
      <div class="stage">
        <span class="eyebrow"><?= e($eyebrow) ?></span>
        <h1><?= $titleHtml ?></h1>
        <?php if ($script): ?><p class="phero-script"><?= e($script) ?></p><?php endif ?>
      </div>
    </section>
<?php
}
