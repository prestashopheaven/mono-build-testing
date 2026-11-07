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

use PrestaShopHeaven\Build\TestingPhp\TestCase\InstallationTestCase;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * The installation test of the base class on the installed fixture module (hooks, defaults, its secret, its table,
 * its hidden tab, its webservice resource, multistore): every inherited test passes.
 */
final class InstallationTestCaseTest extends InstallationTestCase
{
    protected function moduleName(): string
    {
        return TestedModule::name();
    }

    protected function definition(): ModuleDefinitionInterface
    {
        $module = \Module::getInstanceByName(TestedModule::name());
        self::assertIsObject($module);
        self::assertTrue(method_exists($module, 'getModuleDefinition'));

        return $module->getModuleDefinition();
    }

    public function testTheFixtureHasSomethingForEveryCheck(): void
    {
        $definition = $this->definition();

        self::assertNotSame([], $definition->hooks());
        self::assertNotSame([], $definition->configuration());
        self::assertNotSame([], $definition->generatedConfiguration());
        self::assertNotSame([], $definition->tabs());
        self::assertArrayHasKey(TestedModule::name() . '_greetings', $this->webserviceResources());
        self::assertTrue(self::hasGlobalRow(TestedModule::configurationPrefix() . 'TOKEN'));
        self::assertFalse(self::hasGlobalRow(TestedModule::configurationPrefix() . 'NOT_A_KEY'));
        self::assertSame([TestedModule::configurationPrefix() . 'GREETING', TestedModule::configurationPrefix() . 'LIMIT', TestedModule::configurationPrefix() . 'TOKEN'], $this->installedKeys());
    }
}
