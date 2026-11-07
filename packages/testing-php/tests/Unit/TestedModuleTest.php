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
use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * The module under test: set once, read by the helpers, with the name, prefix and main class derived from it.
 */
final class TestedModuleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestedModule::reset();
    }

    public function testNothingIsSetAtFirst(): void
    {
        TestedModule::reset();

        self::assertFalse(TestedModule::isSet());
        self::assertSame('', TestedModule::dir());
        self::assertSame('heavenfoo', TestedModule::name('heavenfoo'), 'A given name needs no module under test.');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('IntegrationBootstrap::boot()');

        TestedModule::name();
    }

    public function testTheModuleIsKeptWithItsFolder(): void
    {
        TestedModule::set('heavenfoo', 'C:\\shop\\modules\\heavenfoo\\');

        self::assertTrue(TestedModule::isSet());
        self::assertSame('heavenfoo', TestedModule::name());
        self::assertSame('heavenfoo', TestedModule::name(''), 'An empty name means the module under test.');
        self::assertSame('heavenbar', TestedModule::name('heavenbar'));
        self::assertSame('C:/shop/modules/heavenfoo', TestedModule::dir());
        self::assertSame('HEAVENFOO_', TestedModule::configurationPrefix());
        self::assertSame('HEAVENBAR_', TestedModule::configurationPrefix('heavenbar'));
        self::assertSame('Heavenfoo', TestedModule::mainClass());
        self::assertSame('Heavenbar', TestedModule::mainClass('heavenbar'));

        TestedModule::set('heavenbar');
        self::assertSame('', TestedModule::dir());
    }

    #[DataProvider('wrongNames')]
    public function testOnlyATechnicalNameIsAccepted(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestedModule::set($name);
    }

    /**
     * Names PrestaShop would not accept as a module folder.
     *
     * @return iterable<string, array{string}>
     */
    public static function wrongNames(): iterable
    {
        yield 'empty' => [''];
        yield 'capital' => ['Heavenfoo'];
        yield 'dash' => ['heaven-foo'];
        yield 'digit first' => ['1heaven'];
    }
}
