<?php
/*
 * LOCAL DEVELOPMENT ONLY — mimics .htaccess for PHP's built-in server:
 *     php -S 127.0.0.1:5517 router.php
 * (On Hostinger, .htaccess does this job and this file is blocked.)
 */
$root = __DIR__;
$uri  = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if (preg_match('#^/(app|storage)(/|$)|/\.|\.(sql|md|log|ini|lock)$|^/router\.php#i', $uri)) {
    http_response_code(403);
    exit('Forbidden');
}
// old .html / .php address → clean address
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#^(.*?)(?:/index)?\.(html|php)$#', $uri, $m) && is_file($root . $uri)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . ($m[1] === '' ? '/' : $m[1]) . ($qs !== '' ? "?$qs" : ''), true, 301);
    exit;
}
if (str_starts_with($uri, '/uploads/') && preg_match('#\.(php\d?|phtml|html?|svg|js)$#i', $uri)) {
    http_response_code(403);
    exit;
}
// real static file
if ($uri !== '/' && is_file($root . $uri)) return false;

$path = rtrim($uri, '/');
$run = function (string $file) use ($root) {
    $_SERVER['SCRIPT_NAME'] = substr($file, strlen($root));
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));
    require $file;
};
if (is_dir($root . $path) && $path !== '') {
    if (is_file("$root$path/index.php")) { $run("$root$path/index.php"); return true; }
    if (is_file("$root$path/index.html")) { readfile("$root$path/index.html"); return true; }
}
if ($path === '') { readfile("$root/index.html"); return true; }
if (is_file("$root$path.html")) { header('Content-Type: text/html; charset=UTF-8'); readfile("$root$path.html"); return true; }
if (is_file("$root$path.php")) { $run("$root$path.php"); return true; }

http_response_code(404);
echo 'Not found';
return true;
