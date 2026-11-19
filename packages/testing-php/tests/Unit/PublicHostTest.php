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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\PublicHost;

/**
 * Test URLs on a public example host, and which hosts the test shops use.
 */
final class PublicHostTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function hosts(): array
    {
        return [
            'localhost' => ['localhost', true],
            'localhost with case and dot' => ['LocalHost.', true],
            'empty' => ['', true],
            'loopback ip' => ['127.0.0.1', true],
            'private ip' => ['192.168.1.20', true],
            'ipv6 loopback' => ['[::1]', true],
            'test name' => ['shop.test', true],
            'localhost subdomain' => ['app.localhost', true],
            'public ip' => ['8.8.8.8', false],
            'public name' => ['www.example.org', false],
            'name with test inside' => ['testshop.com', false],
        ];
    }

    #[DataProvider('hosts')]
    public function testIsTestHost(string $host, bool $expected): void
    {
        self::assertSame($expected, PublicHost::isTestHost($host));
    }

    public function testUrlMovesTheUrlToThePublicHost(): void
    {
        self::assertSame('https://www.example.org/en/12-shirt.html', PublicHost::url('http://localhost:8301/en/12-shirt.html'));
        self::assertSame('https://www.example.org/index.php?id_product=3#top', PublicHost::url('http://localhost:8301/index.php?id_product=3#top'));
        self::assertSame('https://www.example.org/', PublicHost::url('http://localhost:8301'));
        self::assertSame('https://shop.example.com/a', PublicHost::url('http://localhost/a', 'shop.example.com'));
        self::assertSame(['https://www.example.org/a', 'https://www.example.org/b'], PublicHost::urls(['http://localhost/a', 'http://shop.test/b']));
    }

    public function testUrlRefusesARelativeUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PublicHost::url('/en/12-shirt.html');
    }
}
