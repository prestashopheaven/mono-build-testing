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
 * Loads the stylesheet of the fixture on the home page only (HK-05), for RecordingFrontControllerTest.
 */
trait ActionFrontControllerSetMedia
{
    /**
     * Registers views/css/front.css on the home page.
     *
     * @param array<string, mixed> $params
     */
    public function hookActionFrontControllerSetMedia(array $params): void
    {
        $controller = $this->context->controller;
        if (!$controller instanceof \FrontController || $controller->getPageName() !== 'index') {
            return;
        }
        $controller->registerStylesheet(ModuleDefinition::STYLESHEET_ID, 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 150]);
    }
}
