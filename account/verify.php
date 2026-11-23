<?php
require __DIR__ . '/../app/bootstrap.php';

$raw = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
if (!rate_limit('verify:' . client_ip(), 30, 3600)) {
    flash('error', 'Too many attempts. Please try again later.');
    redirect('/account/login');
}
$tok = token_find($raw, 'verify');
if (!$tok) {
    flash('error', 'That confirmation link is invalid or has expired. Log in to get a new one.');
    redirect('/account/login');
}
q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $tok['user_id']]);
token_delete_all((int) $tok['user_id'], 'verify');
audit('user', (int) $tok['user_id'], 'email_verified');
flash('success', 'Your email is confirmed! Log in to write your review.');
redirect('/account/login?next=' . rawurlencode('/account/submit'));
