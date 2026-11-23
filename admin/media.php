<?php
/* Lets a logged-in admin preview photos/videos that are not published yet (they live outside the public folder). */
require __DIR__ . '/../app/bootstrap.php';
require_admin();
session_write_close();   // don't lock the session while a video streams

$m = q('SELECT * FROM testimonial_media WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$m || !is_managed_path($m['path']) || !is_file(ROOT . '/' . $m['path'])) {
    http_response_code(404);
    exit('Not found');
}
stream_file(ROOT . '/' . $m['path'], $m['mime']);
