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

/*
 * Main file of the fixture module "heavengood" (ModuleDefinitionTestCaseTest): only read as text, never loaded.
 */

use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Heavengood\ActionCartSave;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Heavengood\Widget;

/**
 * A module with a hook trait and a widget.
 */
final class Heavengood
{
    use ActionCartSave;
    use Widget;
}
