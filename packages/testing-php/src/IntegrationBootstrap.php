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

namespace PrestaShopHeaven\Build\TestingPhp;

/**
 * The bootstrap of a module's integration tests: boots the real PrestaShop the tests run in (inside its container,
 * vendor/bin/ps-module shops integration <ps-version>), then the module's autoloader, then the Symfony kernel like
 * bin/console does (AdminKernel on PS 9, AppKernel on PS 8). Without the kernel there is no container in a CLI run,
 * and on PS 9 core code that takes services from it fails with "Kernel Container is not available"
 * (Cart::getOrderTotal(), Tools::getContextLocale(), ...).
 *
 * PrestaShop comes first and its autoloader is prepended, so the core's classes (and the files its Composer autoloader
 * includes) win over anything in the module's vendor/. That is why this file is loaded by path and uses nothing else
 * of the package before the module's autoloader runs. The module's tests/Integration/bootstrap.php:
 *
 *   require_once dirname(__DIR__, 2) . '/vendor/prestashopheaven/build-testing-php/src/IntegrationBootstrap.php';
 *   PrestaShopHeaven\Build\TestingPhp\IntegrationBootstrap::boot('heavenfoo', dirname(__DIR__, 2));
 */
final class IntegrationBootstrap
{
    /** Where the shop lives in the test containers when _PS_ROOT_DIR_ is not set. */
    public const DEFAULT_ROOT = '/var/www/html';

    /**
     * Boots PrestaShop, the module's autoloader and the kernel, and sets the module under test (TestedModule). Exits
     * with code 1 and a hint when there is no PrestaShop. Safe to call twice.
     *
     * @param string $module the technical name of the module
     * @param string $moduleDir the module folder (with vendor/)
     */
    public static function boot(string $module, string $moduleDir): void
    {
        $psRoot = self::psRoot();
        if (!self::isShop($psRoot)) {
            fwrite(STDERR, self::missingShopMessage($psRoot));
            exit(1);
        }
        if (!defined('_PS_ADMIN_DIR_')) {
            define('_PS_ADMIN_DIR_', $psRoot . '/admin-dev');
        }
        require_once $psRoot . '/config/config.inc.php';
        require_once $moduleDir . '/vendor/autoload.php';

        TestedModule::set($module, $moduleDir);
        if (!isset($GLOBALS['kernel'])) {
            $GLOBALS['kernel'] = Containers::bootKernel(false);
        }
    }

    /**
     * The PrestaShop root: the environment variable _PS_ROOT_DIR_, else /var/www/html.
     */
    public static function psRoot(): string
    {
        return rtrim((string) (getenv('_PS_ROOT_DIR_') ?: self::DEFAULT_ROOT), '/\\');
    }

    /**
     * True when $psRoot holds a PrestaShop installation.
     */
    public static function isShop(string $psRoot): bool
    {
        return is_file($psRoot . '/config/config.inc.php');
    }

    /**
     * The message when there is no shop: where it looked and how to run the tests.
     */
    public static function missingShopMessage(string $psRoot): string
    {
        return "Integration tests need a PrestaShop installation (looked in $psRoot).\n"
            . "Run them with: vendor/bin/ps-module shops integration <ps-version>\n";
    }
}
