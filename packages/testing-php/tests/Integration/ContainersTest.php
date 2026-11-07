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
use PrestaShopHeaven\Build\TestingPhp\Containers;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;
use PrestaShopHeaven\Runtime\Cqrs\ModuleCommandBus;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Both containers of a real shop: the front one on the context (Module::get() works), the back-office one from the
 * booted kernel, each with the bus of the module.
 */
final class ContainersTest extends TestCase
{
    public function testTheFrontContainerIsPutOnTheContextOnce(): void
    {
        $container = Containers::front();

        self::assertSame($container, \Context::getContext()->container);
        self::assertSame($container, Containers::front());
        self::assertTrue($container->has(ModuleCommandBus::serviceId(TestedModule::name())));
    }

    public function testTheBackOfficeContainerIsTheOneOfTheBootedKernel(): void
    {
        $kernel = $GLOBALS['kernel'];
        self::assertInstanceOf(KernelInterface::class, $kernel);

        self::assertSame($kernel->getContainer(), Containers::backOffice());
        self::assertInstanceOf(ModuleCommandBus::class, Containers::backOffice()->get(ModuleCommandBus::serviceId(TestedModule::name())));
    }

    public function testBootKernelBootsTheKernelOfThisVersion(): void
    {
        $kernel = Containers::bootKernel(false);

        self::assertContains(get_class($kernel), ['AdminKernel', 'AppKernel']);
        self::assertTrue($kernel->getContainer()->has('request_stack'));
    }
}
