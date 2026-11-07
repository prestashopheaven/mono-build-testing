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
 * Reads the parts of a raw MIME message as a test needs them (FakeSmtpServer::rawMails()): its headers and its
 * attachments. Enough for the mails of Swift Mailer (PS 8) and Symfony Mailer (PS 9); no full MIME parser.
 */
final class MimeParts
{
    /**
     * The attached files of a raw message: file name => decoded content, in the order of the message.
     *
     * @return array<string, string>
     */
    public static function attachments(string $raw): array
    {
        $found = [];
        foreach (preg_split('/\r?\n--[^\r\n]+\r?\n/', $raw) ?: [] as $part) {
            $split = preg_split('/\r?\n\r?\n/', $part, 2);
            if (!is_array($split) || count($split) < 2) {
                continue;
            }
            $head = self::unfold($split[0]);
            if (preg_match('/^Content-Disposition:\s*attachment;.*?filename\*?=(?:UTF-8\'\')?"?([^";\r\n]+)"?/im', $head, $name) !== 1) {
                continue;
            }
            $encoding = preg_match('/^Content-Transfer-Encoding:\s*(\S+)/im', $head, $e) === 1 ? strtolower($e[1]) : '7bit';
            $body = $split[1];
            $found[rawurldecode(trim($name[1]))] = match ($encoding) {
                'base64' => (string) base64_decode((string) preg_replace('/\s+/', '', $body)),
                'quoted-printable' => quoted_printable_decode($body),
                default => $body,
            };
        }

        return $found;
    }

    /**
     * The value of a header of the message (the first one, unfolded and MIME-decoded), or null without it.
     */
    public static function header(string $raw, string $name): ?string
    {
        $split = preg_split('/\r?\n\r?\n/', $raw, 2) ?: [''];
        if (preg_match('/^' . preg_quote($name, '/') . ':[ \t]*(.*)$/im', self::unfold($split[0]), $m) !== 1) {
            return null;
        }

        return iconv_mime_decode(trim($m[1]), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: trim($m[1]);
    }

    /**
     * Unfolds header lines: a long value continues on the next line after a space or tab.
     */
    private static function unfold(string $head): string
    {
        return (string) preg_replace('/\r?\n[ \t]+/', ' ', $head);
    }
}
