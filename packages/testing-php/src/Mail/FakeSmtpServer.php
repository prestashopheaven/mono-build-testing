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

use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * Test helper (recipe mails): starts a fake SMTP server (SmtpCatcher), points PrestaShop's mail settings at it and
 * restores them (MailSettings). Every message lands as <dir>/<n>.eml. Use start() in setUp() and stop() in tearDown();
 * the same instance can be started again after stop().
 */
final class FakeSmtpServer
{
    private SmtpCatcher $catcher;

    private MailSettings $settings;

    /**
     * Uses a new catcher and new saved settings, or the given ones (tests).
     */
    public function __construct(?SmtpCatcher $catcher = null, ?MailSettings $settings = null)
    {
        $this->catcher = $catcher ?? new SmtpCatcher();
        $this->settings = $settings ?? new MailSettings();
    }

    /**
     * Starts the server and makes Mail::send() use it (SMTP, no encryption, logged in ps_mail), with the shop address
     * MailSettings::SHOP_EMAIL.
     *
     * @throws \RuntimeException when the server does not start
     */
    public function start(): void
    {
        $this->catcher->start(TestedModule::isSet() ? TestedModule::name() : 'prestahaven');
        $this->settings->pointAt($this->catcher->host(), $this->catcher->port());
    }

    /**
     * Points PrestaShop at a host and port without a server: Mail::send() then fails (for fault tests).
     */
    public function configureUnreachable(): void
    {
        $this->settings->pointAt('127.0.0.1', 1);
    }

    /**
     * The messages received so far, decoded (quoted-printable).
     *
     * @return string[]
     */
    public function mails(): array
    {
        return $this->catcher->mails();
    }

    /**
     * The messages received so far, as sent (MIME, not decoded).
     *
     * @return string[]
     */
    public function rawMails(): array
    {
        return $this->catcher->rawMails();
    }

    /**
     * The attached files of a raw message (rawMails()): file name => decoded content, in the order of the message.
     *
     * @return array<string, string>
     */
    public static function attachments(string $raw): array
    {
        return MimeParts::attachments($raw);
    }

    /**
     * Restores the mail settings, kills the server and removes its messages.
     */
    public function stop(): void
    {
        $this->settings->restore();
        $this->catcher->stop();
    }
}
