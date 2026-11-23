<?php
/* Loaded first by every PHP page:  require __DIR__ . '/../app/bootstrap.php'; */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

if (!is_file(APP . '/config.php')) {
    http_response_code(503);
    exit('This feature is not configured yet. Copy app/config.sample.php to app/config.php.');
}
$GLOBALS['CFG'] = require APP . '/config.php';

/** cfg('mail.smtp_host') — dotted lookup into app/config.php */
function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

error_reporting(E_ALL);
ini_set('display_errors', cfg('env') === 'local' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

require APP . '/lib/db.php';
require APP . '/lib/security.php';
require APP . '/lib/auth.php';
require APP . '/lib/mailer.php';
require APP . '/lib/totp.php';
require APP . '/lib/uploads.php';
require APP . '/lib/testimonials.php';
require APP . '/views/layout.php';

/** Message that is safe to show to the visitor (everything else is logged, not shown). */
final class UserError extends RuntimeException {}

send_security_headers();
start_session();

set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    if (!headers_sent()) http_response_code(500);
    echo cfg('env') === 'local'
        ? '<pre>' . htmlspecialchars((string) $e) . '</pre>'
        : 'Sorry, something went wrong on our side. Please try again in a moment.';
});
