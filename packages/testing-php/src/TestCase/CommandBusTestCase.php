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

namespace PrestaShopHeaven\Build\TestingPhp\TestCase;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\Containers;
use PrestaShopHeaven\Runtime\Cqrs\HandlerNotFoundException;
use PrestaShopHeaven\Runtime\Cqrs\ModuleCommandBus;

/**
 * The integration test every module has for its command bus (ARCH-08, runtime-cqrs): the bus exists under
 * prestashopheaven.<module>.command_bus in both containers of a real shop, so hooks and front controllers (front
 * container) and Symfony code (back office) can dispatch through it, and ModuleServices gives it to the module. Each
 * command and query also gets its own integration test that dispatches it through the bus. A module's
 * tests/Integration/CommandBusTest.php:
 *
 *   final class CommandBusTest extends CommandBusTestCase
 *   {
 *       protected function moduleName(): string { return 'heavenfoo'; }
 *   }
 */
abstract class CommandBusTestCase extends TestCase
{
    /**
     * The technical name of the module ("heavenfoo").
     */
    abstract protected function moduleName(): string;

    /**
     * The front container has the bus, and the module reaches it (ModuleServices::getCommandBus()).
     */
    public function testTheBusIsInTheFrontContainerAndReachableFromTheModule(): void
    {
        Containers::front();
        $module = \Module::getInstanceByName($this->moduleName());
        self::assertInstanceOf(\Module::class, $module);
        self::assertSame(strtolower(ucfirst($this->moduleName())), strtolower(get_class($module)));
        self::assertTrue(method_exists($module, 'getCommandBus'), 'The module has no getCommandBus() (trait ModuleServices of runtime-cqrs).');

        self::assertInstanceOf(ModuleCommandBus::class, $module->getCommandBus());
    }

    /**
     * The back-office container has the bus under its id.
     */
    public function testTheBusIsInTheBackOfficeContainer(): void
    {
        self::assertInstanceOf(ModuleCommandBus::class, Containers::backOffice()->get(ModuleCommandBus::serviceId($this->moduleName())));
    }

    /**
     * A message without handler is refused with HandlerNotFoundException.
     */
    public function testAMessageWithoutHandlerIsRejected(): void
    {
        $bus = Containers::backOffice()->get(ModuleCommandBus::serviceId($this->moduleName()));
        self::assertInstanceOf(ModuleCommandBus::class, $bus);
        $this->expectException(HandlerNotFoundException::class);

        $bus->handle(new \stdClass());
    }
}
