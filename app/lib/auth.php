<?php
/* Customer + admin authentication. Customers and admins are separate tables and separate sessions keys. */

const ADMIN_IDLE_SECONDS     = 30 * 60;      // admin logged out after 30 min without activity
const ADMIN_MAX_SECONDS      = 12 * 3600;    // and after 12 h no matter what
const PASSWORD_MIN           = 10;

function hash_password(string $plain): string
{
    return password_hash($plain, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

/** Returns an error message, or null when the password is acceptable. */
function password_problem(string $pw, string $email = ''): ?string
{
    if (mb_strlen($pw) < PASSWORD_MIN) return 'Password must be at least ' . PASSWORD_MIN . ' characters.';
    if (mb_strlen($pw) > 200) return 'Password is too long.';
    $lower = mb_strtolower($pw);
    foreach (['password', '1234567890', 'qwertyuiop', 'jaylesoul', 'chefjay', 'catering'] as $bad) {
        if (str_contains($lower, $bad)) return 'That password is too easy to guess. Please choose another.';
    }
    $local = mb_strtolower(strtok($email, '@') ?: '');
    if ($local !== '' && mb_strlen($local) > 3 && str_contains($lower, $local)) return 'Your password should not contain your email address.';
    if (count(array_unique(mb_str_split($pw))) < 5) return 'That password is too easy to guess. Please choose another.';
    return null;
}

/** Same work for unknown accounts, so response time does not reveal which emails exist. */
function verify_password_timing_safe(?string $hash, string $plain): bool
{
    static $dummy = null;
    if ($hash === null) {
        $dummy ??= hash_password('dummy-password-for-timing');
        password_verify($plain, $dummy);
        return false;
    }
    return password_verify($plain, $hash);
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function valid_email(string $email): bool
{
    return mb_strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/* =========================================================
   CUSTOMERS
   ========================================================= */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $u = q('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['uid']])->fetch();
        if ($u && $u['status'] === 'active' && (int) $u['session_version'] === (int) ($_SESSION['uver'] ?? -1)) {
            $cache = $u;
        } else {
            unset($_SESSION['uid'], $_SESSION['uver']);
        }
    }
    return $cache;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $u['id'];
    $_SESSION['uver'] = (int) $u['session_version'];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
}

/** After a successful password check, upgrade old hashes silently. */
function maybe_rehash(string $table, array $row, string $plain): void
{
    if (!in_array($table, ['users', 'admins'], true)) return;
    if (password_needs_rehash($row['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        q("UPDATE $table SET password_hash = ? WHERE id = ?", [hash_password($plain), $row['id']]);
    }
}

function logout_user(): void
{
    unset($_SESSION['uid'], $_SESSION['uver']);
    session_regenerate_id(true);
}

function require_user(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please log in first.');
        redirect('/account/login?next=' . rawurlencode(strtok($_SERVER['REQUEST_URI'] ?? '/account', '#')));
    }
    return $u;
}

/* one-time tokens for email verification / password reset (only the SHA-256 is stored) */
function token_create(int $userId, string $type, int $ttlSeconds): string
{
    $raw = bin2hex(random_bytes(32));
    q('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
    q('INSERT INTO user_tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?,?,?,?,?)',
      [$userId, $type, hash('sha256', $raw), now($ttlSeconds), now()]);
    return $raw;
}

/** Returns the token row (still valid) or null. */
function token_find(string $raw, string $type): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return null;
    $row = q('SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND expires_at > ?',
             [hash('sha256', $raw), $type, now()])->fetch();
    return $row ?: null;
}

function token_delete_all(int $userId, string $type): void
{
    q('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
}

function send_verification_email(array $u): void
{
    $token = token_create((int) $u['id'], 'verify', 48 * 3600);
    $link = rtrim(cfg('site_url'), '/') . '/account/verify?token=' . $token;
    send_mail($u['email'], 'Confirm your email — Chef Jay LeSoul Catering',
        "Hi {$u['name']},\n\nThanks for signing up! Please confirm your email address by opening this link:\n\n$link\n\n"
        . "The link works for 48 hours. If you didn't create an account, you can ignore this email.\n\n— Chef Jay LeSoul Catering");
}

/* =========================================================
   ADMINS
   ========================================================= */
function current_admin(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (empty($_SESSION['aid'])) return null;

    $a = q('SELECT * FROM admins WHERE id = ?', [(int) $_SESSION['aid']])->fetch();
    $now = time();
    $fresh = $a && $a['status'] === 'active'
        && (int) $a['session_version'] === (int) ($_SESSION['aver'] ?? -1)
        && $now - (int) ($_SESSION['a_last'] ?? 0) < ADMIN_IDLE_SECONDS
        && $now - (int) ($_SESSION['a_start'] ?? 0) < ADMIN_MAX_SECONDS
        && hash_equals((string) ($_SESSION['a_ua'] ?? ''), admin_ua_hash());
    if (!$fresh) {
        logout_admin();
        return null;
    }
    $_SESSION['a_last'] = $now;
    return $cache = $a;
}

function admin_ua_hash(): string
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function login_admin(array $a): void
{
    session_regenerate_id(true);
    unset($_SESSION['a_pending'], $_SESSION['a_pending_at']);
    $_SESSION['aid']     = (int) $a['id'];
    $_SESSION['aver']    = (int) $a['session_version'];
    $_SESSION['a_start'] = time();
    $_SESSION['a_last']  = time();
    $_SESSION['a_ua']    = admin_ua_hash();
    q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
    audit('admin', (int) $a['id'], 'login', '');
}

function logout_admin(): void
{
    unset($_SESSION['aid'], $_SESSION['aver'], $_SESSION['a_start'], $_SESSION['a_last'], $_SESSION['a_ua'],
          $_SESSION['a_pending'], $_SESSION['a_pending_at']);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

/** Every admin page calls this first. $setupPage = the 2FA setup page itself. */
function require_admin(bool $setupPage = false): array
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    $a = current_admin();
    if (!$a) {
        redirect('/admin/login');
    }
    if (!$setupPage && cfg('admin_require_2fa', true) && !(int) $a['totp_enabled']) {
        flash('info', 'Set up two-factor authentication to continue.');
        redirect('/admin/security');
    }
    return $a;
}
