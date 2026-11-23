<?php
/* POST-only moderation actions: approve / reject / feature / delete. */
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
if (!is_post()) redirect('/admin');
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$do = (string) ($_POST['do'] ?? '');
$t = q('SELECT t.*, u.email AS user_email, u.name AS user_name FROM testimonials t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?', [$id])->fetch();
if (!$t) {
    flash('error', 'That testimonial no longer exists.');
    redirect('/admin');
}
$back = '/admin?status=' . ($t['status'] === 'pending' ? 'pending' : $t['status']);

switch ($do) {
    case 'approve':
        media_set_public($id, true);
        q("UPDATE testimonials SET status = 'approved', admin_note = NULL, reviewed_at = ?, reviewed_by = ? WHERE id = ?", [now(), $admin['id'], $id]);
        if ($t['status'] === 'pending' && $t['user_email']) {
            send_mail($t['user_email'], 'Your review is live — thank you!',
                "Hi {$t['user_name']},\n\nThank you for your review! It's now published on our website:\n"
                . rtrim(cfg('site_url'), '/') . "/testimonials\n\n— Chef Jay LeSoul Catering");
        }
        flash('success', "Testimonial #$id is now published.");
        break;

    case 'reject':
        $note = post_str('note', 255);
        q("UPDATE testimonials SET status = 'rejected', admin_note = ?, reviewed_at = ?, reviewed_by = ? WHERE id = ?", [$note ?: null, now(), $admin['id'], $id]);
        media_set_public($id, false);
        flash('success', "Testimonial #$id is not published.");
        break;

    case 'feature':
        q('UPDATE testimonials SET featured = 1 - featured WHERE id = ?', [$id]);
        flash('success', $t['featured'] ? "Testimonial #$id is no longer featured." : "Testimonial #$id will show first.");
        break;

    case 'delete':
        foreach (q('SELECT path FROM testimonial_media WHERE testimonial_id = ?', [$id])->fetchAll() as $m) delete_media_file($m['path']);
        q('DELETE FROM testimonials WHERE id = ?', [$id]);
        flash('success', "Testimonial #$id was deleted.");
        break;

    default:
        redirect('/admin');
}
audit('admin', (int) $admin['id'], 'testimonial_' . $do, "#$id");
redirect($back);
