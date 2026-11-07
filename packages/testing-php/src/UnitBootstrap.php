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
 * The bootstrap of a module's unit tests: no PrestaShop, no database. The classes in src/ start with the
 * _PS_VERSION_ guard, so it defines _PS_VERSION_ (and _DB_PREFIX_ for SQL builders), and it loads stand-ins for the
 * interfaces PrestaShop ships but a module's dev dependencies do not (only when the real one is missing). Code that
 * needs PrestaShop classes belongs in tests/Integration.
 *
 * The module's tests/Unit/bootstrap.php:
 *
 *   require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
 *   PrestaShopHeaven\Build\TestingPhp\UnitBootstrap::boot();
 */
final class UnitBootstrap
{
    /** The _PS_VERSION_ of unit tests: no real version, so version checks see that no shop runs. */
    public const PS_VERSION = '0.0.0-unit';

    /** The table prefix of unit tests (SQL builders write _DB_PREFIX_). */
    public const DB_PREFIX = 'ps_';

    /**
     * Defines the constants and loads the stand-ins; safe to call twice.
     *
     * @param array<string, string>|null $stubs interface => stub file (default: stubs())
     */
    public static function boot(?array $stubs = null): void
    {
        self::constant('_PS_VERSION_', self::PS_VERSION);
        self::constant('_DB_PREFIX_', self::DB_PREFIX);
        self::loadStubs($stubs ?? self::stubs());
    }

    /**
     * Defines a constant unless it exists. Returns true when it was defined now.
     */
    public static function constant(string $name, string $value): bool
    {
        if (defined($name)) {
            return false;
        }

        return define($name, $value);
    }

    /**
     * The stand-ins of this package: interface => stub file. Symfony's TranslatorInterface: PrestaShop provides it, the
     * dev dependencies of a module do not (a Presenter or a form type that takes the translator).
     *
     * @return array<string, string>
     */
    public static function stubs(): array
    {
        return [
            'Symfony\Contracts\Translation\TranslatorInterface' => dirname(__DIR__) . '/resources/stubs/translator-interface.stub',
        ];
    }

    /**
     * Loads each stub whose interface (or class) does not exist yet. Returns the interfaces it loaded.
     *
     * @param array<string, string> $stubs interface => stub file
     *
     * @return list<string>
     *
     * @throws \RuntimeException when a needed stub file is missing
     */
    public static function loadStubs(array $stubs): array
    {
        $loaded = [];
        foreach ($stubs as $name => $file) {
            if (interface_exists($name) || class_exists($name)) {
                continue;
            }
            if (!is_file($file)) {
                throw new \RuntimeException(sprintf('The stub of %s is missing: %s.', $name, $file));
            }
            require_once $file;
            $loaded[] = $name;
        }

        return $loaded;
    }
}
