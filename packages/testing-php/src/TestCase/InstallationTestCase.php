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
 * The integration test every module has (in a real shop; the runner installs the module before the suite): the
 * installed module matches its ModuleDefinition and its migrations, and the install is multistore-proof (MS-02..04).
 * A module's tests/Integration/InstallationTest.php:
 *
 *   final class InstallationTest extends InstallationTestCase
 *   {
 *       protected function moduleName(): string { return 'heavenfoo'; }
 *       protected function definition(): ModuleDefinitionInterface { return new ModuleDefinition(); }
 *   }
 *
 * Module-specific checks stay in that class as extra test methods.
 */
abstract class InstallationTestCase extends TestCase
{
    /** A generated secret (GENERATED_CONFIGURATION): at least 16 letters and digits (runtime-kit writes 32 hex). */
    public const SECRET_PATTERN = '/^[A-Za-z0-9]{16,}$/';

    protected \Module $module;

    /**
     * The technical name of the module ("heavenfoo").
     */
    abstract protected function moduleName(): string;

    /**
     * The definition of the module (new ModuleDefinition()).
     */
    abstract protected function definition(): ModuleDefinitionInterface;

    /**
     * The webservice resources the module adds (name => definition): by default the constant WEBSERVICE_RESOURCES of
     * the definition class, when it has one.
     *
     * @return array<string, mixed>
     */
    protected function webserviceResources(): array
    {
        $constant = get_class($this->definition()) . '::WEBSERVICE_RESOURCES';
        $resources = defined($constant) ? constant($constant) : [];

        return is_array($resources) ? $resources : [];
    }

    /**
     * Loads the configuration from the database again (Configuration::hasKey() only looks in the static cache, which
     * an earlier test may have emptied with resetStaticCache()) and the installed module.
     */
    protected function setUp(): void
    {
        \Configuration::resetStaticCache();
        \Configuration::loadConfiguration();
        $module = \Module::getInstanceByName($this->moduleName());
        self::assertInstanceOf(\Module::class, $module, 'Module ' . $this->moduleName() . ' cannot be loaded.');
        $this->module = $module;
    }

    /**
     * The module is installed and enabled.
     */
    public function testModuleIsInstalledAndActive(): void
    {
        self::assertTrue(\Module::isInstalled($this->moduleName()));
        self::assertTrue((bool) \Module::isEnabled($this->moduleName()));
    }

    /**
     * Every hook of the definition exists and has the module.
     */
    public function testAllHooksAreRegistered(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->definition()->hooks() as $hook) {
            self::assertGreaterThan(0, (int) \Hook::getIdByName($hook), "Hook $hook does not exist.");
            self::assertTrue($this->module->isRegisteredInHook($hook), "Module is not registered on hook $hook.");
        }
    }

    /**
     * Every configuration key (defaults and generated secrets) has its global row. Not Configuration::hasKey():
     * PrestaShop stores an empty default ('') as NULL, and hasKey() (isset) then says the key is missing.
     */
    public function testAllConfigurationKeysExist(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->installedKeys() as $key) {
            self::assertTrue(self::hasGlobalRow($key), "Configuration $key is missing.");
        }
    }

    /**
     * GENERATED_CONFIGURATION: the install wrote a random secret (a cron token, a signing key) as a global value.
     */
    public function testGeneratedSecretsHaveAValue(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->definition()->generatedConfiguration() as $key) {
            self::assertMatchesRegularExpression(self::SECRET_PATTERN, (string) \Configuration::getGlobalValue($key), "Configuration $key has no generated secret.");
        }
    }

    /**
     * Every table the up migrations create exists.
     */
    public function testTablesFromMigrationsExist(): void
    {
        $this->addToAssertionCount(1);
        $dir = $this->module->getLocalPath() . $this->definition()->migrationsUpDirectory();
        foreach (glob($dir . '*.sql') ?: [] as $file) {
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?\{_DB_PREFIX_\}(\w+)`?/i', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $table) {
                $exists = \Db::getInstance()->executeS('SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . $table) . '\'');
                self::assertNotEmpty($exists, 'Table ' . _DB_PREFIX_ . $table . ' was not created.');
            }
        }
    }

    /**
     * Every tab of the definition exists and belongs to the module (the hidden tab of the settings page too).
     */
    public function testTheTabsExistAndBelongToTheModule(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->definition()->tabs() as $tab) {
            $id = (int) \Tab::getIdFromClassName((string) $tab['class_name']);
            self::assertGreaterThan(0, $id, 'Tab ' . $tab['class_name'] . ' is missing.');
            self::assertSame($this->moduleName(), (new \Tab($id))->module);
        }
    }

    /**
     * The webservice knows every resource of the module, as a resource with its own management class.
     */
    public function testTheWebserviceResourcesAreKnown(): void
    {
        $this->addToAssertionCount(1);
        $own = $this->webserviceResources();
        if ($own === []) {
            return;
        }
        $resources = \WebserviceRequest::getResources();
        foreach (array_keys($own) as $resource) {
            self::assertArrayHasKey($resource, $resources);
            self::assertTrue((bool) ($resources[$resource]['specific_management'] ?? false), "Resource $resource has no specific management.");
        }
    }

    /**
     * Multistore (MS-02): an install from bin/console runs in the default shop, yet the module
     * must be enabled in every shop. Runs in the multistore round of "ps-module shops run".
     */
    public function testModuleIsEnabledInEveryShop(): void
    {
        if (!\Shop::isFeatureActive()) {
            self::markTestSkipped('Multistore is not active in this shop.');
        }
        $enabledIn = array_map('intval', array_column(\Db::getInstance()->executeS(
            'SELECT id_shop FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $this->module->id . ' ORDER BY id_shop'
        ) ?: [], 'id_shop'));
        $shops = array_map('intval', array_values(\Shop::getShops(true, null, true)));
        sort($shops);
        self::assertSame($shops, $enabledIn, 'The module is not enabled in every shop after install.');
    }

    /**
     * Multistore (MS-03): the defaults of ModuleDefinition are global values, not values of the
     * shop the install ran in; every shop inherits them.
     */
    public function testConfigurationDefaultsAreGlobal(): void
    {
        if (!\Shop::isFeatureActive()) {
            self::markTestSkipped('Multistore is not active in this shop.');
        }
        $this->addToAssertionCount(1);
        foreach ($this->installedKeys() as $key) {
            self::assertTrue(self::hasGlobalRow($key), "Configuration $key has no global value.");
            self::assertSame(0, (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'configuration`'
                . ' WHERE name = \'' . pSQL($key) . '\' AND (id_shop IS NOT NULL OR id_shop_group IS NOT NULL)'),
                "Configuration $key has values per shop or group right after install.");
        }
    }

    /**
     * Multistore (MS-04): a value set for one shop only applies to that shop.
     */
    public function testShopValueStaysInItsShop(): void
    {
        $keys = array_keys($this->definition()->configuration());
        $shops = array_map('intval', array_values(\Shop::getShops(true, null, true)));
        if (!\Shop::isFeatureActive() || $keys === [] || count($shops) < 2) {
            self::markTestSkipped('Needs multistore with two shops and at least one configuration key.');
        }
        $key = $keys[0];
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $other = (int) current(array_diff($shops, [$default]));
        $group = (int) \Shop::getGroupFromShop($other);
        $before = \Configuration::get($key, null, null, $default);
        try {
            // Pass the group as well: with null, updateValue() takes it from the context.
            \Configuration::updateValue($key, 'only-in-shop-' . $other, false, $group, $other);
            self::assertSame('only-in-shop-' . $other, \Configuration::get($key, null, $group, $other));
            self::assertSame($before, \Configuration::get($key, null, null, $default));
        } finally {
            \Configuration::deleteFromGivenContext($key, null, $other);
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
     * True when the key has exactly one global row in ps_configuration.
     */
    protected static function hasGlobalRow(string $key): bool
    {
        return (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'configuration`'
            . " WHERE name = '" . pSQL($key) . "' AND id_shop IS NULL AND id_shop_group IS NULL") === 1;
    }
}
