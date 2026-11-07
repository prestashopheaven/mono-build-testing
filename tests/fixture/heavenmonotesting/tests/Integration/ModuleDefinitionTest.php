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

namespace PrestaShopHeaven\MonoTesting\Fixture\Tests\Integration;

use PrestaShopHeaven\Build\TestingPhp\TestCase\ModuleDefinitionTestCase;
use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * The definition test as a module writes it (in a module: tests/Unit/Install/ModuleDefinitionTest.php; the fixture
 * only has integration tests). The module folder is found from this file.
 */
final class ModuleDefinitionTest extends ModuleDefinitionTestCase
{
    protected function moduleName(): string
    {
        return 'heavenmonotesting';
    }

    protected function definition(): ModuleDefinitionInterface
    {
        return new ModuleDefinition();
    }
}
