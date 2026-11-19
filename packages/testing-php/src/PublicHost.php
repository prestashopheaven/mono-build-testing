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

namespace PrestaShopHeaven\Build\TestingPhp;

/**
 * The test shops run on localhost, a host that is never public: a module that sends URLs to an outside service
 * (IndexNow, a feed, a sitemap ping) rightly skips them there, so a sending or queue test on the shop's own URLs proves
 * nothing (TST-15, heavenbingindexnow 1.0.0). Such tests move the URLs to a public example host with this helper and
 * assert what is sent; a test that keeps the shop's own host asserts the skip.
 */
final class PublicHost
{
    /** A public host reserved for examples (RFC 2606): safe to use, never reached by the module under test. */
    public const HOST = 'www.example.org';

    /**
     * Whether a host is one the test shops use and a module must treat as not public: localhost, an IP of a private
     * or loopback range, or a name under .localhost, .test, .local, .internal or .invalid.
     */
    public static function isTestHost(string $host): bool
    {
        $host = strtolower(trim($host, " \t\n\r[]."));
        if ($host === '' || $host === 'localhost') {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return preg_match('/\.(localhost|test|local|internal|invalid)$/', $host) === 1;
    }

    /**
     * The URL on the public example host: same scheme, path, query and fragment, the port left out
     * (http://localhost:8301/en/12-shirt.html -> https://www.example.org/en/12-shirt.html).
     *
     * @throws \InvalidArgumentException when the URL has no host
     */
    public static function url(string $url, string $host = self::HOST): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Not an absolute URL: %s', $url));
        }

        return 'https://' . $host . ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    /**
     * The URLs on the public example host, in the same order.
     *
     * @param list<string> $urls
     *
     * @return list<string>
     */
    public static function urls(array $urls, string $host = self::HOST): array
    {
        return array_map(static fn (string $url): string => self::url($url, $host), $urls);
    }
}
