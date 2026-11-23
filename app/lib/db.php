<?php
/* Database: PDO + MySQL, real prepared statements only (no string-built SQL). */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

/** q('SELECT * FROM users WHERE id = ?', [$id])->fetch() */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(int $offset = 0): string
{
    return gmdate('Y-m-d H:i:s', time() + $offset);
}

function audit(string $actor, ?int $actorId, string $action, string $detail = ''): void
{
    try {
        q('INSERT INTO audit_log (actor, actor_id, action, detail, ip, created_at) VALUES (?,?,?,?,?,?)',
          [$actor, $actorId, $action, mb_substr($detail, 0, 255), client_ip(), now()]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}
