<?php
/* Admin authentication (customers don't have accounts: they send reviews with name + email). */

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

/** After a successful password check, upgrade old hashes silently. */
function maybe_rehash(string $table, array $row, string $plain): void
{
    if ($table !== 'admins') return;
    if (password_needs_rehash($row['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [hash_password($plain), $row['id']]);
    }
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
    // 2FA is optional: admins can turn it on from the Security page.
    return $a;
}
