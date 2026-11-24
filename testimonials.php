<?php
require __DIR__ . '/app/bootstrap.php';

$kind = $_GET['type'] ?? '';
$kind = is_string($kind) && isset(KINDS[$kind]) ? $kind : null;
$page = max(1, min(500, (int) ($_GET['page'] ?? 1)));

try {
    [$items, $total] = approved_testimonials($kind, $page);
    $summary = rating_summary();
    $counts  = kind_counts();
    $dbError = false;
} catch (PDOException $e) {
    error_log('testimonials page: ' . $e->getMessage());
    [$items, $total, $summary, $counts, $dbError] = [[], 0, ['avg' => 0, 'count' => 0, 'dist' => []], ['all' => 0], true];
}
$pages = max(1, (int) ceil($total / PER_PAGE));
$writeUrl = '/review';

page_start('Testimonials & Reviews | Chef Jay LeSoul Catering — Cleveland, OH', 'page-testi', [
    'description' => 'Star ratings, video, photo and written reviews from customers of Chef Jay LeSoul Catering across Greater Cleveland.',
    'active' => '/testimonials',
]);
?>
    <section class="phero">
      <svg class="phero-streak" viewBox="0 0 260 500" preserveAspectRatio="none" aria-hidden="true">
        <polygon points="150,0 236,0 110,500 24,500" fill="url(#goldGradV)" opacity=".55"/>
        <polygon points="212,0 236,0 110,500 86,500" fill="#fff6cc" opacity=".5"/>
      </svg>
      <svg class="phero-acc" viewBox="0 0 300 120" aria-hidden="true">
        <polygon points="160,0 214,0 187,47" fill="url(#gRed)"/>
        <polygon points="232,0 300,0 300,58" fill="url(#goldGradV)"/>
        <polygon points="206,60 260,60 233,106" fill="url(#gBlk)"/>
      </svg>

      <div class="stage testi-hero">
        <div>
          <span class="eyebrow reveal">Testimonials</span>
          <h1 class="reveal" data-delay="80">WHAT PEOPLE <em>ARE SAYING</em></h1>
          <p class="phero-script reveal" data-delay="160">Real love. Real flavor.
            <svg class="brush" viewBox="0 0 440 40" preserveAspectRatio="none" aria-hidden="true"><path d="M4 30C90 18 230 8 436 5 300 14 170 22 70 34 40 37 14 38 4 30Z" fill="url(#goldGrad)"/></svg>
          </p>
          <div class="phero-copy reveal" data-delay="200"><p>Hear it straight from the families, neighbors and event hosts we've had the honor to feed across Greater Cleveland.</p></div>
        </div>

        <aside class="r-summary reveal" data-delay="150" aria-label="Rating summary">
          <?php if ($summary['count']): ?>
            <div class="r-top">
              <b class="r-avg"><?= e(number_format($summary['avg'], 1)) ?></b>
              <div><?= stars_html($summary['avg'], 'r-stars') ?><small>Based on <?= (int) $summary['count'] ?> review<?= $summary['count'] === 1 ? '' : 's' ?></small></div>
            </div>
            <ul class="r-bars">
              <?php foreach ($summary['dist'] as $star => $n): $pct = $summary['count'] ? round($n / $summary['count'] * 100) : 0; ?>
              <li><span><?= $star ?> ★</span><i><em style="width:<?= $pct ?>%"></em></i><span><?= $n ?></span></li>
              <?php endforeach ?>
            </ul>
          <?php else: ?>
            <div class="r-top"><div><?= stars_html(5, 'r-stars') ?><small>Be the first to leave a star rating!</small></div></div>
          <?php endif ?>
          <a href="<?= e($writeUrl) ?>" class="btn-red r-write">Write a Review <svg class="chev"><use href="#chev"/></svg></a>
          <p class="r-note">No account needed · every review is approved by our team</p>
        </aside>
      </div>

      <div class="stage testi-filters">
        <nav class="filters reveal" data-delay="240" aria-label="Filter testimonials">
          <a class="filter<?= $kind === null ? ' active' : '' ?>" href="/testimonials">All<?= !empty($counts['all']) ? ' (' . $counts['all'] . ')' : '' ?></a>
          <?php foreach (KINDS as $k => $label): ?>
          <a class="filter<?= $kind === $k ? ' active' : '' ?>" href="/testimonials?type=<?= $k ?>"><?= e($label) ?><?= !empty($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?></a>
          <?php endforeach ?>
        </nav>
      </div>

      <svg class="phero-swoosh" viewBox="0 0 430 56" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 56V20C120 38 290 46 430 56Z" fill="url(#goldGradH)"/>
        <path d="M0 14C130 32 300 42 430 54" fill="none" stroke="#fff6cc" stroke-width="2" opacity=".85"/>
      </svg>
    </section>

    <section class="testi testi-all">
      <div class="stage">
        <?php if ($dbError): ?>
          <p class="t-empty">Reviews are temporarily unavailable. Please check back soon.</p>
        <?php elseif (!$items): ?>
          <p class="t-empty">No <?= $kind ? e(strtolower(KINDS[$kind])) : 'reviews' ?> yet — <a href="<?= e($writeUrl) ?>">be the first to write one</a>.</p>
        <?php else: ?>
          <div class="t-grid">
            <?php foreach ($items as $i => $t) echo testimonial_card($t, ($i % 3) * 80); ?>
          </div>
        <?php endif ?>

        <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
          <?php for ($p = 1; $p <= $pages; $p++):
              $qs = http_build_query(array_filter(['type' => $kind, 'page' => $p > 1 ? $p : null])); ?>
            <a href="/testimonials<?= $qs ? '?' . e($qs) : '' ?>"<?= $p === $page ? ' class="active" aria-current="page"' : '' ?>><?= $p ?></a>
          <?php endfor ?>
        </nav>
        <?php endif ?>

        <div class="t-share reveal">
          <div><h3>FED BY <em>CHEF JAY?</em></h3><p>Leave a star rating, a few words, photos or a short video. Every review is checked by our team before it goes live.</p></div>
          <a href="<?= e($writeUrl) ?>" class="btn-red">Write a Review <svg class="chev"><use href="#chev"/></svg></a>
        </div>
      </div>
    </section>

    <section class="cta">
      <div class="cta-media"><img src="/assets/images/home-img-4.jpeg" alt="Seasoned grilled shrimp" loading="lazy"></div>
      <svg class="cta-swoosh" viewBox="0 0 404 130" aria-hidden="true">
        <path class="sw-line" pathLength="100" stroke-width="1.2" opacity=".7" d="M-74 130Q10 44 196 8"/>
        <path class="sw-line" pathLength="100" stroke-width="2.4" d="M-44 130Q36 36 226 3"/>
        <path class="sw-ribbon" d="M-8 131Q70 28 250 -1L3000 -1 3000 3 262 3Q96 34 14 131Z"/>
      </svg>
      <div class="stage">
        <div class="cta-copy reveal">
          <h2>LET US CATER<br>YOUR NEXT EVENT</h2>
          <p>Birthdays<span>|</span>Family Gatherings<span>|</span>Corporate Events<br>Community Events<span>|</span>Private In-Home Catering</p>
        </div>
        <a href="/contact#event" class="btn-quote">GET A QUOTE <svg class="chev"><use href="#chev"/></svg></a>
      </div>
    </section>
<?php page_end();
