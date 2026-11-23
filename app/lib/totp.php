<?php
/* Two-factor authentication: TOTP (RFC 6238) — works with Google Authenticator, Authy, 1Password, Microsoft Authenticator. */

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function b32_encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function b32_decode(string $s): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_new_secret(): string
{
    return b32_encode(random_bytes(20));
}

function totp_code(string $secret, int $step): string
{
    $h = hash_hmac('sha1', pack('J', $step), b32_decode($secret), true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code ±30 s. Returns the matched time-step (to block replays) or null. */
function totp_match(string $secret, string $code, ?int $lastStep = null): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) {
        $step = $now + $i;
        if ($lastStep !== null && $step <= $lastStep) continue;
        if (hash_equals(totp_code($secret, $step), $code)) return $step;
    }
    return null;
}

function totp_uri(string $secret, string $email): string
{
    $issuer = 'Chef Jay LeSoul Admin';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret
         . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

/** 8 one-time recovery codes. Returns [plainCodes, jsonOfHashes]. */
function recovery_codes_new(): array
{
    $plain = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtoupper(bin2hex(random_bytes(5)));
        $plain[] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    $hashes = array_map(fn($c) => hash('sha256', str_replace('-', '', $c)), $plain);
    return [$plain, json_encode($hashes)];
}

/** Uses up a recovery code. Returns the new JSON (code removed) or null if it didn't match. */
function recovery_code_use(?string $json, string $input): ?string
{
    $hashes = json_decode((string) $json, true) ?: [];
    $h = hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? ''));
    foreach ($hashes as $i => $stored) {
        if (hash_equals($stored, $h)) {
            unset($hashes[$i]);
            return json_encode(array_values($hashes));
        }
    }
    return null;
}
