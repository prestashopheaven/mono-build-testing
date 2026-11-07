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
use PrestaShopHeaven\Build\TestingPhp\Mail\SmtpCatcher;

/**
 * The fake SMTP server for real: a client talks SMTP to it, the messages land in its folder in the order they came,
 * and stop() leaves nothing behind.
 */
final class SmtpCatcherTest extends TestCase
{
    private SmtpCatcher $catcher;

    protected function setUp(): void
    {
        $this->catcher = new SmtpCatcher();
    }

    protected function tearDown(): void
    {
        $this->catcher->stop();
    }

    public function testItReceivesMessagesInTheirOrder(): void
    {
        self::assertFalse($this->catcher->isRunning());
        $this->catcher->start('heavenfoo');
        $this->catcher->start('heavenfoo');
        $dir = $this->catcher->dir();

        self::assertTrue($this->catcher->isRunning());
        self::assertSame('127.0.0.1', $this->catcher->host());
        self::assertGreaterThan(0, $this->catcher->port());
        self::assertStringContainsString('/heavenfoo-mails-', $dir);
        self::assertDirectoryExists($dir);
        self::assertSame([], $this->catcher->rawMails());

        $messages = [];
        for ($i = 1; $i <= 11; ++$i) {
            $messages[] = "Subject: Mail $i\r\n\r\nBody $i =C3=A9\r\n..dot line\r\n";
        }
        $replies = $this->talk($messages);

        self::assertStringStartsWith('220 ', $replies[0]);
        self::assertContains('250 8BITMIME', $replies);
        self::assertContains('221 Bye', $replies);
        $raw = $this->catcher->rawMails();
        self::assertCount(11, $raw);
        self::assertSame("Subject: Mail 1\r\n\r\nBody 1 =C3=A9\r\n.dot line\r\n", $raw[0], 'Dot-stuffing is undone.');
        self::assertStringStartsWith('Subject: Mail 10', $raw[9], 'Mail 10 comes after mail 9.');
        self::assertStringStartsWith('Subject: Mail 11', $raw[10]);
        self::assertStringContainsString('Body 1 é', $this->catcher->mails()[0], 'mails() decodes quoted-printable.');

        $this->catcher->stop();
        self::assertFalse($this->catcher->isRunning());
        self::assertDirectoryDoesNotExist($dir);
        self::assertSame('', $this->catcher->dir());
        self::assertSame('', $this->catcher->host());
        self::assertSame(0, $this->catcher->port());
        self::assertSame([], $this->catcher->mails());
    }

    public function testItCanBeStartedAgainAfterStop(): void
    {
        $this->catcher->start();
        $first = $this->catcher->dir();
        $this->catcher->stop();
        $this->catcher->start();

        self::assertTrue($this->catcher->isRunning());
        self::assertNotSame($first, $this->catcher->dir());
        self::assertStringContainsString('/prestahaven-mails-', $this->catcher->dir());
    }

    public function testAServerThatDoesNotStartIsReported(): void
    {
        $script = str_replace('\\', '/', sys_get_temp_dir()) . '/smtp-broken-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, "<?php\nfwrite(STDERR, 'cannot listen');\nexit(1);\n");
        $catcher = new SmtpCatcher($script);
        try {
            $catcher->start('heavenfoo');
            self::fail('The broken server started.');
        } catch (\RuntimeException $e) {
            self::assertSame('Fake SMTP server did not start: cannot listen', $e->getMessage());
            self::assertFalse($catcher->isRunning());
            self::assertSame('', $catcher->dir(), 'Its folder is removed again.');
        } finally {
            unlink($script);
        }
    }

    public function testTheScriptIsPartOfThePackage(): void
    {
        self::assertFileExists(SmtpCatcher::script());
        self::assertStringEndsWith('/resources/fake-smtp-server.php', SmtpCatcher::script());
    }

    public function testTheServerScriptRefusesAMissingFolder(): void
    {
        $process = proc_open([PHP_BINARY, SmtpCatcher::script(), '/no/such/folder'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $error = (string) stream_get_contents($pipes[2]);

        self::assertSame(1, proc_close($process));
        self::assertStringContainsString('Usage: php fake-smtp-server.php <existing dir>', $error);
    }

    public function testRemoveTreeRemovesNestedFoldersAndIgnoresMissingOnes(): void
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()) . '/smtp-tree-' . bin2hex(random_bytes(4));
        mkdir($dir . '/a/b', 0777, true);
        file_put_contents($dir . '/a/b/1.eml', 'x');
        file_put_contents($dir . '/2.eml', 'y');

        SmtpCatcher::removeTree($dir);
        SmtpCatcher::removeTree($dir);

        self::assertDirectoryDoesNotExist($dir);
    }

    /**
     * One SMTP session that sends every message, then QUIT; returns the reply lines.
     *
     * @param string[] $messages
     *
     * @return string[]
     */
    private function talk(array $messages): array
    {
        $socket = stream_socket_client('tcp://' . $this->catcher->host() . ':' . $this->catcher->port(), $errno, $errstr, 5);
        self::assertIsResource($socket, $errstr);
        $replies = [];
        $read = static function () use ($socket, &$replies): void {
            do {
                $line = rtrim((string) fgets($socket), "\r\n");
                $replies[] = $line;
            } while (preg_match('/^\d{3}-/', $line) === 1);
        };
        $send = static function (string $line) use ($socket, $read): void {
            fwrite($socket, $line . "\r\n");
            $read();
        };
        $read();
        $send('EHLO client.test');
        foreach ($messages as $message) {
            $send('MAIL FROM:<shop@example.test>');
            $send('RCPT TO:<customer@example.test>');
            $send('DATA');
            fwrite($socket, $message);
            $send('.');
        }
        $send('QUIT');
        fclose($socket);

        return $replies;
    }
}
