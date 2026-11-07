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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Cases;

use PrestaShopHeaven\Build\TestingPhp\TestCase\ModuleDefinitionTestCase;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Definitions\BareDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * The module test of "heavenbare" as a module writes it: two methods, the module folder found from this file. Not a
 * test file itself (no Test suffix): ModuleDefinitionTestCaseTest runs its methods.
 */
final class BareCase extends ModuleDefinitionTestCase
{
    /**
     * The fixture module.
     */
    protected function moduleName(): string
    {
        return 'heavenbare';
    }

    /**
     * Its empty definition.
     */
    protected function definition(): ModuleDefinitionInterface
    {
        return new BareDefinition();
    }

    /**
     * The folder the base class finds (public for the test).
     */
    public function foundModuleDir(): string
    {
        return $this->moduleDir();
    }
}
