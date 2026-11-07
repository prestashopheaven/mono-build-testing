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
 * The SMTP server of FakeSmtpServer without PrestaShop: runs resources/fake-smtp-server.php in its own process on a
 * free port of 127.0.0.1 and reads the messages it wrote (<dir>/<n>.eml). FakeSmtpServer points PrestaShop at it.
 */
final class SmtpCatcher
{
    /** @var string the folder of the messages, '' while not started */
    private string $dir = '';

    /** @var resource|null the server process */
    private $process;

    private string $host = '';

    private int $port = 0;

    /**
     * Uses the server script of this package, or another one (tests).
     *
     * @param string|null $script a PHP script that takes the mail folder and prints "READY <host:port>"
     */
    public function __construct(private readonly ?string $script = null)
    {
    }

    /**
     * Starts the server (once; a second call keeps the running one).
     *
     * @param string $prefix start of the name of the temporary mail folder (the module name, so leftovers are traceable)
     *
     * @throws \RuntimeException when the server does not start
     */
    public function start(string $prefix = 'prestahaven'): void
    {
        if (is_resource($this->process)) {
            return;
        }
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()) . '/' . $prefix . '-mails-' . uniqid();
        mkdir($this->dir);
        $process = proc_open([PHP_BINARY, $this->script ?? self::script(), $this->dir], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->process = is_resource($process) ? $process : null;
        $ready = is_resource($process) ? (string) fgets($pipes[1]) : '';
        if (!str_starts_with($ready, 'READY ')) {
            // Only read stderr after a failure: stream_get_contents() blocks while the server runs.
            $error = is_resource($process) ? trim((string) stream_get_contents($pipes[2])) : 'proc_open() failed';
            $this->stop();

            throw new \RuntimeException('Fake SMTP server did not start: ' . $error);
        }
        [$host, $port] = explode(':', trim(substr($ready, 6)));
        $this->host = $host;
        $this->port = (int) $port;
    }

    /**
     * The script of the server (resources/fake-smtp-server.php of this package).
     */
    public static function script(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2)) . '/resources/fake-smtp-server.php';
    }

    /**
     * True while the server runs.
     */
    public function isRunning(): bool
    {
        return is_resource($this->process);
    }

    /**
     * The host the server listens on ('' while not started).
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * The port the server listens on (0 while not started).
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * The folder the messages land in ('' while not started).
     */
    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * The messages received so far, as sent (MIME, not decoded), oldest first.
     *
     * @return string[]
     */
    public function rawMails(): array
    {
        $files = $this->dir === '' ? [] : (glob($this->dir . '/*.eml') ?: []);
        // <n>.eml: in the order of arrival, also from 10 on.
        usort($files, static fn (string $a, string $b): int => (int) basename($a, '.eml') <=> (int) basename($b, '.eml'));

        return array_map(static fn (string $file): string => (string) file_get_contents($file), $files);
    }

    /**
     * The messages received so far, decoded (quoted-printable), oldest first.
     *
     * @return string[]
     */
    public function mails(): array
    {
        return array_map(static fn (string $raw): string => quoted_printable_decode($raw), $this->rawMails());
    }

    /**
     * Kills the server (SIGKILL, so proc_close() never waits) and removes the folder with its messages. The catcher
     * can be started again afterwards.
     */
    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, 9);
            proc_close($this->process);
        }
        $this->process = null;
        if ($this->dir !== '') {
            self::removeTree($this->dir);
        }
        $this->dir = '';
        $this->host = '';
        $this->port = 0;
    }

    /**
     * Removes a folder with everything in it (nothing when it does not exist).
     */
    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) && !is_link($path) ? self::removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
