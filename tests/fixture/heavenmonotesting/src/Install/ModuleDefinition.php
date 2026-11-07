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

namespace PrestaShopHeaven\MonoTesting\Fixture\Install;

use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Everything the fixture registers: one of each kind, so the base test cases of build-testing-php have something to
 * check (hooks, defaults, a secret, a table, a hidden settings tab, a webservice resource).
 */
final class ModuleDefinition extends AbstractModuleDefinition
{
    /** The greeting of the settings page: a text field per shop (multistore). */
    public const CONFIG_GREETING = 'HEAVENMONOTESTING_GREETING';

    /** A number field of the settings page. */
    public const CONFIG_LIMIT = 'HEAVENMONOTESTING_LIMIT';

    /** A generated secret. */
    public const CONFIG_TOKEN = 'HEAVENMONOTESTING_TOKEN';

    public const HOOKS = ['actionFrontControllerSetMedia', 'actionOrderStatusPostUpdate', 'addWebserviceResources'];

    public const CONFIGURATION = [
        self::CONFIG_GREETING => 'Hello',
        self::CONFIG_LIMIT => 25,
    ];

    public const GENERATED_CONFIGURATION = [self::CONFIG_TOKEN];

    public const TABS = [
        [
            'class_name' => 'AdminHeavenmonotestingConfiguration',
            'route_name' => 'heavenmonotesting_configuration',
            'parent_class_name' => 'AdminParentModulesSf',
            'wording' => 'Testing packages fixture',
            'visible' => false,
        ],
    ];

    public const TABS_WORDING_DOMAIN = 'Modules.Heavenmonotesting.Admin';

    /** Every status change the hook saw, with the context employee (OrderStatus). */
    public const TABLE = 'heavenmonotesting_status_change';

    /** The stylesheet the front hook registers on the home page (RecordingFrontController). */
    public const STYLESHEET_ID = 'module-heavenmonotesting-front';

    /** A resource of the webservice (InstallationTestCase). */
    public const WEBSERVICE_RESOURCES = [
        'heavenmonotesting_greetings' => [
            'description' => 'Testing packages fixture: greetings',
            'specific_management' => true,
            'forbidden_method' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        ],
    ];
}
