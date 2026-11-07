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
 * The module whose tests are running: its technical name and folder. IntegrationBootstrap::boot() (or a test) sets
 * it once, so the helpers that need the module (Http::moduleConfigurationPage(), FormFactories, the mail folder of
 * FakeSmtpServer) do not take its name in every call.
 */
final class TestedModule
{
    /** @var string the technical name, '' when not set */
    private static string $name = '';

    /** @var string the module folder with "/" separators, '' when not set */
    private static string $dir = '';

    /**
     * Sets the module under test.
     *
     * @throws \InvalidArgumentException when the name is not a technical module name (lowercase letters and digits)
     */
    public static function set(string $name, string $dir = ''): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a technical module name.', $name));
        }
        self::$name = $name;
        self::$dir = rtrim(str_replace('\\', '/', $dir), '/');
    }

    /**
     * Forgets the module under test (for the tests of this package).
     */
    public static function reset(): void
    {
        self::$name = '';
        self::$dir = '';
    }

    /**
     * True when a module is set.
     */
    public static function isSet(): bool
    {
        return self::$name !== '';
    }

    /**
     * The technical name of the module under test, or $name when it is given.
     *
     * @throws \LogicException when neither is there
     */
    public static function name(?string $name = null): string
    {
        if ($name !== null && $name !== '') {
            return $name;
        }
        if (self::$name === '') {
            throw new \LogicException('No module under test: call IntegrationBootstrap::boot() (or TestedModule::set()) in the bootstrap of the tests.');
        }

        return self::$name;
    }

    /**
     * The folder of the module under test ('' when unknown).
     */
    public static function dir(): string
    {
        return self::$dir;
    }

    /**
     * The prefix of the module's configuration keys and constants: the name in capitals plus "_" (INS-02).
     */
    public static function configurationPrefix(?string $name = null): string
    {
        return strtoupper(self::name($name)) . '_';
    }

    /**
     * The class name of the module's main class (PrestaShop: the technical name with a capital).
     */
    public static function mainClass(?string $name = null): string
    {
        return ucfirst(self::name($name));
    }
}
