<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    prestahaven.com
 * @copyright Since 2026 George van Engers
 * @license   https://opensource.org/licenses/MIT MIT License
 */

/*
 * Minimal SMTP server for the integration tests (Mail\FakeSmtpServer, recipe mails): accepts every command and writes
 * each message to <dir>/<n>.eml. Prints "READY <host:port>" once it listens.
 * Usage: php fake-smtp-server.php <dir>
 */
$dir = $argv[1] ?? '';
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php fake-smtp-server.php <existing dir>\n");
    exit(1);
}
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "Cannot listen: $errstr\n");
    exit(1);
}
fwrite(STDOUT, 'READY ' . stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);

$count = 0;
while ($connection = stream_socket_accept($server, -1)) {
    $reply = static function (string $line) use ($connection): void {
        fwrite($connection, $line . "\r\n");
    };
    $reply('220 localhost fake SMTP');
    $inData = false;
    $data = '';
    while (($line = fgets($connection)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                file_put_contents($dir . '/' . (++$count) . '.eml', $data);
                $data = '';
                $reply('250 OK queued');
            } else {
                $data .= str_starts_with($line, '..') ? substr($line, 1) : $line;
            }
            continue;
        }
        $verb = strtoupper(substr(trim($line), 0, 4));
        if ($verb === 'EHLO') {
            $reply('250-localhost');
            $reply('250 8BITMIME');
        } elseif ($verb === 'DATA') {
            $inData = true;
            $reply('354 End data with <CR><LF>.<CR><LF>');
        } elseif ($verb === 'QUIT') {
            $reply('221 Bye');
            break;
        } else {
            $reply('250 OK');
        }
    }
    fclose($connection);
}
