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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\Mail\MailpitClient;

/**
 * The Mailpit client: the calls of API v1 (through a recording transport), the errors, waiting for a mail, the
 * addresses from the environment, and the curl transport against a fake Mailpit (PHP built-in server).
 */
final class MailpitClientTest extends TestCase
{
    /** @var list<array{string, string}> the requests of the recording transport */
    private array $requests = [];

    /** @var list<array{status: int, body: string}> the answers it gives, in order */
    private array $answers = [];

    protected function tearDown(): void
    {
        putenv('MAILPIT_URL');
        putenv('MAILPIT_SMTP');
    }

    public function testTheAddressesComeFromTheArgumentTheEnvironmentOrTheDefault(): void
    {
        putenv('MAILPIT_URL');
        putenv('MAILPIT_SMTP');
        $default = new MailpitClient();
        self::assertSame('http://mailpit:8025', $default->baseUrl());
        self::assertSame('mailpit', $default->smtpHost());
        self::assertSame(1025, $default->smtpPort());

        putenv('MAILPIT_URL=http://localhost:8081/');
        $fromEnv = new MailpitClient();
        self::assertSame('http://localhost:8081', $fromEnv->baseUrl());
        self::assertSame('localhost', $fromEnv->smtpHost());

        putenv('MAILPIT_SMTP=smtp.test:2525');
        self::assertSame('smtp.test', $fromEnv->smtpHost());
        self::assertSame(2525, $fromEnv->smtpPort());
        putenv('MAILPIT_SMTP=smtp.test');
        self::assertSame(1025, $fromEnv->smtpPort());

        putenv('MAILPIT_SMTP');
        self::assertSame('http://given.test:1', (new MailpitClient('http://given.test:1'))->baseUrl());
        self::assertSame('given.test', (new MailpitClient('http://given.test:1'))->smtpHost());
        self::assertSame('mailpit', (new MailpitClient('not a url'))->smtpHost());
    }

    public function testTheCallsOfTheApi(): void
    {
        $client = $this->client([
            $this->ok(['total' => 2, 'messages' => [['ID' => 'a'], 'not a message', ['ID' => 'b']]]),
            $this->ok(['messages' => [['ID' => 'c']]]),
            $this->ok(['ID' => 'c', 'Attachments' => [['PartID' => '2', 'FileName' => 'a.pdf'], ['PartID' => '3'], 'x']]),
            ['status' => 200, 'body' => 'PDF'],
            $this->ok(['ID' => 'c', 'Subject' => 'Hi']),
            ['status' => 200, 'body' => "Subject: Hi\r\n\r\n"],
            ['status' => 200, 'body' => 'part'],
            ['status' => 200, 'body' => ''],
            ['status' => 200, 'body' => ''],
            $this->ok([]),
        ]);

        self::assertSame([['ID' => 'a'], ['ID' => 'b']], $client->messages(10));
        self::assertSame([['ID' => 'c']], $client->search('to:a@example.test subject:"Hi there"'));
        self::assertSame(['a.pdf' => 'PDF'], $client->attachments('c'));
        self::assertSame('Hi', $client->message('c')['Subject']);
        self::assertSame("Subject: Hi\r\n\r\n", $client->raw('c'));
        self::assertSame('part', $client->part('c', '2'));
        $client->deleteAll();
        $client->deleteSearch('to:a@example.test');
        self::assertSame([], $client->messages(), 'An answer without "messages" is an empty list.');

        self::assertSame([
            ['GET', 'http://mailpit.test/api/v1/messages?limit=10'],
            ['GET', 'http://mailpit.test/api/v1/search?query=to%3Aa%40example.test%20subject%3A%22Hi%20there%22&limit=50'],
            ['GET', 'http://mailpit.test/api/v1/message/c'],
            ['GET', 'http://mailpit.test/api/v1/message/c/part/2'],
            ['GET', 'http://mailpit.test/api/v1/message/c'],
            ['GET', 'http://mailpit.test/api/v1/message/c/raw'],
            ['GET', 'http://mailpit.test/api/v1/message/c/part/2'],
            ['DELETE', 'http://mailpit.test/api/v1/messages'],
            ['DELETE', 'http://mailpit.test/api/v1/search?query=to%3Aa%40example.test'],
            ['GET', 'http://mailpit.test/api/v1/messages?limit=50'],
        ], $this->requests);
    }

    public function testAnAnswerThatIsNoSuccessIsAnError(): void
    {
        $client = $this->client([['status' => 404, 'body' => 'not found']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mailpit GET /api/v1/message/x answered HTTP 404.');

        $client->message('x');
    }

    public function testNoConnectionIsNamed(): void
    {
        $client = $this->client([['status' => 0, 'body' => '']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('answered HTTP 0 (no connection to http://mailpit.test).');

        $client->deleteAll();
    }

    public function testAnAnswerThatIsNoJsonObjectIsAnError(): void
    {
        $client = $this->client([['status' => 200, 'body' => '"text"']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not answer with a JSON object');

        $client->messages();
    }

    public function testIsReachable(): void
    {
        self::assertTrue($this->client([$this->ok(['Version' => '1'])])->isReachable());
        self::assertFalse($this->client([['status' => 0, 'body' => '']])->isReachable());
        self::assertFalse($this->client([['status' => 200, 'body' => 'html']])->isReachable());
    }

    public function testWaitForPollsUntilTheMailsAreThere(): void
    {
        $client = $this->client([
            $this->ok(['messages' => []]),
            $this->ok(['messages' => [['ID' => 'a']]]),
            $this->ok(['messages' => [['ID' => 'a'], ['ID' => 'b']]]),
        ]);

        self::assertSame([['ID' => 'a'], ['ID' => 'b']], $client->waitFor('to:x@example.test', 2, 5.0, 0.001));
        self::assertCount(3, $this->requests);
    }

    public function testWaitForGivesUpAfterTheTimeout(): void
    {
        $client = $this->client(array_fill(0, 100, $this->ok(['messages' => [['ID' => 'a']]])));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mailpit has 1 message(s) for "to:x@example.test" after 0.0 s, expected 2.');

        $client->waitFor('to:x@example.test', 2, 0.0);
    }

    public function testTheAddressesOfAList(): void
    {
        self::assertSame(['a@example.test', 'b@example.test'], MailpitClient::addresses([['Name' => 'A', 'Address' => 'a@example.test'], ['Name' => 'no address'], 'x', ['Address' => 'b@example.test']]));
        self::assertSame([], MailpitClient::addresses(null));
    }

    public function testTheCurlTransportAgainstAFakeMailpit(): void
    {
        $port = $this->freePort();
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/fake-mailpit.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            $client = new MailpitClient('http://127.0.0.1:' . $port);
            $this->waitUntilListening($port);

            self::assertTrue($client->isReachable());
            self::assertSame('to:a@example.test', $client->search('to:a@example.test')[0]['Subject']);
            self::assertSame(['a@example.test'], MailpitClient::addresses($client->messages()[0]['To']));
            self::assertSame("Subject: Hello\r\n\r\nBody", $client->raw('m1'));
            self::assertSame(['a.txt' => 'file content'], $client->attachments('m1'));
            $client->deleteAll();
            $answer = MailpitClient::curlTransport('PATCH', 'http://127.0.0.1:' . $port . '/api/v1/messages');
            self::assertSame(404, $answer['status']);
            self::assertSame('not found', $answer['body']);
        } finally {
            proc_terminate($process, 9);
            proc_close($process);
        }
        self::assertSame(0, MailpitClient::curlTransport('GET', 'http://127.0.0.1:' . $port . '/api/v1/info')['status'], 'Nothing listens any more.');
    }

    /**
     * A client of http://mailpit.test that records its requests and gives $answers in order.
     *
     * @param list<array{status: int, body: string}> $answers
     */
    private function client(array $answers): MailpitClient
    {
        $this->answers = $answers;
        $this->requests = [];

        return new MailpitClient('http://mailpit.test/', function (string $method, string $url): array {
            $this->requests[] = [$method, $url];

            return array_shift($this->answers) ?? ['status' => 500, 'body' => ''];
        });
    }

    /**
     * A 200 answer with a JSON body.
     *
     * @param array<mixed> $data
     *
     * @return array{status: int, body: string}
     */
    private function ok(array $data): array
    {
        return ['status' => 200, 'body' => (string) json_encode($data)];
    }

    /**
     * A TCP port of 127.0.0.1 that is free right now.
     */
    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    /**
     * Waits (at most 10 s) until something listens on the port.
     */
    private function waitUntilListening(int $port): void
    {
        $end = microtime(true) + 10;
        while (microtime(true) < $end) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }
            usleep(50000);
        }
        self::fail("The fake Mailpit does not listen on port $port.");
    }
}
