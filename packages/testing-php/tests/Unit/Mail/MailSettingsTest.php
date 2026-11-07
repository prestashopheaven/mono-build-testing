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
use PrestaShopHeaven\Build\TestingPhp\Mail\MailSettings;

/**
 * The SMTP settings per mailer (the rest of MailSettings writes PrestaShop's configuration: integration test).
 */
final class MailSettingsTest extends TestCase
{
    public function testSwiftMailerGetsAnEmptyEncryptionAndSymfonyMailerOff(): void
    {
        $ps8 = MailSettings::values('127.0.0.1', 2525, true);
        $ps9 = MailSettings::values('mailpit', 1025, false, 'shop@example.test');

        self::assertSame([
            'PS_MAIL_METHOD' => 2,
            'PS_MAIL_SERVER' => '127.0.0.1',
            'PS_MAIL_SMTP_PORT' => '2525',
            'PS_MAIL_SMTP_ENCRYPTION' => '',
            'PS_MAIL_USER' => '',
            'PS_MAIL_PASSWD' => '',
            'PS_LOG_EMAILS' => 1,
            'PS_SHOP_EMAIL' => 'owner@example.test',
        ], $ps8);
        self::assertSame('off', $ps9['PS_MAIL_SMTP_ENCRYPTION']);
        self::assertSame('mailpit', $ps9['PS_MAIL_SERVER']);
        self::assertSame('shop@example.test', $ps9['PS_SHOP_EMAIL']);
        self::assertSame(array_keys($ps8), MailSettings::KEYS, 'Every key it writes is restored.');
    }
}
