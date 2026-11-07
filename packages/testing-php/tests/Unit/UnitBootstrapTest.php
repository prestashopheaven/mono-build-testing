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

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\UnitBootstrap;

/**
 * The unit bootstrap: the guard constants and the stand-ins of interfaces PrestaShop ships.
 */
final class UnitBootstrapTest extends TestCase
{
    public function testBootDefinesTheConstantsAndTheTranslatorInterface(): void
    {
        UnitBootstrap::boot();
        UnitBootstrap::boot();

        self::assertIsString(constant('_PS_VERSION_'));
        self::assertIsString(constant('_DB_PREFIX_'));
        self::assertTrue(interface_exists('Symfony\Contracts\Translation\TranslatorInterface'));
        self::assertSame('0.0.0-unit', UnitBootstrap::PS_VERSION);
        self::assertSame('ps_', UnitBootstrap::DB_PREFIX);
    }

    public function testAConstantIsDefinedOnce(): void
    {
        $name = 'HEAVEN_UNIT_BOOTSTRAP_' . strtoupper(bin2hex(random_bytes(4)));

        self::assertTrue(UnitBootstrap::constant($name, 'first'));
        self::assertFalse(UnitBootstrap::constant($name, 'second'));
        self::assertSame('first', constant($name));
    }

    public function testTheStubsAreFilesOfThePackage(): void
    {
        $stubs = UnitBootstrap::stubs();

        self::assertSame(['Symfony\Contracts\Translation\TranslatorInterface'], array_keys($stubs));
        foreach ($stubs as $file) {
            self::assertFileExists($file);
            self::assertStringEndsWith('.stub', $file);
        }
    }

    public function testOnlyMissingInterfacesAreLoaded(): void
    {
        $name = 'HeavenUnitStub' . bin2hex(random_bytes(4));
        $file = sys_get_temp_dir() . '/' . $name . '.stub';
        file_put_contents($file, "<?php\ninterface $name\n{\n}\n");
        try {
            $stubs = [$name => $file, \Countable::class => '/does/not/matter.stub', self::class => '/neither.stub'];

            self::assertSame([$name], UnitBootstrap::loadStubs($stubs));
            self::assertTrue(interface_exists($name, false));
            self::assertSame([], UnitBootstrap::loadStubs($stubs), 'A second call loads nothing.');
        } finally {
            unlink($file);
        }
    }

    public function testAMissingStubFileIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The stub of HeavenNoSuchInterface is missing');

        UnitBootstrap::loadStubs(['HeavenNoSuchInterface' => '/no/such/file.stub']);
    }
}
