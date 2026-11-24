<?php
/* Public "Write a Review" form — no account needed: name + email + rating + review (+ photos / video).
   Every review waits for admin approval. Protected by CSRF, Turnstile, honeypot, timing and rate limits. */
require __DIR__ . '/app/bootstrap.php';

$maxPhotos = (int) cfg('uploads.max_photos', 3);
$photoMb   = (int) cfg('uploads.photo_max_mb', 5);
$videoMb   = (int) cfg('uploads.video_max_mb', 20);
$errors = [];
$old = ['name' => '', 'email' => '', 'rating' => 0, 'title' => '', 'body' => '', 'event_type' => '', 'location' => ''];

if (upload_too_big_for_server()) {
    $errors['form'] = "Your files are too large. Photos can be up to {$photoMb} MB each and the video up to {$videoMb} MB.";
} elseif (is_post()) {
    csrf_check();
    $old = [
        'name'       => post_str('name', 80),
        'email'      => normalize_email(post_str('email', 190)),
        'rating'     => (int) ($_POST['rating'] ?? 0),
        'title'      => post_str('title', 120),
        'body'       => post_text('body', 2000),
        'event_type' => post_str('event_type', 40),
        'location'   => post_str('location', 80),
    ];
    $photos = files_list('photos');
    $videos = files_list('video');
    $stored = [];
    $saved = false;
    $ipKey = 'review-ip:' . client_ip();
    $emKey = 'review-em:' . $old['email'];

    try {
        bot_check('review', 5);
        if (rate_blocked($ipKey, 3, 86400) || rate_blocked($emKey, 3, 86400)) {
            throw new UserError('You can send up to 3 reviews per day. Please try again tomorrow.');
        }

        if (mb_strlen($old['name']) < 2) $errors['name'] = 'Please enter your name.';
        if (!valid_email($old['email'])) $errors['email'] = 'Please enter a valid email address.';
        if ($old['rating'] < 1 || $old['rating'] > 5) $errors['rating'] = 'Please choose a star rating.';
        if (mb_strlen($old['body']) < 20) $errors['body'] = 'Please write at least 20 characters.';
        if ($old['event_type'] !== '' && !isset(EVENT_TYPES[$old['event_type']])) $old['event_type'] = '';
        if (preg_match_all('#https?://|www\.#i', $old['title'] . ' ' . $old['body']) > 1) $errors['body'] = 'Please remove links from your review.';
        if (count($photos) > $maxPhotos) $errors['photos'] = "You can add up to $maxPhotos photos.";
        if (count($videos) > 1) $errors['video'] = 'You can add one video.';
        if (empty($_POST['consent'])) $errors['consent'] = 'Please confirm this is your own honest review.';

        if (!$errors) {
            if (email_blocked($old['email'])) throw new UserError('Sorry, we can’t accept reviews from this email address.');
            $pending = (int) q("SELECT COUNT(*) FROM testimonials WHERE author_email = ? AND status = 'pending'", [$old['email']])->fetchColumn();
            if ($pending >= 3) throw new UserError('You already have 3 reviews waiting for approval. Please wait until we review them.');

            // store files first (private folder); if anything fails they are cleaned up below
            foreach ($photos as $f) $stored[] = ['type' => 'image'] + store_photo($f, false);
            foreach ($videos as $f) $stored[] = ['type' => 'video'] + store_video($f, false, $videoMb);

            $pdo = db();
            $pdo->beginTransaction();
            q("INSERT INTO testimonials (source, author_name, author_email, author_location, event_type, rating, title, body, kind, status, created_at)
               VALUES ('customer', ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)", [
                $old['name'], $old['email'], $old['location'] ?: null, $old['event_type'] ?: null, $old['rating'],
                $old['title'] ?: null, $old['body'], kind_for(null, array_column($stored, 'type')), now(),
            ]);
            $tid = (int) $pdo->lastInsertId();
            foreach ($stored as $i => $m) {
                q('INSERT INTO testimonial_media (testimonial_id, type, path, mime, size, sort) VALUES (?,?,?,?,?,?)',
                  [$tid, $m['type'], $m['path'], $m['mime'], $m['size'], $i]);
            }
            $pdo->commit();
            $saved = true;
            rate_hit($ipKey, 86400);
            rate_hit($emKey, 86400);
            audit('visitor', null, 'testimonial_submit', "#$tid " . $old['email']);

            if ($to = cfg('mail.admin_notify')) {
                send_mail($to, "New {$old['rating']}★ review waiting for approval",
                    "{$old['name']} ({$old['email']}) sent a new review:\n\n" . ($old['title'] ? "{$old['title']}\n" : '') . "{$old['body']}\n\n"
                    . count($stored) . " attachment(s)\n\nApprove or reject it here:\n" . rtrim(cfg('site_url'), '/') . '/admin');
            }
            $_SESSION['review_sent'] = $old['name'];
            redirect('/review?sent=1');
        }
    } catch (UserError $e) {
        $errors['form'] = $e->getMessage();
    } finally {
        if (!$saved) {   // validation error or crash: undo the database write and remove stored files
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            foreach ($stored as $m) delete_media_file($m['path']);
        }
    }
}

$sentName = isset($_GET['sent']) ? ($_SESSION['review_sent'] ?? null) : null;
unset($_SESSION['review_sent']);

page_start('Write a Review | Chef Jay LeSoul Catering', 'page-app', [
    'description' => 'Fed by Chef Jay LeSoul? Leave a star rating, your review, photos or a short video.',
    'captcha' => true, 'active' => '/testimonials',
]);
app_hero('Write a Review', 'SHARE YOUR <em>EXPERIENCE</em>', 'Real love. Real flavor.');
?>
<section class="app-sec">
  <div class="app-card app-card-wide">
  <?php if ($sentName): ?>
    <div class="app-msg">
      <div class="alert alert-success" role="status">Thank you, <?= e($sentName) ?>! Your review was sent.</div>
      <p>Our team checks every review before it goes live. You'll get an email at the address you gave us as soon as it's published.</p>
      <p><a href="/testimonials">Read what others are saying</a> · <a href="/review">Write another review</a></p>
    </div>
  <?php else: ?>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif ?>
    <form method="post" action="/review" class="app-form" enctype="multipart/form-data" novalidate
          data-max-photos="<?= $maxPhotos ?>" data-photo-mb="<?= $photoMb ?>" data-video-mb="<?= $videoMb ?>">
      <?= csrf_field() ?>

      <fieldset class="rate<?= isset($errors['rating']) ? ' error' : '' ?>">
        <legend>Your rating <span class="req">*</span></legend>
        <div class="rate-stars">
          <?php for ($s = 5; $s >= 1; $s--): ?>
            <input type="radio" name="rating" id="r<?= $s ?>" value="<?= $s ?>"<?= $old['rating'] === $s ? ' checked' : '' ?> required>
            <label for="r<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★<span class="sr"><?= $s ?> stars</span></label>
          <?php endfor ?>
        </div>
        <?php if (isset($errors['rating'])): ?><small class="err"><?= e($errors['rating']) ?></small><?php endif ?>
      </fieldset>

      <div class="two-col">
        <div class="field<?= isset($errors['name']) ? ' error' : '' ?>" data-error="<?= e($errors['name'] ?? '') ?>">
          <input type="text" name="name" id="name" placeholder=" " maxlength="80" autocomplete="name" required value="<?= e($old['name']) ?>"><label for="name">Your Name * (shown on your review)</label></div>
        <div class="field<?= isset($errors['email']) ? ' error' : '' ?>" data-error="<?= e($errors['email'] ?? '') ?>">
          <input type="email" name="email" id="email" placeholder=" " maxlength="190" autocomplete="email" required value="<?= e($old['email']) ?>"><label for="email">Email * (never shown)</label></div>
      </div>

      <div class="field"><input type="text" name="title" id="title" placeholder=" " maxlength="120" value="<?= e($old['title']) ?>"><label for="title">Review title (optional) — e.g. “Best oxtails in Cleveland!”</label></div>

      <div class="field<?= isset($errors['body']) ? ' error' : '' ?>" data-error="<?= e($errors['body'] ?? '') ?>">
        <textarea name="body" id="body" rows="6" placeholder=" " maxlength="2000" required data-count="2000"><?= e($old['body']) ?></textarea>
        <label for="body">Your review *</label><small class="counter" aria-live="polite"></small></div>

      <div class="two-col">
        <div class="field"><select name="event_type" id="event_type">
          <option value="">What did we cook for you? (optional)</option>
          <?php foreach (EVENT_TYPES as $k => $label): ?><option value="<?= e($k) ?>"<?= $old['event_type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        </select></div>
        <div class="field"><input type="text" name="location" id="location" placeholder=" " maxlength="80" value="<?= e($old['location']) ?>"><label for="location">City (optional)</label></div>
      </div>

      <div class="uploads">
        <label class="up-box<?= isset($errors['photos']) ? ' error' : '' ?>">
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-kind="photo">
          <span class="up-ico" aria-hidden="true">📷</span>
          <b>Add photos</b><small>Up to <?= $maxPhotos ?> · JPG/PNG · max <?= $photoMb ?> MB each</small>
        </label>
        <label class="up-box<?= isset($errors['video']) ? ' error' : '' ?>">
          <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm" data-kind="video">
          <span class="up-ico" aria-hidden="true">🎥</span>
          <b>Add a short video</b><small>1 video · MP4/MOV · max <?= $videoMb ?> MB (about 30–60 sec)</small>
        </label>
      </div>
      <div class="up-preview" aria-live="polite"></div>
      <?php foreach (['photos', 'video'] as $k) if (isset($errors[$k])): ?><small class="err"><?= e($errors[$k]) ?></small><?php endif ?>

      <label class="check<?= isset($errors['consent']) ? ' error' : '' ?>"><input type="checkbox" name="consent" value="1" required>
        <span>This is my own honest experience, and Chef Jay LeSoul Catering may show my review, name, photos and video on its website and social media.</span></label>

      <?= bot_fields('review') ?>
      <button type="submit" class="btn-red btn-send">Send My Review <svg class="chev"><use href="#chev"/></svg></button>
      <p class="form-note">Your email is never shown publicly. Reviews appear after our team approves them.</p>
    </form>
  <?php endif ?>
  </div>
</section>
<?php page_end();
