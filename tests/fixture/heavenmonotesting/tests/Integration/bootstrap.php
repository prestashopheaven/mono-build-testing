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
 * Bootstrap of the integration tests in a real shop (ps-module shops integration <version>), exactly as a module writes
 * it with build-testing-php: PrestaShop, the module's autoloader, the Symfony kernel and the module under test.
 */

require_once dirname(__DIR__, 2) . '/vendor/prestashopheaven/build-testing-php/src/IntegrationBootstrap.php';
PrestaShopHeaven\Build\TestingPhp\IntegrationBootstrap::boot('heavenmonotesting', dirname(__DIR__, 2));
