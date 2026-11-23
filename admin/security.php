<?php
/* Admin security: two-factor setup, recovery codes, password, activity log. */
require __DIR__ . '/../app/bootstrap.php';
require APP . '/views/admin_layout.php';
$admin = require_admin(true);
$error = '';
$newCodes = null;

if (is_post()) {
    csrf_check();
    $do = (string) ($_POST['do'] ?? '');
    if (!rate_limit('asec:' . $admin['id'], 10, 900)) {
        $error = 'Too many attempts. Please wait 15 minutes.';
    } elseif ($do === 'enable_2fa') {
        $secret = $_SESSION['totp_setup'] ?? '';
        $step = $secret ? totp_match($secret, post_str('code', 10)) : null;
        if ($step === null) {
            $error = 'That code did not match. Make sure your phone\'s clock is correct and try the newest code.';
        } else {
            [$newCodes, $json] = recovery_codes_new();
            q('UPDATE admins SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, recovery_codes = ? WHERE id = ?', [$secret, $step, $json, $admin['id']]);
            unset($_SESSION['totp_setup']);
            audit('admin', (int) $admin['id'], '2fa_enabled');
            $admin = q('SELECT * FROM admins WHERE id = ?', [$admin['id']])->fetch();
        }
    } elseif ($do === 'new_codes') {
        if (totp_match((string) $admin['totp_secret'], post_str('code', 10), $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null) === null) {
            $error = 'Enter the current code from your authenticator app to create new recovery codes.';
        } else {
            [$newCodes, $json] = recovery_codes_new();
            q('UPDATE admins SET recovery_codes = ? WHERE id = ?', [$json, $admin['id']]);
            audit('admin', (int) $admin['id'], 'recovery_codes_regenerated');
        }
    } elseif ($do === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify($cur, $admin['password_hash'])) $error = 'Current password is not correct.';
        elseif (mb_strlen($new) < 14) $error = 'Admin passwords must be at least 14 characters.';
        elseif ($p = password_problem($new, $admin['email'])) $error = $p;
        else {
            q('UPDATE admins SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [hash_password($new), $admin['id']]);
            audit('admin', (int) $admin['id'], 'password_change');
            logout_admin();
            flash('success', 'Password changed. Please log in again with the new password.');
            redirect('/admin/login');
        }
    }
}

if (!(int) $admin['totp_enabled'] && empty($_SESSION['totp_setup'])) $_SESSION['totp_setup'] = totp_new_secret();
$setupSecret = (int) $admin['totp_enabled'] ? null : $_SESSION['totp_setup'];
$codesLeft = count(json_decode((string) $admin['recovery_codes'], true) ?: []);
$log = q('SELECT * FROM audit_log ORDER BY id DESC LIMIT 40')->fetchAll();

admin_start('Security', $admin, 'security');
?>
<div class="adm-head"><h1>Security</h1></div>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>

<?php if ($newCodes): ?>
<section class="adm-panel adm-codes">
  <h2>Save your recovery codes</h2>
  <p>If you lose your phone, each of these codes lets you log in <b>once</b>. Print them or store them in a password manager. They won't be shown again.</p>
  <ul><?php foreach ($newCodes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach ?></ul>
  <a class="adm-btn adm-btn-gold" href="/admin">I saved them — continue</a>
</section>
<?php endif ?>

<section class="adm-panel">
  <h2>Two-factor authentication <?= (int) $admin['totp_enabled'] ? '<span class="badge-st st-approved">ON</span>' : '<span class="badge-st st-rejected">OFF</span>' ?></h2>
  <?php if ($setupSecret): ?>
    <ol class="adm-steps">
      <li>Install <b>Google Authenticator</b>, <b>Microsoft Authenticator</b> or <b>Authy</b> on your phone.</li>
      <li>Scan this QR code (or type the key by hand):
        <div class="qr-wrap"><div id="qr" data-uri="<?= e(totp_uri($setupSecret, $admin['email'])) ?>"></div>
          <code class="secret"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></div></li>
      <li>Type the 6-digit code the app shows:</li>
    </ol>
    <form method="post" action="/admin/security" class="adm-form adm-inline">
      <?= csrf_field() ?><input type="hidden" name="do" value="enable_2fa">
      <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123456" class="code-input">
      <button type="submit" class="adm-btn adm-btn-red">Turn on 2FA</button>
    </form>
  <?php else: ?>
    <p>Every login asks for a code from your authenticator app. Recovery codes left: <b><?= $codesLeft ?></b></p>
    <form method="post" action="/admin/security" class="adm-form adm-inline">
      <?= csrf_field() ?><input type="hidden" name="do" value="new_codes">
      <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="Current code" class="code-input">
      <button type="submit" class="adm-btn">Create new recovery codes</button>
    </form>
  <?php endif ?>
</section>

<section class="adm-panel">
  <h2>Change password</h2>
  <form method="post" action="/admin/security" class="adm-form adm-grid">
    <?= csrf_field() ?><input type="hidden" name="do" value="password">
    <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password (14+ characters)<input type="password" name="password" required minlength="14" autocomplete="new-password"></label>
    <div class="span2"><button type="submit" class="adm-btn adm-btn-red">Change password</button></div>
  </form>
</section>

<section class="adm-panel">
  <h2>Recent activity</h2>
  <div class="adm-table-wrap">
  <table class="adm-table small">
    <thead><tr><th>When (UTC)</th><th>Who</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr class="<?= str_contains($l['action'], 'failed') || str_contains($l['action'], 'locked') ? 'warn' : '' ?>">
        <td><?= e($l['created_at']) ?></td><td><?= e($l['actor']) ?><?= $l['actor_id'] ? ' #' . (int) $l['actor_id'] : '' ?></td>
        <td><?= e($l['action']) ?></td><td><?= e($l['detail']) ?></td><td><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
</section>
<?php admin_end($setupSecret ? ['https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'] : []);
