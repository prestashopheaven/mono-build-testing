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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\IntegrationBootstrap;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bootstrap of the fixture module (tests/Integration/bootstrap.php) is IntegrationBootstrap::boot(): it booted
 * PrestaShop, the kernel and set the module under test; a second call changes nothing.
 */
final class IntegrationBootstrapTest extends TestCase
{
    public function testTheShopTheKernelAndTheModuleAreThere(): void
    {
        self::assertIsString(constant('_PS_VERSION_'));
        self::assertIsString(constant('_PS_ADMIN_DIR_'));
        self::assertTrue(IntegrationBootstrap::isShop(IntegrationBootstrap::psRoot()));
        self::assertInstanceOf(KernelInterface::class, $GLOBALS['kernel'] ?? null);
        self::assertTrue(TestedModule::isSet());
        self::assertInstanceOf(\Module::class, \Module::getInstanceByName(TestedModule::name()));
        self::assertFileExists(TestedModule::dir() . '/' . TestedModule::name() . '.php');
    }

    public function testASecondBootKeepsTheKernel(): void
    {
        $kernel = $GLOBALS['kernel'];

        IntegrationBootstrap::boot(TestedModule::name(), TestedModule::dir());

        self::assertSame($kernel, $GLOBALS['kernel']);
    }

    public function testTheRootComesFromTheEnvironment(): void
    {
        $previous = getenv('_PS_ROOT_DIR_');
        try {
            putenv('_PS_ROOT_DIR_=/somewhere/else/');
            self::assertSame('/somewhere/else', IntegrationBootstrap::psRoot());
            putenv('_PS_ROOT_DIR_');
            self::assertSame(IntegrationBootstrap::DEFAULT_ROOT, IntegrationBootstrap::psRoot());
        } finally {
            putenv($previous === false ? '_PS_ROOT_DIR_' : '_PS_ROOT_DIR_=' . $previous);
        }
    }

    public function testWithoutAShopTheMessageSaysHowToRunTheTests(): void
    {
        self::assertFalse(IntegrationBootstrap::isShop('/no/shop/here'));
        self::assertStringContainsString('(looked in /no/shop/here)', IntegrationBootstrap::missingShopMessage('/no/shop/here'));
        self::assertStringContainsString('vendor/bin/ps-module shops integration <ps-version>', IntegrationBootstrap::missingShopMessage('/x'));
    }
}
