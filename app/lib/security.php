<?php
/* Escaping, headers, sessions, CSRF, rate limiting, bot checks, flash messages. */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function client_ip(): string
{
    if (cfg('trust_cloudflare_ip') && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function send_security_headers(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://challenges.cloudflare.com https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: blob:; media-src 'self' blob:; "
        . "frame-src https://www.facebook.com https://www.youtube-nocookie.com https://www.youtube.com https://challenges.cloudflare.com; "
        . "connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'");
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_name(is_https() ? '__Host-jls' : 'jls');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => is_https(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Read a trimmed single-line string from POST, capped in length. */
function post_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $max);
}

/** Multi-line text (keeps line breaks, max 2 blank lines). */
function post_text(string $key, int $max = 5000): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = preg_replace("/\n{3,}/", "\n\n", $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

/* ---------- rate limiting (stored in MySQL, survives restarts) ---------- */
function rl_key(string $key): string
{
    return hash('sha256', $key);
}

/** true when $key already used $max attempts inside $window seconds */
function rate_blocked(string $key, int $max, int $window): bool
{
    $row = q('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [rl_key($key)])->fetch();
    return $row && $row['window_start'] + $window > time() && $row['hits'] >= $max;
}

function rate_hit(string $key, int $window): void
{
    $k = rl_key($key);
    $now = time();
    $row = q('SELECT window_start FROM rate_limits WHERE rkey = ?', [$k])->fetch();
    if (!$row || $row['window_start'] + $window <= $now) {
        q('REPLACE INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)', [$k, $now]);
    } else {
        q('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$k]);
    }
    if (random_int(1, 200) === 1) q('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400 * 2]);
}

/** Count an attempt and say whether it is allowed. */
function rate_limit(string $key, int $max, int $window): bool
{
    if (rate_blocked($key, $max, $window)) return false;
    rate_hit($key, $window);
    return true;
}

function rate_clear(string $key): void
{
    q('DELETE FROM rate_limits WHERE rkey = ?', [rl_key($key)]);
}

/* ---------- bot protection: Cloudflare Turnstile + honeypot + min fill time ---------- */
function turnstile_enabled(): bool
{
    return (string) cfg('turnstile.site_key', '') !== '' && (string) cfg('turnstile.secret_key', '') !== '';
}

/** Everything a protected form needs: honeypot, timing stamp, captcha widget. */
function bot_fields(string $form): string
{
    $_SESSION['form_ts'][$form] = time();
    $html = '<div class="hp" aria-hidden="true"><label>Leave empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    if (turnstile_enabled()) {
        $html .= '<div class="cf-turnstile" data-sitekey="' . e(cfg('turnstile.site_key')) . '" data-theme="dark"></div>';
    }
    return $html;
}

/** Throws UserError when the request looks automated. */
function bot_check(string $form, int $minSeconds = 3): void
{
    $hp = $_POST['website'] ?? '';
    $started = $_SESSION['form_ts'][$form] ?? 0;
    if ($hp !== '' || !$started || time() - $started < $minSeconds) {
        throw new UserError('Please wait a moment and try again.');
    }
    if (!turnstile_enabled()) return;

    $token = $_POST['cf-turnstile-response'] ?? '';
    if (!is_string($token) || $token === '' || strlen($token) > 2048) {
        throw new UserError('Please complete the “I am human” check.');
    }
    $payload = http_build_query([
        'secret' => cfg('turnstile.secret_key'), 'response' => $token, 'remoteip' => client_ip(),
    ]);
    $res = http_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);
    $json = $res ? json_decode($res, true) : null;
    if (empty($json['success'])) {
        throw new UserError('The “I am human” check failed. Please try again.');
    }
}

function http_post(string $url, string $body): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $out = curl_exec($ch);
        return is_string($out) ? $out : null;
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'content' => $body, 'timeout' => 10,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    ]]);
    $out = @file_get_contents($url, false, $ctx);
    return $out === false ? null : $out;
}

/* ---------- flash messages & redirects ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes_html(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $out .= '<div class="alert alert-' . e($type) . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/** Redirect to a path on THIS site only (never to an outside URL). */
function redirect(string $path): never
{
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) $path = '/';
    header('Location: ' . $path, true, 303);
    exit;
}

/** Safe ?next= value: only a local path. */
function safe_next(?string $next, string $fallback): string
{
    if (!is_string($next) || !preg_match('#^/[A-Za-z0-9/_\-?=&.]*$#', $next) || str_starts_with($next, '//')) {
        return $fallback;
    }
    return $next;
}
