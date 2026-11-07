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

namespace PrestaShopHeaven\Build\TestingPhp\TestCase;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * The unit test every module has for its ModuleDefinition (conventions INS-01, INS-02, DB-01, HK-01, BO-11): hooks,
 * configuration keys and their constants, migrations, tables, tabs and routes follow the rules. No PrestaShop needed.
 * A module's tests/Unit/Install/ModuleDefinitionTest.php:
 *
 *   final class ModuleDefinitionTest extends ModuleDefinitionTestCase
 *   {
 *       protected function moduleName(): string { return 'heavenfoo'; }
 *       protected function definition(): ModuleDefinitionInterface { return new ModuleDefinition(); }
 *   }
 *
 * Module-specific checks stay in that class as extra test methods. Constants it looks at on the definition class, when
 * they exist: CONFIG_* (string keys), GLOBAL_CONFIGURATION, TABLE and TABLE_*, TABS_WORDING_DOMAIN.
 */
abstract class ModuleDefinitionTestCase extends TestCase
{
    /** Migration file names: "<12 digits> - <C|A|D|I|U> - <name>.sql". */
    public const MIGRATION_PATTERN = '/^\d{12} - [CADIU] - [\w-]+\.sql$/';

    /**
     * The technical name of the module ("heavenfoo").
     */
    abstract protected function moduleName(): string;

    /**
     * The definition of the module (new ModuleDefinition()).
     */
    abstract protected function definition(): ModuleDefinitionInterface;

    /**
     * The module folder: the nearest folder above the test class that holds <module>.php.
     *
     * @throws \LogicException when there is none: override moduleDir()
     */
    protected function moduleDir(): string
    {
        $file = (string) (new \ReflectionClass(static::class))->getFileName();
        for ($dir = dirname($file); $dir !== dirname($dir); $dir = dirname($dir)) {
            if (is_file($dir . '/' . $this->moduleName() . '.php')) {
                return str_replace('\\', '/', $dir);
            }
        }
        throw new \LogicException(sprintf('No %s.php above %s: override moduleDir().', $this->moduleName(), $file));
    }

    /**
     * The prefix of every configuration key: the module name in capitals plus "_" (INS-02).
     */
    protected function configurationPrefix(): string
    {
        return strtoupper($this->moduleName()) . '_';
    }

    /**
     * Hooks whose method is neither in the module nor served by its widget, because something else provides it (a
     * package trait); none by default.
     *
     * @return string[]
     */
    protected function hooksServedElsewhere(): array
    {
        return [];
    }

    /**
     * Every hook is registered once and has a valid name.
     */
    public function testHooksAreUniqueNonEmptyNames(): void
    {
        $this->addToAssertionCount(1);
        $hooks = $this->definition()->hooks();

        self::assertSame(array_values(array_unique($hooks)), array_values($hooks), 'A hook is registered twice.');
        foreach ($hooks as $hook) {
            self::assertMatchesRegularExpression('/^[a-zA-Z][a-zA-Z0-9]+$/', $hook);
        }
    }

    /**
     * Every configuration key (defaults and generated secrets) is unique, in capitals and has the module prefix.
     */
    public function testConfigurationKeysUseTheModulePrefix(): void
    {
        $this->addToAssertionCount(1);
        $keys = $this->installedKeys();
        self::assertSame(array_values(array_unique($keys)), $keys, 'A configuration key is defined twice.');
        foreach ($keys as $key) {
            self::assertStringStartsWith($this->configurationPrefix(), $key);
            self::assertMatchesRegularExpression('/^[A-Z0-9_]+$/', $key);
        }
    }

    /**
     * A key is a default or a generated secret, never both; every CONFIG_* constant that names a key has the prefix
     * and is installed (else install would not create it).
     */
    public function testEveryConfigurationConstantHasThePrefixAndIsInstalled(): void
    {
        $this->addToAssertionCount(1);
        $definition = $this->definition();
        self::assertSame([], array_values(array_intersect(array_keys($definition->configuration()), $definition->generatedConfiguration())), 'A key is both a default and a generated secret.');
        $installed = $this->installedKeys();
        foreach ($this->constants() as $name => $key) {
            // Only constants that name one key; CONFIG_* arrays group keys (a map of keys).
            if (!str_starts_with($name, 'CONFIG_') || !is_string($key)) {
                continue;
            }
            self::assertStringStartsWith($this->configurationPrefix(), $key, $name);
            self::assertContains($key, $installed, "$name is neither in CONFIGURATION nor in GENERATED_CONFIGURATION: install would not create it.");
        }
    }

    /**
     * GLOBAL_CONFIGURATION (keys that are never set per shop), when the definition has it: unique and installed.
     */
    public function testEveryGlobalConfigurationKeyIsInstalled(): void
    {
        $this->addToAssertionCount(1);
        $global = $this->constants()['GLOBAL_CONFIGURATION'] ?? [];
        self::assertIsArray($global);
        self::assertSame(array_values(array_unique($global)), array_values($global), 'A key is in GLOBAL_CONFIGURATION twice.');
        foreach ($global as $key) {
            self::assertContains($key, $this->installedKeys(), "$key is global but install does not create it.");
        }
    }

    /**
     * Every up migration that creates or alters a table (C, A) has a down migration with the same timestamp. Inserts
     * and updates of data (I, U) do not need to be reverted when the table is dropped.
     */
    public function testEveryUpMigrationHasADownMigration(): void
    {
        $this->addToAssertionCount(1);
        $down = $this->timestamps($this->definition()->migrationsDownDirectory());
        foreach ($this->timestamps($this->definition()->migrationsUpDirectory()) as $timestamp => $type) {
            if (in_array($type, ['C', 'A'], true)) {
                self::assertArrayHasKey($timestamp, $down, "migrations/up/$timestamp has no down migration.");
            }
        }
    }

    /**
     * Every migration file is named "<timestamp> - <type> - <name>.sql" (DB-01).
     */
    public function testMigrationFilesFollowTheNamingConvention(): void
    {
        $this->addToAssertionCount(1);
        foreach ([$this->definition()->migrationsUpDirectory(), $this->definition()->migrationsDownDirectory()] as $dir) {
            foreach ($this->sqlFiles($dir) as $file) {
                self::assertMatchesRegularExpression(self::MIGRATION_PATTERN, basename($file));
            }
        }
    }

    /**
     * Every TABLE / TABLE_* constant has the module prefix and is created by an up migration.
     */
    public function testTheTablesUseTheModulePrefixAndAreCreatedByAMigration(): void
    {
        $this->addToAssertionCount(1);
        $sql = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $this->sqlFiles($this->definition()->migrationsUpDirectory())));
        foreach ($this->constants() as $name => $table) {
            if (preg_match('/^TABLE(_|$)/', $name) !== 1) {
                continue;
            }
            self::assertIsString($table, $name);
            self::assertStringStartsWith($this->moduleName() . '_', $table, $name);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `{_DB_PREFIX_}' . $table . '`', $sql, "$name: no up migration creates $table.");
        }
    }

    /**
     * Every hook has its method hook<Name>() in the module (main file or src/, HK-01), or is a display hook the
     * module's widget serves (renderWidget()).
     */
    public function testEveryHookHasItsMethod(): void
    {
        $this->addToAssertionCount(1);
        $code = $this->moduleCode();
        $widget = preg_match('/function\s+renderWidget\s*\(/i', $code) === 1;
        foreach (array_diff($this->definition()->hooks(), $this->hooksServedElsewhere()) as $hook) {
            if ($widget && str_starts_with($hook, 'display')) {
                continue;
            }
            self::assertMatchesRegularExpression('/function\s+hook' . preg_quote(ucfirst($hook), '/') . '\s*\(/i', $code, "Hook $hook has no method hook" . ucfirst($hook) . '() (HK-01).');
        }
    }

    /**
     * The tabs have unique class names Admin<Module>..., routes <module>_..., a wording, and the domain
     * Modules.<Module>.Admin for their names.
     */
    public function testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName(): void
    {
        $this->addToAssertionCount(1);
        $tabs = $this->definition()->tabs();
        $classNames = array_column($tabs, 'class_name');
        self::assertSame(array_values(array_unique($classNames)), $classNames, 'Two tabs have the same class name.');
        foreach ($tabs as $tab) {
            self::assertStringStartsWith('Admin' . ucfirst($this->moduleName()), (string) ($tab['class_name'] ?? ''));
            if (isset($tab['route_name'])) {
                self::assertStringStartsWith($this->moduleName() . '_', (string) $tab['route_name']);
            }
            self::assertNotSame('', (string) ($tab['wording'] ?? ''), 'Tab ' . ($tab['class_name'] ?? '?') . ' has no wording.');
        }
        if ($tabs !== []) {
            $domain = 'Modules.' . ucfirst($this->moduleName()) . '.Admin';
            self::assertSame($domain, $this->definition()->tabsWordingDomain(), "The tab names need the wording domain $domain (TABS_WORDING_DOMAIN).");
        }
    }

    /**
     * Every legacy controller of the module's own routes (Admin<Module>...) is a tab (BO-11); routes on core pages
     * (AdminOrders, AdminProducts) are left alone.
     */
    public function testEveryLegacyControllerOfTheRoutesIsATab(): void
    {
        $this->addToAssertionCount(1);
        preg_match_all("/_legacy_controller:\\s*'?([A-Za-z0-9_]+)'?/", $this->routes(), $controllers);
        $tabs = array_column($this->definition()->tabs(), 'class_name');
        foreach (array_unique($controllers[1]) as $controller) {
            if (str_starts_with($controller, 'Admin' . ucfirst($this->moduleName()))) {
                self::assertContains($controller, $tabs, "_legacy_controller $controller has no tab in the module definition (BO-11).");
            }
        }
    }

    /**
     * The route of every tab is in config/routes.yml.
     */
    public function testTheRouteOfEveryTabExists(): void
    {
        $this->addToAssertionCount(1);
        preg_match_all('/^([a-z0-9_]+):\s*$/m', $this->routes(), $names);
        foreach ($this->definition()->tabs() as $tab) {
            if (isset($tab['route_name'])) {
                self::assertContains($tab['route_name'], $names[1], 'Route ' . $tab['route_name'] . ' of tab ' . ($tab['class_name'] ?? '?') . ' is not in config/routes.yml.');
            }
        }
    }

    /**
     * The keys install creates: the defaults and the generated secrets.
     *
     * @return list<string>
     */
    protected function installedKeys(): array
    {
        return array_values(array_merge(array_keys($this->definition()->configuration()), $this->definition()->generatedConfiguration()));
    }

    /**
     * The constants of the definition class.
     *
     * @return array<string, mixed>
     */
    protected function constants(): array
    {
        return (new \ReflectionClass($this->definition()))->getConstants();
    }

    /**
     * The SQL files of a migration folder (relative to the module), sorted.
     *
     * @return list<string>
     */
    protected function sqlFiles(string $dir): array
    {
        $files = glob($this->moduleDir() . '/' . $dir . '*.sql') ?: [];
        sort($files);

        return $files;
    }

    /**
     * The migrations of a folder: timestamp => type letter.
     *
     * @return array<string, string>
     */
    protected function timestamps(string $dir): array
    {
        $result = [];
        foreach ($this->sqlFiles($dir) as $file) {
            if (preg_match('/^(\d{12}) - ([CADIU]) - /', basename($file), $m) === 1) {
                $result[$m[1]] = $m[2];
            }
        }

        return $result;
    }

    /**
     * config/routes.yml of the module ('' without one).
     */
    protected function routes(): string
    {
        $file = $this->moduleDir() . '/config/routes.yml';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /**
     * The PHP code of the module: the main file and every file in src/.
     */
    protected function moduleCode(): string
    {
        $dir = $this->moduleDir();
        $files = is_file("$dir/{$this->moduleName()}.php") ? ["$dir/{$this->moduleName()}.php"] : [];
        if (is_dir("$dir/src")) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$dir/src", \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
    }
}
