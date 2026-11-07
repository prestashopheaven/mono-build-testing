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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Integration\TestCase;

use PrestaShopHeaven\Build\TestingPhp\Containers;
use PrestaShopHeaven\Build\TestingPhp\TestCase\CommandBusTestCase;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;
use PrestaShopHeaven\MonoTesting\Fixture\Application\Greeting\Query\GetGreeting\GetGreetingQuery;
use PrestaShopHeaven\Runtime\Cqrs\ModuleCommandBus;

/**
 * The bus test of the base class on the fixture module: every inherited test passes, and the bus reaches the
 * fixture's handler in both containers.
 */
final class CommandBusTestCaseTest extends CommandBusTestCase
{
    protected function moduleName(): string
    {
        return TestedModule::name();
    }

    public function testTheBusReachesTheHandlerOfTheFixture(): void
    {
        $shop = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $expected = (string) \Configuration::get(TestedModule::configurationPrefix() . 'GREETING', null, (int) \Shop::getGroupFromShop($shop), $shop);
        $front = Containers::front()->get(ModuleCommandBus::serviceId(TestedModule::name()));
        $backOffice = Containers::backOffice()->get(ModuleCommandBus::serviceId(TestedModule::name()));
        self::assertInstanceOf(ModuleCommandBus::class, $front);
        self::assertInstanceOf(ModuleCommandBus::class, $backOffice);

        self::assertSame($expected, $front->handle(new GetGreetingQuery($shop)));
        self::assertSame($expected, $backOffice->handle(new GetGreetingQuery($shop)));
    }
}
