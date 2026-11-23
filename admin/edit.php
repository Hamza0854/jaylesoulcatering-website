<?php
/* Add a testimonial (video file, YouTube/Facebook link, photos, text) or edit an existing one. */
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';
$admin = require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$t = $id ? q('SELECT * FROM testimonials WHERE id = ?', [$id])->fetch() : null;
if ($id && !$t) { flash('error', 'Testimonial not found.'); redirect('/admin'); }
$media = $t ? with_media([$t])[0]['media'] : [];
$videoMb = (int) cfg('uploads.admin_video_max_mb', 60);
$photoMb = (int) cfg('uploads.photo_max_mb', 5);

$v = [
    'author_name' => $t['author_name'] ?? '', 'author_location' => $t['author_location'] ?? '',
    'event_type' => $t['event_type'] ?? '', 'rating' => (int) ($t['rating'] ?? 0),
    'title' => $t['title'] ?? '', 'body' => $t['body'] ?? '',
    'embed' => $t['embed_url'] ?? '', 'status' => $t['status'] ?? 'approved', 'featured' => (int) ($t['featured'] ?? 0),
];
$error = upload_too_big_for_server() ? "The upload is larger than the server allows (max video {$videoMb} MB)." : '';

if (!$error && is_post()) {
    csrf_check();
    $v = [
        'author_name' => post_str('author_name', 80), 'author_location' => post_str('author_location', 80),
        'event_type' => post_str('event_type', 40), 'rating' => (int) ($_POST['rating'] ?? 0),
        'title' => post_str('title', 120), 'body' => post_text('body', 5000), 'embed' => post_str('embed', 500),
        'status' => in_array($_POST['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_POST['status'] : 'approved',
        'featured' => empty($_POST['featured']) ? 0 : 1,
    ];
    $removeIds = array_map('intval', (array) ($_POST['remove_media'] ?? []));
    $stored = [];
    $saved = false;
    try {
        if ($v['author_name'] === '') throw new UserError('Please enter the customer name (or e.g. “Happy Customer”).');
        if ($v['rating'] < 0 || $v['rating'] > 5) $v['rating'] = 0;
        if (!isset(EVENT_TYPES[$v['event_type']])) $v['event_type'] = '';
        $embed = $v['embed'] !== '' ? parse_video_link($v['embed']) : null;

        $public = $v['status'] === 'approved';
        foreach (files_list('photos') as $f) $stored[] = ['type' => 'image'] + store_photo($f, $public);
        foreach (files_list('video') as $f) $stored[] = ['type' => 'video'] + store_video($f, $public, $videoMb);

        $keptTypes = array_column(array_filter($media, fn($m) => !in_array((int) $m['id'], $removeIds, true)), 'type');
        $kind = kind_for($embed['url'] ?? null, array_merge($keptTypes, array_column($stored, 'type')));
        if ($kind === 'text' && $v['body'] === '') throw new UserError('Add a review text, a video, a video link or a photo.');

        $pdo = db();
        $pdo->beginTransaction();
        $vals = [$v['author_name'], $v['author_location'] ?: null, $v['event_type'] ?: null, $v['rating'] ?: null,
                 $v['title'] ?: null, $v['body'] ?: null, $kind, $embed['provider'] ?? null, $embed['url'] ?? null,
                 $v['status'], $v['featured']];
        if ($t) {
            q('UPDATE testimonials SET author_name=?, author_location=?, event_type=?, rating=?, title=?, body=?, kind=?,
               embed_provider=?, embed_url=?, status=?, featured=?, reviewed_at=?, reviewed_by=? WHERE id=?',
              array_merge($vals, [now(), $admin['id'], $id]));
        } else {
            q("INSERT INTO testimonials (author_name, author_location, event_type, rating, title, body, kind, embed_provider, embed_url,
               status, featured, source, created_at, reviewed_at, reviewed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,'admin',?,?,?)",
              array_merge($vals, [now(), now(), $admin['id']]));
            $id = (int) $pdo->lastInsertId();
        }
        $sort = count($media);
        foreach ($stored as $m) {
            q('INSERT INTO testimonial_media (testimonial_id, type, path, mime, size, sort) VALUES (?,?,?,?,?,?)',
              [$id, $m['type'], $m['path'], $m['mime'], $m['size'], $sort++]);
        }
        $toDelete = [];
        foreach ($media as $m) if (in_array((int) $m['id'], $removeIds, true)) $toDelete[] = $m;
        foreach ($toDelete as $m) q('DELETE FROM testimonial_media WHERE id = ? AND testimonial_id = ?', [$m['id'], $id]);
        $pdo->commit();
        $saved = true;
        foreach ($toDelete as $m) delete_media_file($m['path']);
        media_set_public($id, $public);   // keep file visibility in line with status

        audit('admin', (int) $admin['id'], $t ? 'testimonial_edit' : 'testimonial_add', "#$id");
        flash('success', $t ? "Testimonial #$id saved." : "Testimonial #$id added.");
        redirect('/admin?status=' . $v['status']);
    } catch (UserError $e) {
        $error = $e->getMessage();
    } finally {
        if (!$saved) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            foreach ($stored as $m) delete_media_file($m['path']);
        }
    }
}

admin_start($t ? 'Edit testimonial' : 'Add testimonial', $admin, $t ? 'reviews' : 'add');
?>
<div class="adm-head"><h1><?= $t ? 'Edit testimonial #' . (int) $t['id'] : 'Add a testimonial' ?></h1></div>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php if ($t && $t['source'] === 'customer'): ?><p class="alert alert-info">This review was written by a customer. Only fix typos — don't change what they said.</p><?php endif ?>

<form method="post" action="/admin/edit<?= $t ? '?id=' . (int) $t['id'] : '' ?>" enctype="multipart/form-data" class="adm-form adm-grid">
  <?= csrf_field() ?>
  <?php if ($t): ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><?php endif ?>

  <label>Customer name *<input type="text" name="author_name" maxlength="80" required value="<?= e($v['author_name']) ?>"></label>
  <label>City / location<input type="text" name="author_location" maxlength="80" value="<?= e($v['author_location']) ?>"></label>
  <label>Event type<select name="event_type"><option value="">—</option>
    <?php foreach (EVENT_TYPES as $k => $label): ?><option value="<?= e($k) ?>"<?= $v['event_type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
  <label>Star rating<select name="rating"><option value="0">No rating</option>
    <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= $v['rating'] === $s ? ' selected' : '' ?>><?= str_repeat('★', $s) . str_repeat('☆', 5 - $s) ?> (<?= $s ?>)</option><?php endfor ?></select></label>

  <label class="span2">Title<input type="text" name="title" maxlength="120" value="<?= e($v['title']) ?>"></label>
  <label class="span2">Review text<textarea name="body" rows="5" maxlength="5000"><?= e($v['body']) ?></textarea></label>

  <label class="span2">YouTube or Facebook video link
    <input type="url" name="embed" maxlength="500" placeholder="https://www.facebook.com/reel/…  or  https://youtube.com/shorts/…" value="<?= e($v['embed']) ?>">
    <small>YouTube videos play right on the page. Facebook blocks embedding of most reels (music/copyright), so Facebook links show a “Watch on Facebook” tile —
      to play a Facebook reel on the page, download it and upload the MP4 below instead (best quality).</small></label>

  <label>Upload video (MP4/MOV/WebM, max <?= $videoMb ?> MB)<input type="file" name="video" accept="video/mp4,video/quicktime,video/webm"></label>
  <label>Upload photos (JPG/PNG, max <?= $photoMb ?> MB each)<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>

  <?php if ($media): ?>
  <div class="span2 adm-media adm-media-edit">
    <?php foreach ($media as $m): ?>
      <label class="m-item">
        <?php if ($m['type'] === 'image'): ?><img src="<?= e(media_url($m)) ?>" alt=""><?php else: ?><video src="<?= e(media_url($m)) ?>" preload="metadata" controls></video><?php endif ?>
        <span><input type="checkbox" name="remove_media[]" value="<?= (int) $m['id'] ?>"> Remove</span>
      </label>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <label>Status<select name="status">
    <?php foreach (['approved' => 'Published', 'pending' => 'Waiting', 'rejected' => 'Rejected'] as $k => $label): ?>
      <option value="<?= $k ?>"<?= $v['status'] === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></label>
  <label class="inline"><input type="checkbox" name="featured" value="1"<?= $v['featured'] ? ' checked' : '' ?>> Featured (show first)</label>

  <div class="span2 adm-form-actions">
    <button type="submit" class="adm-btn adm-btn-red"><?= $t ? 'Save changes' : 'Add testimonial' ?></button>
    <a class="adm-btn" href="/admin">Cancel</a>
  </div>
</form>
<?php admin_end();
