<?php
/* Testimonials: queries, rating summary, embeds, rendering. */

const EVENT_TYPES = [
    'private-dinner' => 'Private Dinner',
    'catering'       => 'Event Catering',
    'corporate'      => 'Corporate Event',
    'family'         => 'Birthday / Family Gathering',
    'community'      => 'Cooking in the Hood / Community',
    'seasoning'      => 'Black Crack Seasoning',
    'other'          => 'Other',
];
const KINDS = ['video' => 'Videos', 'photo' => 'Photos', 'text' => 'Reviews'];
const PER_PAGE = 12;

/* ---------- video links (YouTube / Facebook) ---------- */

/** Turns a pasted YouTube/Facebook link into ['provider','url'] or throws UserError. */
function parse_video_link(string $url): array
{
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url) || mb_strlen($url) > 500) throw new UserError('Please paste a full video link starting with https://');
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if (preg_match('#(^|\.)(youtube\.com|youtu\.be|youtube-nocookie\.com)$#', $host)) {
        if (preg_match('#(?:v=|/shorts/|/embed/|/live/|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=' . $m[1]];
        }
        throw new UserError('That YouTube link does not point to a video.');
    }

    if (preg_match('#(^|\.)(facebook\.com|fb\.watch)$#', $host)) {
        // short share links (facebook.com/share/r/…, fb.watch/…) → follow the redirect to the real reel
        if (str_contains($url, '/share/') || $host === 'fb.watch') $url = resolve_redirect($url) ?? $url;
        if (preg_match('#facebook\.com/(?:reel/|watch/?\?v=|[^/]+/videos/(?:[^/]+/)?|video\.php\?v=)(\d{6,})#', $url, $m)) {
            return ['provider' => 'facebook', 'url' => 'https://www.facebook.com/reel/' . $m[1] . '/'];
        }
        throw new UserError('Could not read that Facebook link. Open the video on Facebook and copy the address that contains /reel/ or /videos/.');
    }
    throw new UserError('Only YouTube and Facebook video links are supported.');
}

/** Follows redirects (Facebook only) and returns the final URL. */
function resolve_redirect(string $url): ?string
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; LinkCheck/1.0)',
    ]);
    curl_exec($ch);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    return is_string($final) && preg_match('#^https://([a-z0-9-]+\.)*facebook\.com/#i', $final) ? $final : null;
}

function embed_html(string $provider, string $url, string $title): string
{
    if ($provider === 'youtube' && preg_match('#v=([A-Za-z0-9_-]{11})#', $url, $m)) {
        return '<iframe src="https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&amp;playsinline=1" title="' . e($title)
             . '" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    }
    if ($provider === 'facebook') {
        // Facebook refuses to embed many reels (music/copyright, private, or owner setting) and we can't
        // detect that from our page, so Facebook videos open on Facebook instead of showing a broken box.
        // Tip: download the reel and upload the MP4 in the admin panel to play it right here, in full quality.
        return '<a class="t-fb" href="' . e($url) . '" target="_blank" rel="noopener" aria-label="' . e($title) . ' — watch on Facebook">'
             . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
             . '<b>Watch the Video</b><small>on Facebook</small></a>';
    }
    return '';
}

/* ---------- reading ---------- */

function kind_for(?string $embedUrl, array $mediaTypes): string
{
    if ($embedUrl || in_array('video', $mediaTypes, true)) return 'video';
    if (in_array('image', $mediaTypes, true)) return 'photo';
    return 'text';
}

/** Adds ['media' => [...]] to each testimonial row. */
function with_media(array $rows): array
{
    if (!$rows) return $rows;
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $media = [];
    foreach (q("SELECT * FROM testimonial_media WHERE testimonial_id IN ($in) ORDER BY sort, id", $ids)->fetchAll() as $m) {
        $media[$m['testimonial_id']][] = $m;
    }
    foreach ($rows as &$r) $r['media'] = $media[$r['id']] ?? [];
    return $rows;
}

function approved_testimonials(?string $kind, int $page): array
{
    $where = "status = 'approved'" . ($kind ? ' AND kind = ?' : '');
    $args = $kind ? [$kind] : [];
    $total = (int) q("SELECT COUNT(*) FROM testimonials WHERE $where", $args)->fetchColumn();
    $offset = max(0, ($page - 1) * PER_PAGE);
    $rows = q("SELECT * FROM testimonials WHERE $where ORDER BY featured DESC, created_at DESC, id DESC LIMIT " . PER_PAGE . " OFFSET $offset", $args)->fetchAll();
    return [with_media($rows), $total];
}

function kind_counts(): array
{
    $out = ['all' => 0, 'video' => 0, 'photo' => 0, 'text' => 0];
    foreach (q("SELECT kind, COUNT(*) c FROM testimonials WHERE status = 'approved' GROUP BY kind")->fetchAll() as $r) {
        $out[$r['kind']] = (int) $r['c'];
        $out['all'] += (int) $r['c'];
    }
    return $out;
}

/** judge.me-style summary: average, total, and how many of each star. */
function rating_summary(): array
{
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach (q("SELECT rating, COUNT(*) c FROM testimonials WHERE status = 'approved' AND rating BETWEEN 1 AND 5 GROUP BY rating")->fetchAll() as $r) {
        $dist[(int) $r['rating']] = (int) $r['c'];
    }
    $count = array_sum($dist);
    $avg = $count ? array_sum(array_map(fn($s, $c) => $s * $c, array_keys($dist), $dist)) / $count : 0;
    return ['avg' => round($avg, 1), 'count' => $count, 'dist' => $dist];
}

/* ---------- rendering ---------- */

function stars_html(float $rating, string $class = 't-stars'): string
{
    $full = (int) floor($rating);
    $half = $rating - $full >= 0.5;
    $html = '<span class="' . $class . '" role="img" aria-label="' . e(rtrim(rtrim(number_format($rating, 1), '0'), '.')) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $i <= $full ? 'on' : ($i === $full + 1 && $half ? 'half' : 'off');
        $html .= '<i class="' . $cls . '" aria-hidden="true">★</i>';
    }
    return $html . '</span>';
}

function fmt_date(?string $dt): string
{
    return $dt ? date('M j, Y', strtotime($dt)) : '';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return $i ?: '?';
}

/** One public testimonial card. */
function testimonial_card(array $t, int $delay = 0): string
{
    $images = array_values(array_filter($t['media'], fn($m) => $m['type'] === 'image'));
    $video  = array_values(array_filter($t['media'], fn($m) => $m['type'] === 'video'))[0] ?? null;
    $label  = $t['kind'] === 'video' ? 'Video Review' : ($t['kind'] === 'photo' ? 'Photo Review' : '');
    $h = '<article class="t-card reveal" data-type="' . e($t['kind']) . '"' . ($delay ? ' data-delay="' . $delay . '"' : '') . '>';

    if ($t['embed_url'] || $video) {
        $h .= '<div class="t-media vert"><span class="t-kind">' . $label . '</span>';
        $h .= $video
            ? '<video src="' . e(media_url($video)) . '#t=0.1" controls playsinline preload="metadata"></video>'
            : embed_html((string) $t['embed_provider'], (string) $t['embed_url'], 'Video testimonial from ' . $t['author_name']);
        $h .= '</div>';
    } elseif ($images) {
        $h .= '<div class="t-media photo"><span class="t-kind">' . $label . '</span>'
            . '<a href="' . e(media_url($images[0])) . '" target="_blank" rel="noopener"><img src="' . e(media_url($images[0])) . '" alt="Photo from ' . e($t['author_name']) . '" loading="lazy"></a></div>';
    }

    $h .= '<div class="t-body">';
    if (!$t['embed_url'] && !$video && !$images) $h .= '<span class="t-mark" aria-hidden="true">&ldquo;</span>';
    if ($t['rating']) $h .= stars_html((float) $t['rating']);
    if ($t['title']) $h .= '<h3 class="t-title">' . e($t['title']) . '</h3>';
    if ($t['body']) $h .= '<p>' . nl2br(e($t['body'])) . '</p>';

    if (count($images) > ($t['embed_url'] || $video ? 0 : 1)) {
        $h .= '<div class="t-thumbs">';
        foreach (($t['embed_url'] || $video) ? $images : array_slice($images, 1) as $img) {
            $h .= '<a href="' . e(media_url($img)) . '" target="_blank" rel="noopener"><img src="' . e(media_url($img)) . '" alt="" loading="lazy"></a>';
        }
        $h .= '</div>';
    }

    $meta = array_filter([$t['author_location'], EVENT_TYPES[$t['event_type'] ?? ''] ?? null, fmt_date($t['created_at'])]);
    $h .= '<div class="t-who"><span class="t-av">' . e(initials($t['author_name'])) . '</span><div>'
        . '<b>' . e($t['author_name']) . ($t['source'] === 'customer' ? ' <span class="t-verified" title="Email-verified customer">✔ Verified</span>' : '') . '</b>'
        . '<small>' . e(implode(' · ', $meta)) . '</small></div></div>';
    if ($t['embed_provider'] === 'facebook') {
        $h .= '<a class="t-src" href="' . e($t['embed_url']) . '" target="_blank" rel="noopener">Watch on Facebook <svg class="chev"><use href="#chev"/></svg></a>';
    }
    return $h . '</div></article>';
}

/** Short status badge for account/admin pages. */
function status_badge(string $status): string
{
    $label = ['pending' => 'Waiting for approval', 'approved' => 'Published', 'rejected' => 'Not published'][$status] ?? $status;
    return '<span class="badge-st st-' . e($status) . '">' . e($label) . '</span>';
}
