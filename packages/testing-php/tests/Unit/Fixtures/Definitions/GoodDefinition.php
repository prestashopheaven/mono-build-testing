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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Definitions;

use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;

/**
 * The definition of the fixture module "heavengood": every rule of ModuleDefinitionTestCase has something to check.
 */
final class GoodDefinition extends AbstractModuleDefinition
{
    public const HOOKS = ['actionCartSave', 'displayHome', 'displayAdminAfterHeader'];

    public const CONFIG_ENABLED = 'HEAVENGOOD_ENABLED';

    public const CONFIG_NAME = 'HEAVENGOOD_NAME';

    public const CONFIG_TOKEN = 'HEAVENGOOD_TOKEN';

    /** A group of keys: an array, not a key itself. */
    public const CONFIG_GROUPS = ['main' => [self::CONFIG_ENABLED, self::CONFIG_NAME]];

    public const CONFIGURATION = [
        self::CONFIG_ENABLED => 1,
        self::CONFIG_NAME => '',
    ];

    public const GENERATED_CONFIGURATION = [self::CONFIG_TOKEN];

    public const GLOBAL_CONFIGURATION = [self::CONFIG_TOKEN];

    public const TABS = [
        [
            'class_name' => 'AdminHeavengoodConfiguration',
            'route_name' => 'heavengood_configuration',
            'parent_class_name' => 'AdminParentModulesSf',
            'wording' => 'Good fixture',
            'visible' => false,
        ],
        [
            'class_name' => 'AdminHeavengoodLegacy',
            'parent_class_name' => 'AdminParentModulesSf',
            'wording' => 'Legacy page',
        ],
    ];

    public const TABS_WORDING_DOMAIN = 'Modules.Heavengood.Admin';

    public const TABLE = 'heavengood_item';
}
