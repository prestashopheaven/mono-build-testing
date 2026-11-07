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
use PrestaShopHeaven\Build\TestingPhp\Mail\MailpitClient;
use PrestaShopHeaven\Build\TestingPhp\Mail\MailSettings;

/**
 * The mail settings of the shop: pointed at a server and back, and (when a Mailpit runs next to the shop, MAILPIT_URL
 * or http://mailpit:8025) a real mail read back through the Mailpit client.
 */
final class MailSettingsTest extends TestCase
{
    private MailSettings $settings;

    /** @var array<string, mixed> */
    private array $before = [];

    protected function setUp(): void
    {
        foreach (MailSettings::KEYS as $key) {
            $this->before[$key] = \Configuration::getGlobalValue($key);
        }
        $this->settings = new MailSettings();
    }

    protected function tearDown(): void
    {
        $this->settings->restore();
    }

    public function testPointAtWritesTheSettingsAndRestorePutsThemBack(): void
    {
        $this->settings->pointAt('smtp.heavenmonotesting.test', 2525, 'shop@heavenmonotesting.test');
        $this->settings->pointAt('second.heavenmonotesting.test', 2526);

        self::assertSame('second.heavenmonotesting.test', \Configuration::getGlobalValue('PS_MAIL_SERVER'));
        self::assertSame('2526', (string) \Configuration::getGlobalValue('PS_MAIL_SMTP_PORT'));
        self::assertSame(2, (int) \Configuration::getGlobalValue('PS_MAIL_METHOD'));
        self::assertSame(class_exists('Swift_SmtpTransport') ? '' : 'off', (string) \Configuration::getGlobalValue('PS_MAIL_SMTP_ENCRYPTION'));
        self::assertSame(MailSettings::SHOP_EMAIL, \Configuration::getGlobalValue('PS_SHOP_EMAIL'));

        $this->settings->restore();
        foreach ($this->before as $key => $value) {
            self::assertSame((string) $value, (string) \Configuration::getGlobalValue($key), "$key is the value from before the first pointAt().");
        }
        $this->settings->restore();
    }

    public function testAMailReachesMailpit(): void
    {
        $mailpit = new MailpitClient();
        if (!$mailpit->isReachable()) {
            self::markTestSkipped('No Mailpit at ' . $mailpit->baseUrl() . ' (set MAILPIT_URL, see the memory note on mail testing).');
        }
        $to = 'mailpit-' . bin2hex(random_bytes(4)) . '@heavenmonotesting.test';
        $this->settings->pointAt($mailpit->smtpHost(), $mailpit->smtpPort());

        self::assertTrue((bool) \Mail::send((int) \Configuration::get('PS_LANG_DEFAULT'), 'test', 'Through Mailpit', [], $to));

        $found = $mailpit->waitFor('to:' . $to);
        self::assertSame([$to], MailpitClient::addresses($found[0]['To'] ?? []));
        self::assertStringContainsString('Through Mailpit', (string) ($mailpit->message((string) $found[0]['ID'])['Subject'] ?? ''));
        $mailpit->deleteSearch('to:' . $to);
        \Db::getInstance()->delete('mail', '`recipient` = \'' . pSQL($to) . '\'');
    }
}
