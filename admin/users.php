<?php
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $uid = (int) ($_POST['id'] ?? 0);
    $do = (string) ($_POST['do'] ?? '');
    if ($do === 'block' || $do === 'unblock') {
        // blocking also ends their sessions (session_version bump)
        q('UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?', [$do === 'block' ? 'blocked' : 'active', $uid]);
        if ($do === 'block' && !empty($_POST['hide'])) {
            foreach (q("SELECT id FROM testimonials WHERE user_id = ? AND status <> 'rejected'", [$uid])->fetchAll() as $r) {
                q("UPDATE testimonials SET status = 'rejected', reviewed_at = ?, reviewed_by = ? WHERE id = ?", [now(), $admin['id'], $r['id']]);
                media_set_public((int) $r['id'], false);
            }
        }
        audit('admin', (int) $admin['id'], 'user_' . $do, "user #$uid");
        flash('success', $do === 'block' ? 'Customer blocked.' : 'Customer unblocked.');
    }
    redirect(($_POST['back'] ?? '') === 'reviews' ? '/admin' : '/admin/users');
}

$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$args = [];
$where = '1=1';
if ($search !== '') {
    $where = '(u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $args = [$like, $like];
}
$users = q("SELECT u.*, (SELECT COUNT(*) FROM testimonials t WHERE t.user_id = u.id) AS reviews
            FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 200", $args)->fetchAll();

admin_start('Customers', $admin, 'users');
?>
<div class="adm-head">
  <h1>Customers</h1>
  <form method="get" action="/admin/users" class="adm-search"><input type="search" name="q" placeholder="Search name or email" value="<?= e($search) ?>"><button class="adm-btn" type="submit">Search</button></form>
</div>
<div class="adm-table-wrap">
<table class="adm-table">
  <thead><tr><th>Name</th><th>Email</th><th>Verified</th><th>Reviews</th><th>Joined</th><th>Last login</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= $u['email_verified_at'] ? '✔' : '<span class="muted">no</span>' ?></td>
      <td><?= (int) $u['reviews'] ?></td>
      <td><?= e(fmt_date($u['created_at'])) ?></td>
      <td><?= e(fmt_date($u['last_login_at'])) ?: '—' ?></td>
      <td><?= $u['status'] === 'active' ? '<span class="badge-st st-approved">active</span>' : '<span class="badge-st st-rejected">blocked</span>' ?></td>
      <td>
        <form method="post" action="/admin/users"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <?php if ($u['status'] === 'active'): ?>
            <input type="hidden" name="do" value="block"><input type="hidden" name="hide" value="1">
            <button class="adm-btn adm-btn-danger" type="submit" data-confirm="Block <?= e($u['name']) ?>? Their reviews will be unpublished and they can't log in.">Block</button>
          <?php else: ?>
            <input type="hidden" name="do" value="unblock"><button class="adm-btn" type="submit">Unblock</button>
          <?php endif ?>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  <?php if (!$users): ?><tr><td colspan="8" class="muted">No customers found.</td></tr><?php endif ?>
  </tbody>
</table>
</div>
<?php admin_end();
