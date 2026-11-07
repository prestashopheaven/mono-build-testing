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

declare(strict_types=1);

namespace PrestaShopHeaven\Build\TestingPhp\Mail;

/**
 * Reads the mails a shop sent from Mailpit (axllent/mailpit) through its API v1, for end-to-end mail tests: who got
 * what, with which subject, text, HTML and attachments. Point the shop at Mailpit's SMTP side with
 * MailSettings::pointAt($client->smtpHost(), $client->smtpPort()).
 *
 * The address of the API comes from the constructor, the environment variable MAILPIT_URL, or http://mailpit:8025 (the
 * network alias of the Mailpit container next to a test shop; the internal test shop publishes it on
 * http://localhost:8081). The SMTP side: MAILPIT_SMTP ("host:port"), or the host of the API on port 1025.
 *
 * The messages of the list and search calls are Mailpit's summaries (ID, From, To, Cc, Bcc, Subject, Created,
 * Attachments as a count, Snippet); message() gives the full message (also Text, HTML, Attachments with PartID,
 * FileName, ContentType and Size).
 */
final class MailpitClient
{
    /** The API address when nothing else is given. */
    public const DEFAULT_URL = 'http://mailpit:8025';

    /** Mailpit's SMTP port. */
    public const SMTP_PORT = 1025;

    private string $baseUrl;

    /** @var callable(string, string): array{status: int, body: string} */
    private $transport;

    /**
     * Uses the API at $baseUrl (else MAILPIT_URL, else DEFAULT_URL), over curl or the given transport (tests).
     *
     * @param (callable(string, string): array{status: int, body: string})|null $transport method and URL => status and body
     */
    public function __construct(?string $baseUrl = null, ?callable $transport = null)
    {
        $url = $baseUrl ?? (getenv('MAILPIT_URL') ?: self::DEFAULT_URL);
        $this->baseUrl = rtrim($url, '/');
        $this->transport = $transport ?? self::curlTransport(...);
    }

    /**
     * The API address in use.
     */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The host of Mailpit's SMTP side: MAILPIT_SMTP's host, else the host of the API.
     */
    public function smtpHost(): string
    {
        $smtp = (string) getenv('MAILPIT_SMTP');
        if ($smtp !== '') {
            return explode(':', $smtp)[0];
        }

        return (string) (parse_url($this->baseUrl, PHP_URL_HOST) ?: 'mailpit');
    }

    /**
     * The port of Mailpit's SMTP side: MAILPIT_SMTP's port, else 1025.
     */
    public function smtpPort(): int
    {
        $smtp = explode(':', (string) getenv('MAILPIT_SMTP'));

        return isset($smtp[1]) && (int) $smtp[1] > 0 ? (int) $smtp[1] : self::SMTP_PORT;
    }

    /**
     * True when the API answers (GET /api/v1/info); false on any error, so a test can skip without Mailpit.
     */
    public function isReachable(): bool
    {
        try {
            $this->json('GET', '/api/v1/info');

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * The newest messages, newest first (summaries).
     *
     * @return list<array<string, mixed>>
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function messages(int $limit = 50): array
    {
        return self::list($this->json('GET', '/api/v1/messages?limit=' . $limit));
    }

    /**
     * The messages that match a Mailpit search ("to:a@example.test", "subject:Invoice", "has:attachment"), newest
     * first (summaries).
     *
     * @return list<array<string, mixed>>
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function search(string $query, int $limit = 50): array
    {
        return self::list($this->json('GET', '/api/v1/search?query=' . rawurlencode($query) . '&limit=' . $limit));
    }

    /**
     * One message in full: From, To, Cc, Bcc, ReplyTo, Subject, Date, Text, HTML, Attachments, Inline.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when Mailpit does not answer as expected (an unknown ID: 404)
     */
    public function message(string $id): array
    {
        return $this->json('GET', '/api/v1/message/' . rawurlencode($id));
    }

    /**
     * The source of a message, as sent (MIME).
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function raw(string $id): string
    {
        return $this->send('GET', '/api/v1/message/' . rawurlencode($id) . '/raw');
    }

    /**
     * The decoded content of one part of a message (an attachment: its PartID from message()).
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function part(string $id, string $partId): string
    {
        return $this->send('GET', '/api/v1/message/' . rawurlencode($id) . '/part/' . rawurlencode($partId));
    }

    /**
     * The attachments of a message: file name => decoded content.
     *
     * @return array<string, string>
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function attachments(string $id): array
    {
        $files = [];
        foreach ((array) ($this->message($id)['Attachments'] ?? []) as $attachment) {
            if (is_array($attachment) && isset($attachment['PartID'], $attachment['FileName'])) {
                $files[(string) $attachment['FileName']] = $this->part($id, (string) $attachment['PartID']);
            }
        }

        return $files;
    }

    /**
     * Deletes every message (a clean inbox before a test).
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function deleteAll(): void
    {
        $this->send('DELETE', '/api/v1/messages');
    }

    /**
     * Deletes the messages that match a search (only those of this test).
     *
     * @throws \RuntimeException when Mailpit does not answer as expected
     */
    public function deleteSearch(string $query): void
    {
        $this->send('DELETE', '/api/v1/search?query=' . rawurlencode($query));
    }

    /**
     * Waits until at least $count messages match the search, and returns them (Mailpit stores a mail a moment after
     * the SMTP conversation).
     *
     * @return list<array<string, mixed>>
     *
     * @throws \RuntimeException when they are not there within $timeout seconds, or Mailpit does not answer
     */
    public function waitFor(string $query, int $count = 1, float $timeout = 10.0, float $interval = 0.2): array
    {
        $end = microtime(true) + $timeout;
        while (true) {
            $found = $this->search($query);
            if (count($found) >= $count) {
                return $found;
            }
            if (microtime(true) >= $end) {
                throw new \RuntimeException(sprintf('Mailpit has %d message(s) for "%s" after %.1f s, expected %d.', count($found), $query, $timeout, $count));
            }
            usleep((int) ($interval * 1000000));
        }
    }

    /**
     * The e-mail addresses of an address list of Mailpit (To, Cc, Bcc: [{"Name": ..., "Address": ...}]).
     *
     * @param mixed $list
     *
     * @return list<string>
     */
    public static function addresses($list): array
    {
        $addresses = [];
        foreach (is_array($list) ? $list : [] as $entry) {
            if (is_array($entry) && isset($entry['Address'])) {
                $addresses[] = (string) $entry['Address'];
            }
        }

        return $addresses;
    }

    /**
     * Sends a request with curl.
     *
     * @return array{status: int, body: string} status 0 when there is no connection
     *
     * @throws \RuntimeException when curl cannot be started
     */
    public static function curlTransport(string $method, string $url): array
    {
        $curl = curl_init($url) ?: throw new \RuntimeException('curl cannot open ' . $url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);

        return ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => is_string($body) ? $body : ''];
    }

    /**
     * Sends a request; returns the body of a 2xx answer.
     *
     * @throws \RuntimeException on any other answer, or without a connection
     */
    private function send(string $method, string $path): string
    {
        $response = ($this->transport)($method, $this->baseUrl . $path);
        if ($response['status'] < 200 || $response['status'] > 299) {
            throw new \RuntimeException(sprintf('Mailpit %s %s answered HTTP %d%s.', $method, $path, $response['status'], $response['status'] === 0 ? ' (no connection to ' . $this->baseUrl . ')' : ''));
        }

        return $response['body'];
    }

    /**
     * Sends a request; returns the JSON object of the answer.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when the answer is no 2xx or no JSON object
     */
    private function json(string $method, string $path): array
    {
        $data = json_decode($this->send($method, $path), true);
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Mailpit %s %s did not answer with a JSON object.', $method, $path));
        }

        return $data;
    }

    /**
     * The "messages" list of a list or search answer.
     *
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function list(array $data): array
    {
        return array_values(array_filter((array) ($data['messages'] ?? []), 'is_array'));
    }
}
