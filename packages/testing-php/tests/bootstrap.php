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
 * Bootstrap of the unit tests of this package (ps-module mono check loads it after the root autoloader): the package's
 * own unit bootstrap, so the runtime-kit classes of the fixtures pass their _PS_VERSION_ guard, and the autoloader of
 * the test classes (autoload-dev of a package is not in the root autoloader).
 */

PrestaShopHeaven\Build\TestingPhp\UnitBootstrap::boot();

spl_autoload_register(static function (string $class): void {
    $prefix = 'PrestaShopHeaven\\Build\\TestingPhp\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
