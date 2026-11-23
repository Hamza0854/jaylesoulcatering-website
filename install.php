<?php
/*
 * ONE-TIME INSTALLER  →  https://jaylesoulcatering.com/install?key=YOUR_INSTALL_KEY
 * Creates the database tables, adds the existing video testimonials and the first admin account.
 * It locks itself afterwards (storage/installed.lock). Delete this file once you're done.
 */
require __DIR__ . '/app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$lock = ROOT . '/storage/installed.lock';
$key  = (string) cfg('install_key', '');
$given = is_string($_REQUEST['key'] ?? null) ? $_REQUEST['key'] : '';

function install_page(string $body): never
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Install</title><link rel="stylesheet" href="/assets/css/admin.css"></head>'
       . '<body class="adm adm-guest"><main class="adm-main"><div class="adm-auth">' . $body . '</div></main></body></html>';
    exit;
}

if (is_file($lock)) install_page('<h1>Already <em>installed</em></h1><p>Delete <code>install.php</code> from the server. Go to <a href="/admin">the admin panel</a>.</p>');
if (strlen($key) < 20) install_page('<h1>Installer <em>locked</em></h1><p>Set <code>install_key</code> in <code>app/config.php</code> to a random string of at least 20 characters, then open <code>/install?key=…</code></p>');
try { $allowed = rate_limit('install:' . client_ip(), 10, 3600); } catch (PDOException) { $allowed = true; } // tables may not exist yet
if (!$allowed || !hash_equals($key, $given)) {
    http_response_code(403);
    install_page('<h1>Wrong <em>key</em></h1>');
}

$error = '';
if (is_post()) {
    csrf_check();
    $name  = post_str('name', 80);
    $email = normalize_email(post_str('email', 190));
    $pw    = (string) ($_POST['password'] ?? '');
    try {
        if (mb_strlen($name) < 2) throw new UserError('Enter your name.');
        if (!valid_email($email)) throw new UserError('Enter a valid email.');
        if (mb_strlen($pw) < 14) throw new UserError('Admin password must be at least 14 characters.');
        if ($p = password_problem($pw, $email)) throw new UserError($p);

        // 1. tables
        $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(APP . '/schema.sql'));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) db()->exec($stmt);

        if ((int) q('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) throw new UserError('An admin already exists. Installation was already done.');

        // 2. first admin
        q('INSERT INTO admins (name, email, password_hash, created_at) VALUES (?,?,?,?)', [$name, $email, hash_password($pw), now()]);
        $adminId = (int) db()->lastInsertId();

        // 3. existing video testimonials (only if the table is empty)
        if ((int) q('SELECT COUNT(*) FROM testimonials')->fetchColumn() === 0) {
            $seed = [
                ['Private Dining Guest', 'A testimony on how a private dining event can be done', 'private-dinner', 'facebook', 'https://www.facebook.com/reel/1553656299054690/', null],
                ['Happy Customer', null, null, 'youtube', 'https://www.youtube.com/watch?v=Of20yQSj1rA', null],
            ];
            foreach ([36, 35, 34, 33, 32] as $n) {
                if (is_file(ROOT . "/assets/images/testimonial-video-$n.mp4")) $seed[] = ['Happy Customer', null, null, null, null, "assets/images/testimonial-video-$n.mp4"];
            }
            $i = 0;
            foreach ($seed as [$author, $title, $event, $prov, $url, $file]) {
                // stagger dates so the order on the page stays as listed
                q("INSERT INTO testimonials (source, author_name, event_type, title, kind, embed_provider, embed_url, status, featured, created_at, reviewed_at, reviewed_by)
                   VALUES ('admin', ?, ?, ?, 'video', ?, ?, 'approved', ?, ?, ?, ?)",
                  [$author, $event, $title, $prov, $url, $i === 0 ? 1 : 0, now(-60 * $i), now(), $adminId]);
                $tid = (int) db()->lastInsertId();
                if ($file) {
                    q("INSERT INTO testimonial_media (testimonial_id, type, path, mime, size, sort) VALUES (?, 'video', ?, 'video/mp4', ?, 0)",
                      [$tid, $file, filesize(ROOT . '/' . $file)]);
                }
                $i++;
            }
        }
        file_put_contents($lock, 'installed ' . now());
        audit('system', $adminId, 'installed');
        install_page('<h1>All <em>set!</em></h1><ol><li>In <code>app/config.php</code> set <code>install_key</code> back to <code>\'\'</code>.</li>'
            . '<li>Delete <code>install.php</code> from the server.</li><li><a href="/admin/login">Log in to the admin panel</a> and set up two-factor authentication.</li></ol>');
    } catch (UserError $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log((string) $e);
        $error = 'Database error: check the db settings in app/config.php. (' . $e->getCode() . ')';
    }
}

install_page('<h1>Install <em>Testimonials</em></h1><p class="muted">Creates the database tables and your admin account.</p>'
    . ($error ? '<div class="alert alert-error">' . e($error) . '</div>' : '')
    . '<form method="post" class="adm-form">' . csrf_field() . '<input type="hidden" name="key" value="' . e($given) . '">'
    . '<label>Your name<input name="name" required maxlength="80"></label>'
    . '<label>Admin email<input type="email" name="email" required autocomplete="username"></label>'
    . '<label>Admin password (14+ characters)<input type="password" name="password" required minlength="14" autocomplete="new-password"></label>'
    . '<button class="adm-btn adm-btn-red" type="submit">Install</button></form>');
