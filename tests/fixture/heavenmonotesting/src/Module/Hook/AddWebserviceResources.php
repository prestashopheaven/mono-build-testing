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

namespace PrestaShopHeaven\MonoTesting\Fixture\Module\Hook;

use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adds the webservice resource of the fixture (only listed: InstallationTestCase checks that the webservice knows it).
 */
trait AddWebserviceResources
{
    /**
     * Returns the resources of this module, merged by WebserviceRequest::getResources().
     *
     * @param array<string, mixed> $params ['resources' => current resource list]
     *
     * @return array<string, array<string, mixed>>
     */
    public function hookAddWebserviceResources(array $params): array
    {
        return ModuleDefinition::WEBSERVICE_RESOURCES;
    }
}
