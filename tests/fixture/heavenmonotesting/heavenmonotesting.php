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
 * Fixture module of mono-build-testing: a real module with one of everything the helpers of build-testing-php work
 * with (a multistore settings page, a front stylesheet, a status hook that records the employee, a webservice
 * resource, the command bus), so their integration tests run in real shops (ps-module shops run, CI of the monorepo).
 * Never released.
 */

use PrestaShopHeaven\MonoTesting\Fixture\Module\Helper\Definition;
use PrestaShopHeaven\MonoTesting\Fixture\Module\Hook\ActionFrontControllerSetMedia;
use PrestaShopHeaven\MonoTesting\Fixture\Module\Hook\ActionOrderStatusPostUpdate;
use PrestaShopHeaven\MonoTesting\Fixture\Module\Hook\AddWebserviceResources;
use PrestaShopHeaven\Runtime\Cqrs\ModuleServices;
use PrestaShopHeaven\Runtime\Kit\ConfigurationPage;
use PrestaShopHeaven\Runtime\Kit\ModuleBase;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * The fixture module: lifecycle and Configure button from runtime-kit, the bus from runtime-cqrs, its hooks as traits.
 */
class Heavenmonotesting extends Module
{
    use ModuleBase;
    use Definition;
    use ModuleServices;
    use ConfigurationPage;
    use ActionFrontControllerSetMedia;
    use ActionOrderStatusPostUpdate;
    use AddWebserviceResources;

    /**
     * Sets the identity of the fixture module.
     */
    public function __construct()
    {
        $this->name = 'heavenmonotesting';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'prestahaven.com';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.99.99'];
        $this->bootstrap = true;
        $this->multistoreCompatibility = self::MULTISTORE_COMPATIBILITY_YES;

        parent::__construct();

        $this->displayName = $this->trans('Testing packages fixture', [], 'Modules.Heavenmonotesting.Admin');
        $this->description = $this->trans('Test module of the PrestaHaven test helpers.', [], 'Modules.Heavenmonotesting.Admin');
    }
}
