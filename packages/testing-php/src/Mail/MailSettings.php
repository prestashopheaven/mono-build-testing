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
 * The mail settings of the shop during a test: points Mail::send() at an SMTP server (the fake one, or Mailpit) and
 * restores the settings afterwards. Global values, like the shop's own settings page writes them.
 */
final class MailSettings
{
    /** The settings it changes and restores. PS_SHOP_EMAIL too: a test (or pointAt()) may give the shop an address. */
    public const KEYS = ['PS_MAIL_METHOD', 'PS_MAIL_SERVER', 'PS_MAIL_SMTP_PORT', 'PS_MAIL_SMTP_ENCRYPTION', 'PS_MAIL_USER', 'PS_MAIL_PASSWD', 'PS_LOG_EMAILS', 'PS_SHOP_EMAIL'];

    /** The shop address during a test: a valid one, so a mail "to the shop" can be sent and found. */
    public const SHOP_EMAIL = 'owner@example.test';

    /** @var array<string, mixed> the values before the first change */
    private array $saved = [];

    /**
     * Saves the current settings once (a second call keeps the first values).
     */
    public function remember(): void
    {
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $this->saved)) {
                $this->saved[$key] = \Configuration::getGlobalValue($key);
            }
        }
    }

    /**
     * Makes Mail::send() use SMTP on $host:$port (no encryption, no login, every mail logged in ps_mail) and gives the
     * shop the address $shopEmail. Saves the settings first.
     */
    public function pointAt(string $host, int $port, string $shopEmail = self::SHOP_EMAIL): void
    {
        $this->remember();
        foreach (self::values($host, $port, class_exists('Swift_SmtpTransport'), $shopEmail) as $key => $value) {
            \Configuration::updateGlobalValue($key, $value);
        }
    }

    /**
     * Restores the saved settings (nothing when nothing was saved).
     */
    public function restore(): void
    {
        foreach ($this->saved as $key => $value) {
            \Configuration::updateGlobalValue($key, $value);
        }
        $this->saved = [];
    }

    /**
     * The settings for SMTP on $host:$port. Encryption: PS 8 (Swift Mailer) needs '': it passes the value on as the
     * scheme ("off://"). PS 9 (Symfony Mailer) needs 'off': anything else, '' too, means TLS ("ssl://"). User and
     * password as strings (PS 9 setters).
     *
     * @return array<string, int|string>
     */
    public static function values(string $host, int $port, bool $swiftMailer, string $shopEmail = self::SHOP_EMAIL): array
    {
        return [
            'PS_MAIL_METHOD' => 2,
            'PS_MAIL_SERVER' => $host,
            'PS_MAIL_SMTP_PORT' => (string) $port,
            'PS_MAIL_SMTP_ENCRYPTION' => $swiftMailer ? '' : 'off',
            'PS_MAIL_USER' => '',
            'PS_MAIL_PASSWD' => '',
            'PS_LOG_EMAILS' => 1,
            'PS_SHOP_EMAIL' => $shopEmail,
        ];
    }
}
