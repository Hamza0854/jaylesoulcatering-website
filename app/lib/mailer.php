<?php
/* Plain-text email. Drivers: smtp (Hostinger mailbox), mail (PHP mail()), log (local testing). */

function send_mail(string $to, string $subject, string $text): bool
{
    if (!valid_email($to) || preg_match('/[\r\n]/', $to . $subject)) return false;
    $driver = cfg('mail.driver', 'log');

    if ($driver === 'log') {
        $entry = '[' . now() . "] To: $to\nSubject: $subject\n\n$text\n" . str_repeat('-', 60) . "\n";
        return file_put_contents(ROOT . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    $from     = cfg('mail.from_email');
    $fromName = '=?UTF-8?B?' . base64_encode((string) cfg('mail.from_name', '')) . '?=';
    $subj     = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body     = chunk_split(base64_encode($text));
    $headers  = [
        "From: $fromName <$from>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];

    if ($driver === 'mail') {
        return mail($to, $subj, $body, implode("\r\n", $headers), '-f' . $from);
    }
    return smtp_send($from, $to, $subj, $headers, $body);
}

function smtp_send(string $from, string $to, string $subject, array $headers, string $body): bool
{
    $host = (string) cfg('mail.smtp_host');
    $port = (int) cfg('mail.smtp_port', 465);
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp   = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $err, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        error_log("SMTP connect to $host:$port failed: $err");
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (?string $line, array $ok) use ($fp, $read): string {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $res = $read();
        if (!in_array((int) substr($res, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP error after "' . strtok((string) $line, ' ') . '": ' . trim($res));
        }
        return $res;
    };

    try {
        $helo = 'EHLO ' . (parse_url((string) cfg('site_url'), PHP_URL_HOST) ?: 'localhost');
        $cmd(null, [220]);
        $cmd($helo, [250]);
        if ($port === 587) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
            $cmd($helo, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) cfg('mail.smtp_user')), [334]);
        $cmd(base64_encode((string) cfg('mail.smtp_pass')), [235]);
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $msg = "To: <$to>\r\nSubject: $subject\r\nDate: " . date('r') . "\r\n"
             . 'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>\r\n"
             . implode("\r\n", $headers) . "\r\n\r\n" . rtrim($body, "\r\n"); // base64 body: CRLF lines, never starts with "."
        fwrite($fp, $msg . "\r\n.\r\n");
        $cmd(null, [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        fclose($fp);
        return false;
    }
}
