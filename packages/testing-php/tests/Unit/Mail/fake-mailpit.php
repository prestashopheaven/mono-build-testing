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
 * Router of the PHP built-in server that plays Mailpit's API v1 for MailpitClientTest (php -S 127.0.0.1:<port> this
 * file): one message, its raw source and one attachment; every request is answered with the method and path it got.
 */

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
header('X-Seen: ' . $method . ' ' . $path);

if ($method === 'GET' && $path === '/api/v1/info') {
    header('Content-Type: application/json');
    echo '{"Version":"v1.21.0"}';
} elseif ($method === 'GET' && ($path === '/api/v1/messages' || $path === '/api/v1/search')) {
    header('Content-Type: application/json');
    echo json_encode(['total' => 1, 'messages' => [['ID' => 'm1', 'Subject' => (string) ($_GET['query'] ?? 'all'), 'To' => [['Name' => '', 'Address' => 'a@example.test']]]]]);
} elseif ($method === 'GET' && $path === '/api/v1/message/m1') {
    header('Content-Type: application/json');
    echo json_encode(['ID' => 'm1', 'Subject' => 'Hello', 'Attachments' => [['PartID' => '2', 'FileName' => 'a.txt']]]);
} elseif ($method === 'GET' && $path === '/api/v1/message/m1/raw') {
    echo "Subject: Hello\r\n\r\nBody";
} elseif ($method === 'GET' && $path === '/api/v1/message/m1/part/2') {
    echo 'file content';
} elseif ($method === 'DELETE' && $path === '/api/v1/messages') {
    echo 'ok';
} else {
    http_response_code(404);
    echo 'not found';
}
