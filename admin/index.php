<?php
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';
$admin = require_admin();

$tabs = ['pending' => 'Waiting', 'approved' => 'Published', 'rejected' => 'Rejected', 'all' => 'All'];
$tab = $_GET['status'] ?? 'pending';
$tab = is_string($tab) && isset($tabs[$tab]) ? $tab : 'pending';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;

$counts = ['all' => 0];
foreach (q('SELECT status, COUNT(*) c FROM testimonials GROUP BY status')->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['c'];
    $counts['all'] += (int) $r['c'];
}
$where = $tab === 'all' ? '1=1' : 't.status = ?';
$args = $tab === 'all' ? [] : [$tab];
$total = (int) q("SELECT COUNT(*) FROM testimonials t WHERE $where", $args)->fetchColumn();
$rows = with_media(q("SELECT t.*, (b.email IS NOT NULL) AS email_blocked FROM testimonials t
                      LEFT JOIN blocked_emails b ON b.email = t.author_email WHERE $where
                      ORDER BY t.created_at DESC, t.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args)->fetchAll());
$summary = rating_summary();

admin_start('Reviews', $admin, 'reviews');
?>
<div class="adm-head">
  <h1>Testimonials</h1>
  <div class="adm-stat"><?= stars_html($summary['avg'] ?: 0, 'mini-stars') ?> <b><?= e(number_format($summary['avg'], 1)) ?></b> average · <?= (int) $summary['count'] ?> rated reviews published</div>
  <a class="adm-btn adm-btn-gold" href="/admin/edit">+ Add Testimonial</a>
</div>

<nav class="adm-tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="/admin?status=<?= $k ?>"<?= $k === $tab ? ' class="on"' : '' ?>><?= e($label) ?> <span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach ?>
</nav>

<?php if (!$rows): ?><p class="adm-empty">Nothing here. <?= $tab === 'pending' ? 'No reviews are waiting for approval 🎉' : '' ?></p><?php endif ?>

<div class="adm-list">
<?php foreach ($rows as $t): ?>
  <article class="adm-card is-<?= e($t['status']) ?>">
    <div class="adm-card-main">
      <div class="adm-meta">
        <?= status_badge($t['status']) ?>
        <?php if ($t['featured']): ?><span class="badge-st st-featured">★ Featured</span><?php endif ?>
        <span class="tag"><?= e(ucfirst($t['kind'])) ?></span>
        <span class="tag"><?= $t['source'] === 'customer' ? 'Customer' : 'Added by admin' ?></span>
        <small>#<?= (int) $t['id'] ?> · <?= e(fmt_date($t['created_at'])) ?></small>
      </div>
      <?= $t['rating'] ? stars_html((float) $t['rating'], 'mini-stars') : '<span class="muted small">No rating</span>' ?>
      <?php if ($t['title']): ?><h3><?= e($t['title']) ?></h3><?php endif ?>
      <?php if ($t['body']): ?><p class="adm-body"><?= nl2br(e($t['body'])) ?></p><?php endif ?>
      <p class="adm-who"><b><?= e($t['author_name']) ?></b>
        <?php if ($t['author_email']): ?> · <a href="mailto:<?= e($t['author_email']) ?>"><?= e($t['author_email']) ?></a><?= $t['email_blocked'] ? ' <span class="badge-st st-rejected">blocked</span>' : '' ?><?php endif ?>
        <?= $t['author_location'] ? ' · ' . e($t['author_location']) : '' ?>
        <?= isset(EVENT_TYPES[$t['event_type'] ?? '']) ? ' · ' . e(EVENT_TYPES[$t['event_type']]) : '' ?></p>
      <?php if ($t['admin_note']): ?><p class="adm-note">Note: <?= e($t['admin_note']) ?></p><?php endif ?>

      <?php if ($t['media'] || $t['embed_url']): ?>
      <div class="adm-media">
        <?php foreach ($t['media'] as $m): ?>
          <?php if ($m['type'] === 'image'): ?>
            <a href="<?= e(media_url($m)) ?>" target="_blank" rel="noopener"><img src="<?= e(media_url($m)) ?>" alt="" loading="lazy"></a>
          <?php else: ?>
            <video src="<?= e(media_url($m)) ?>" controls preload="metadata" playsinline></video>
          <?php endif ?>
        <?php endforeach ?>
        <?php if ($t['embed_url']): ?><a class="adm-link" href="<?= e($t['embed_url']) ?>" target="_blank" rel="noopener">▶ <?= e(ucfirst((string) $t['embed_provider'])) ?> video</a><?php endif ?>
      </div>
      <?php endif ?>
    </div>

    <div class="adm-actions">
      <?php if ($t['status'] !== 'approved'): ?>
        <form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="approve">
          <button class="adm-btn adm-btn-green" type="submit">✓ Approve</button></form>
      <?php endif ?>
      <?php if ($t['status'] !== 'rejected'): ?>
        <form method="post" action="/admin/action" class="reject-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="reject">
          <input type="text" name="note" maxlength="255" placeholder="Reason (optional, private note)">
          <button class="adm-btn" type="submit"><?= $t['status'] === 'approved' ? 'Unpublish' : '✕ Reject' ?></button></form>
      <?php endif ?>
      <form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="feature">
        <button class="adm-btn" type="submit"><?= $t['featured'] ? 'Unfeature' : '★ Feature (show first)' ?></button></form>
      <a class="adm-btn" href="/admin/edit?id=<?= (int) $t['id'] ?>">Edit</a>
      <form method="post" action="/admin/action"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="do" value="delete">
        <button class="adm-btn adm-btn-danger" type="submit" data-confirm="Delete this testimonial and its files forever?">Delete</button></form>
      <?php if ($t['author_email'] && !$t['email_blocked']): ?>
        <form method="post" action="/admin/users"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($t['author_email']) ?>"><input type="hidden" name="do" value="block"><input type="hidden" name="back" value="reviews">
          <button class="adm-btn adm-btn-danger" type="submit" data-confirm="Block <?= e($t['author_email']) ?>? All reviews from this email are unpublished and it can't send new ones.">Block this email</button></form>
      <?php endif ?>
    </div>
  </article>
<?php endforeach ?>
</div>

<?php if ($total > $per): ?>
<nav class="adm-pager">
  <?php for ($p = 1; $p <= (int) ceil($total / $per); $p++): ?>
    <a href="/admin?status=<?= $tab ?>&amp;page=<?= $p ?>"<?= $p === $page ? ' class="on"' : '' ?>><?= $p ?></a>
  <?php endfor ?>
</nav>
<?php endif ?>
<?php admin_end();
