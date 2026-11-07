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
use PrestaShopHeaven\Build\TestingPhp\Mail\FakeSmtpServer;
use PrestaShopHeaven\Build\TestingPhp\Mail\MimeParts;

/**
 * Attachments and headers of raw messages as Swift Mailer (PS 8) and Symfony Mailer (PS 9) write them.
 */
final class MimePartsTest extends TestCase
{
    private const RAW = "Subject: =?UTF-8?B?SW52b2ljZSDigqw=?=\r\n"
        . "To: customer@example.test\r\n"
        . "Content-Type: multipart/mixed;\r\n boundary=\"b1\"\r\n"
        . "\r\n"
        . "--b1\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\n"
        . "\r\n"
        . "Hello\r\n"
        . "--b1\r\n"
        . "Content-Type: application/pdf; name=manual.pdf\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-Disposition: attachment;\r\n filename=\"manual.pdf\"\r\n"
        . "\r\n"
        . "JVBERi0x\r\nLjQ=\r\n"
        . "--b1\r\n"
        . "Content-Type: text/plain; name=terms.txt\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n"
        . "Content-Disposition: attachment; filename*=UTF-8''terms%20%C3%A9.txt\r\n"
        . "\r\n"
        . "caf=C3=A9\r\n"
        . "--b1\r\n"
        . "Content-Type: text/csv\r\n"
        . "Content-Disposition: attachment; filename=list.csv\r\n"
        . "\r\n"
        . "a;b\r\n"
        . "--b1\r\n"
        . "Content-Type: image/png\r\n"
        . "Content-Disposition: inline; filename=logo.png\r\n"
        . "\r\n"
        . "png\r\n"
        . "--b1\r\n"
        . "no blank line in this part\r\n"
        . "--b1--\r\n";

    public function testTheAttachmentsAreDecodedInTheirOrder(): void
    {
        $attachments = MimeParts::attachments(self::RAW);

        self::assertSame(['manual.pdf', 'terms é.txt', 'list.csv'], array_keys($attachments));
        self::assertSame('%PDF-1.4', $attachments['manual.pdf']);
        self::assertSame('café', $attachments['terms é.txt']);
        self::assertSame('a;b', $attachments['list.csv']);
        self::assertSame($attachments, FakeSmtpServer::attachments(self::RAW), 'FakeSmtpServer keeps its static helper.');
        self::assertSame([], MimeParts::attachments("Subject: x\r\n\r\nNo parts\r\n"));
    }

    public function testAHeaderIsUnfoldedAndDecoded(): void
    {
        self::assertSame('Invoice €', MimeParts::header(self::RAW, 'Subject'));
        self::assertSame('customer@example.test', MimeParts::header(self::RAW, 'to'));
        self::assertSame('multipart/mixed; boundary="b1"', MimeParts::header(self::RAW, 'Content-Type'));
        self::assertNull(MimeParts::header(self::RAW, 'Cc'));
        self::assertNull(MimeParts::header('', 'Subject'));
    }
}
