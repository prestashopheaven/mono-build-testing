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
 * composer phpstan [<ps-version> ...]: PHPStan of the packages, their integration tests and the fixture module against
 * PrestaShop 8.1.0 and 9.2.0 (config.json, phpstan.neon next to this file), through ps-module's phpstan runner.
 * ps-module mono check runs PHPStan only for runtime monorepos; build-testing-php is PrestaShop code, so it runs here
 * until mono check gives build packages that use PrestaShop the PrestaShop extension too.
 */

putenv('PS_PHPSTAN_DIR=' . str_replace('\\', '/', __DIR__));
chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . '/vendor/prestashopheaven/build-tools/src/phpstan.php';
