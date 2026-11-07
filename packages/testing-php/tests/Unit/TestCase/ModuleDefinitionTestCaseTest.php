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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\TestCase;

use PrestaShopHeaven\Build\TestingPhp\TestCase\ModuleDefinitionTestCase;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Definitions\GoodDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * The base class as a module uses it, on the fixture module "heavengood" that follows every rule: all its tests pass
 * (PHPUnit runs the inherited test methods). The failures are in ModuleDefinitionTestCaseRulesTest.
 */
final class ModuleDefinitionTestCaseTest extends ModuleDefinitionTestCase
{
    /**
     * The fixture module.
     */
    protected function moduleName(): string
    {
        return 'heavengood';
    }

    /**
     * Its definition.
     */
    protected function definition(): ModuleDefinitionInterface
    {
        return new GoodDefinition();
    }

    /**
     * Its folder in the fixtures.
     */
    protected function moduleDir(): string
    {
        return dirname(__DIR__) . '/Fixtures/heavengood';
    }

    /**
     * runtime-kit serves this hook (the PrestaHaven bar).
     *
     * @return string[]
     */
    protected function hooksServedElsewhere(): array
    {
        return ['displayAdminAfterHeader'];
    }

    /**
     * The helpers read the fixture: keys, migrations by type, routes and the PHP code of main file and src/.
     */
    public function testTheHelpersReadTheModule(): void
    {
        self::assertSame('HEAVENGOOD_', $this->configurationPrefix());
        self::assertSame(['HEAVENGOOD_ENABLED', 'HEAVENGOOD_NAME', 'HEAVENGOOD_TOKEN'], $this->installedKeys());
        self::assertSame(['202611070000' => 'C', '202611070001' => 'I'], $this->timestamps('migrations/up/'));
        self::assertSame(['202611070000' => 'D'], $this->timestamps('migrations/down/'));
        self::assertCount(2, $this->sqlFiles('migrations/up/'));
        self::assertSame([], $this->sqlFiles('migrations/none/'));
        self::assertStringContainsString('heavengood_configuration:', $this->routes());
        $code = $this->moduleCode();
        self::assertStringContainsString('final class Heavengood', $code);
        self::assertStringContainsString('function hookActionCartSave(', $code);
        self::assertStringContainsString('function renderWidget(', $code);
        self::assertSame('heavengood_item', $this->constants()['TABLE']);
    }
}
