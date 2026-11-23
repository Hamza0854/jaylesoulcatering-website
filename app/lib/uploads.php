<?php
/*
 * Upload handling.
 *  - Pending (not yet approved) files live in storage/pending/  → blocked from the web,
 *    the admin sees them through /admin/media.
 *  - On approval they move to uploads/testimonials/ → public.
 *  - Photos are decoded and re-encoded with GD: strips EXIF/GPS and any hidden payload.
 *  - Videos are checked by real file signature + MIME, never by the file name.
 *  - File names are random; the visitor's file name is never used.
 */

const PENDING_REL = 'storage/pending/';
const PUBLIC_REL  = 'uploads/testimonials/';
const PHOTO_MAX_SIDE = 1600;

const VIDEO_TYPES = [
    'video/mp4'       => 'mp4',
    'video/quicktime' => 'mov',
    'video/webm'      => 'webm',
];

/** $_FILES['x'] with multiple="" → list of single-file arrays (empty slots dropped). */
function files_list(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
                  'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** The whole POST was bigger than post_max_size → PHP silently drops everything. */
function upload_too_big_for_server(): bool
{
    return is_post() && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function upload_check(array $f, int $maxBytes, string $what): void
{
    $mb = round($maxBytes / 1048576);
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new UserError("That $what is too large. The maximum is {$mb} MB.");
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new UserError("The $what didn't upload correctly. Please try again.");
    }
    if ($f['size'] <= 0 || $f['size'] > $maxBytes) {
        throw new UserError("That $what is too large. The maximum is {$mb} MB.");
    }
}

function random_name(string $ext): string
{
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

/** Re-encodes a photo to JPEG. Returns ['path','mime','size']. */
function store_photo(array $f, bool $public): array
{
    upload_check($f, (int) cfg('uploads.photo_max_mb', 5) * 1048576, 'photo');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new UserError('Photos must be JPG, PNG or WebP images.');
    }
    [$w, $h] = $info;
    if ($w < 50 || $h < 50 || $w * $h > 50_000_000) throw new UserError('That photo has unusual dimensions. Please try a different one.');
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('PHP GD extension is missing');

    $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$src) throw new UserError('That photo could not be read. Please try a different one.');

    // respect the phone's rotation flag, then drop all metadata by re-encoding
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) {
            $rot = imagerotate($src, $angle, 0);
            if ($rot) $src = $rot;
        }
    }
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, PHOTO_MAX_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // flatten transparency
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    unset($src);

    $rel = ($public ? PUBLIC_REL : PENDING_REL) . random_name('jpg');
    if (!imagejpeg($dst, ROOT . '/' . $rel, 84)) throw new RuntimeException('Could not save photo');
    unset($dst);
    return ['path' => $rel, 'mime' => 'image/jpeg', 'size' => filesize(ROOT . '/' . $rel)];
}

/** Validates and stores a video. Returns ['path','mime','size']. */
function store_video(array $f, bool $public, int $maxMb): array
{
    upload_check($f, $maxMb * 1048576, 'video');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 16);
    $isIso  = substr($head, 4, 4) === 'ftyp';               // mp4 / mov
    $isWebm = substr($head, 0, 4) === "\x1A\x45\xDF\xA3";   // webm / mkv
    if (!isset(VIDEO_TYPES[$mime]) || !($isIso || $isWebm)) {
        throw new UserError('Videos must be MP4, MOV or WebM files.');
    }
    $rel = ($public ? PUBLIC_REL : PENDING_REL) . random_name(VIDEO_TYPES[$mime]);
    if (!move_uploaded_file($f['tmp_name'], ROOT . '/' . $rel)) throw new RuntimeException('Could not save video');
    @chmod(ROOT . '/' . $rel, 0644);
    return ['path' => $rel, 'mime' => $mime, 'size' => (int) $f['size']];
}

/** Only files we created can be moved / deleted (never the site's own assets). */
function is_managed_path(string $rel): bool
{
    return (bool) preg_match('#^(storage/pending|uploads/testimonials)/[a-f0-9]{32}\.(jpg|mp4|mov|webm)$#', $rel);
}

function delete_media_file(string $rel): void
{
    if (is_managed_path($rel) && is_file(ROOT . '/' . $rel)) @unlink(ROOT . '/' . $rel);
}

/** Move a testimonial's files to public (approved) or back to private (pending / rejected). */
function media_set_public(int $testimonialId, bool $public): void
{
    $rows = q('SELECT id, path FROM testimonial_media WHERE testimonial_id = ?', [$testimonialId])->fetchAll();
    foreach ($rows as $m) {
        if (!is_managed_path($m['path'])) continue;
        $isPublic = str_starts_with($m['path'], PUBLIC_REL);
        if ($isPublic === $public) continue;
        $new = ($public ? PUBLIC_REL : PENDING_REL) . basename($m['path']);
        if (@rename(ROOT . '/' . $m['path'], ROOT . '/' . $new)) {
            q('UPDATE testimonial_media SET path = ? WHERE id = ?', [$new, $m['id']]);
        }
    }
}

/** Browser URL for a media row. Private files are only reachable through the admin proxy. */
function media_url(array $m): string
{
    return str_starts_with($m['path'], PENDING_REL) ? '/admin/media?id=' . (int) $m['id'] : '/' . $m['path'];
}

/** Streams a file with HTTP Range support (so videos can seek). */
function stream_file(string $abs, string $mime): never
{
    $size = filesize($abs);
    $start = 0; $end = $size - 1;
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $m)) {
        if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int) $m[2]); }
        else { $start = (int) $m[1]; if ($m[2] !== '') $end = min($end, (int) $m[2]); }
        if ($start > $end || $start >= $size) { header("Content-Range: bytes */$size", true, 416); exit; }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    $fp = fopen($abs, 'rb');
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(65536, $left));
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    exit;
}
