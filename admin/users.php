<?php
/* Reviewers: everyone who sent a review (by email), with block / unblock. */
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $email = normalize_email(post_str('email', 190));
    $do = (string) ($_POST['do'] ?? '');
    if (valid_email($email) && $do === 'block') {
        q('INSERT IGNORE INTO blocked_emails (email, reason, created_at) VALUES (?,?,?)', [$email, post_str('reason', 255) ?: null, now()]);
        // unpublish everything from this email
        foreach (q("SELECT id FROM testimonials WHERE author_email = ? AND status <> 'rejected'", [$email])->fetchAll() as $r) {
            q("UPDATE testimonials SET status = 'rejected', reviewed_at = ?, reviewed_by = ? WHERE id = ?", [now(), $admin['id'], $r['id']]);
            media_set_public((int) $r['id'], false);
        }
        audit('admin', (int) $admin['id'], 'email_block', $email);
        flash('success', "$email is blocked and its reviews are unpublished.");
    } elseif (valid_email($email) && $do === 'unblock') {
        q('DELETE FROM blocked_emails WHERE email = ?', [$email]);
        audit('admin', (int) $admin['id'], 'email_unblock', $email);
        flash('success', "$email can send reviews again (its old reviews stay unpublished until you approve them).");
    }
    redirect(($_POST['back'] ?? '') === 'reviews' ? '/admin' : '/admin/users');
}

$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$where = 'author_email IS NOT NULL';
$args = [];
if ($search !== '') {
    $where .= ' AND (author_name LIKE ? OR author_email LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $args = [$like, $like];
}
$people = q("SELECT t.author_email AS email, MAX(t.author_name) AS name, COUNT(*) AS reviews,
                    SUM(t.status = 'approved') AS published, MAX(t.created_at) AS last_review,
                    MAX(b.email IS NOT NULL) AS blocked
             FROM testimonials t LEFT JOIN blocked_emails b ON b.email = t.author_email
             WHERE $where GROUP BY t.author_email ORDER BY last_review DESC LIMIT 300", $args)->fetchAll();

admin_start('Reviewers', $admin, 'users');
?>
<div class="adm-head">
  <h1>Reviewers</h1>
  <form method="get" action="/admin/users" class="adm-search"><input type="search" name="q" placeholder="Search name or email" value="<?= e($search) ?>"><button class="adm-btn" type="submit">Search</button></form>
</div>
<p class="muted" style="margin-bottom:14px">Everyone who sent a review. Blocking an email unpublishes its reviews and stops it from sending new ones.</p>
<div class="adm-table-wrap">
<table class="adm-table">
  <thead><tr><th>Name</th><th>Email</th><th>Reviews</th><th>Published</th><th>Last review</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($people as $p): ?>
    <tr>
      <td><?= e($p['name']) ?></td>
      <td><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></td>
      <td><?= (int) $p['reviews'] ?></td>
      <td><?= (int) $p['published'] ?></td>
      <td><?= e(fmt_date($p['last_review'])) ?></td>
      <td><?= $p['blocked'] ? '<span class="badge-st st-rejected">blocked</span>' : '<span class="badge-st st-approved">ok</span>' ?></td>
      <td>
        <form method="post" action="/admin/users"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($p['email']) ?>">
          <?php if (!$p['blocked']): ?>
            <input type="hidden" name="do" value="block">
            <button class="adm-btn adm-btn-danger" type="submit" data-confirm="Block <?= e($p['email']) ?>? Its reviews will be unpublished and it can't send new ones.">Block</button>
          <?php else: ?>
            <input type="hidden" name="do" value="unblock"><button class="adm-btn" type="submit">Unblock</button>
          <?php endif ?>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  <?php if (!$people): ?><tr><td colspan="7" class="muted">No reviews from customers yet.</td></tr><?php endif ?>
  </tbody>
</table>
</div>
<?php admin_end();
