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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Integration\Mail;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\Mail\FakeSmtpServer;
use PrestaShopHeaven\Build\TestingPhp\Mail\MailSettings;
use PrestaShopHeaven\Build\TestingPhp\Mail\MimeParts;

/**
 * PrestaShop's own Mail::send() (Swift Mailer on PS 8, Symfony Mailer on PS 9) to the fake SMTP server: the mail and
 * its attachment arrive, an unreachable server makes the send fail, and stop() puts the shop's settings back.
 */
final class FakeSmtpServerTest extends TestCase
{
    private const TO = 'customer@heavenmonotesting.test';

    private FakeSmtpServer $smtp;

    /** @var array<string, mixed> */
    private array $settings = [];

    protected function setUp(): void
    {
        foreach (MailSettings::KEYS as $key) {
            $this->settings[$key] = \Configuration::getGlobalValue($key);
        }
        $this->smtp = new FakeSmtpServer();
    }

    protected function tearDown(): void
    {
        $this->smtp->stop();
        \Db::getInstance()->delete('mail', '`recipient` = \'' . pSQL(self::TO) . '\'');
    }

    public function testAMailOfPrestaShopArrivesWithItsAttachment(): void
    {
        $this->smtp->start();

        self::assertSame(MailSettings::SHOP_EMAIL, \Configuration::getGlobalValue('PS_SHOP_EMAIL'));
        self::assertTrue($this->send('Fixture mail', ['content' => 'hello file', 'name' => 'hello.txt', 'mime' => 'text/plain']), 'Mail::send() failed; see ps_log.');

        $raw = $this->smtp->rawMails();
        self::assertCount(1, $raw);
        self::assertStringContainsString('Fixture mail', (string) MimeParts::header($raw[0], 'Subject'));
        self::assertStringContainsString(self::TO, (string) MimeParts::header($raw[0], 'To'));
        self::assertSame(['hello.txt' => 'hello file'], FakeSmtpServer::attachments($raw[0]));
        self::assertCount(1, $this->smtp->mails());
    }

    public function testAnUnreachableServerMakesTheSendFail(): void
    {
        $this->smtp->configureUnreachable();

        self::assertFalse($this->send('Not sent'));
        self::assertSame([], $this->smtp->rawMails());
    }

    public function testStopRestoresTheSettingsAndTheServerCanStartAgain(): void
    {
        $this->smtp->start();
        $this->smtp->stop();
        foreach ($this->settings as $key => $value) {
            self::assertSame((string) $value, (string) \Configuration::getGlobalValue($key), $key);
        }

        $this->smtp->start();
        self::assertTrue($this->send('Second start'));
        self::assertCount(1, $this->smtp->rawMails());
    }

    /**
     * Sends PrestaShop's core test mail in the default language of the shop.
     *
     * @param array<string, string>|null $attachment
     */
    private function send(string $subject, ?array $attachment = null): bool
    {
        return (bool) \Mail::send((int) \Configuration::get('PS_LANG_DEFAULT'), 'test', $subject, [], self::TO, null, null, null, $attachment);
    }
}
