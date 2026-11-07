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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\TestCase;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Cases\BareCase;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Cases\DefinitionCase;
use PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Definitions\BareDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\AbstractModuleDefinition;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

require_once dirname(__DIR__) . '/Fixtures/heavenbare/tests/BareCase.php';

/**
 * ModuleDefinitionTestCase finds what breaks the rules, passes a module with nothing to check, and finds the module
 * folder from the test class.
 */
final class ModuleDefinitionTestCaseRulesTest extends TestCase
{
    /** The test methods of the base class. */
    private const RULES = [
        'testHooksAreUniqueNonEmptyNames',
        'testConfigurationKeysUseTheModulePrefix',
        'testEveryConfigurationConstantHasThePrefixAndIsInstalled',
        'testEveryGlobalConfigurationKeyIsInstalled',
        'testEveryUpMigrationHasADownMigration',
        'testMigrationFilesFollowTheNamingConvention',
        'testTheTablesUseTheModulePrefixAndAreCreatedByAMigration',
        'testEveryHookHasItsMethod',
        'testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName',
        'testEveryLegacyControllerOfTheRoutesIsATab',
        'testTheRouteOfEveryTabExists',
    ];

    /**
     * A module with only its main file passes every rule; its folder is found above the test class.
     */
    public function testAModuleWithNothingToCheckPassesEveryRule(): void
    {
        $case = new BareCase('testHooksAreUniqueNonEmptyNames');
        self::assertSame(str_replace('\\', '/', dirname(__DIR__)) . '/Fixtures/heavenbare', $case->foundModuleDir());
        foreach (self::RULES as $rule) {
            $case->$rule();
        }
        self::assertGreaterThanOrEqual(count(self::RULES), $case->numberOfAssertionsPerformed());
    }

    /**
     * Without <module>.php above the test class, moduleDir() asks to be overridden.
     */
    public function testWithoutTheMainFileTheFolderMustBeGiven(): void
    {
        $case = DefinitionCase::for('heavennowhere', new BareDefinition(), null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No heavennowhere.php above');

        $case->testMigrationFilesFollowTheNamingConvention();
    }

    /**
     * The prefix is the module name in capitals.
     */
    public function testThePrefixIsTheModuleNameInCapitals(): void
    {
        self::assertSame('HEAVENBARE_', DefinitionCase::for('heavenbare', new BareDefinition(), null)->prefix());
    }

    /**
     * Each broken definition or module fails its rule.
     */
    #[DataProvider('brokenModules')]
    public function testABrokenModuleFailsItsRule(string $rule, string $module, ModuleDefinitionInterface $definition, string $message): void
    {
        $case = DefinitionCase::for($module, $definition, dirname(__DIR__) . '/Fixtures/' . $module);

        try {
            $case->$rule();
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString($message, $e->getMessage());

            return;
        }
        self::fail("$rule passed for a broken module.");
    }

    /**
     * Broken modules: the rule, the module folder, the definition and a part of the failure message.
     *
     * @return iterable<string, array{string, string, ModuleDefinitionInterface, string}>
     */
    public static function brokenModules(): iterable
    {
        yield 'hook twice' => ['testHooksAreUniqueNonEmptyNames', 'heavenbare', new class extends AbstractModuleDefinition {
            public const HOOKS = ['displayHome', 'displayHome'];
        }, 'A hook is registered twice.'];
        yield 'hook name' => ['testHooksAreUniqueNonEmptyNames', 'heavenbare', new class extends AbstractModuleDefinition {
            public const HOOKS = ['display-home'];
        }, 'matches PCRE pattern'];
        yield 'key twice' => ['testConfigurationKeysUseTheModulePrefix', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['HEAVENBARE_A' => 1];
            public const GENERATED_CONFIGURATION = ['HEAVENBARE_A'];
        }, 'A configuration key is defined twice.'];
        yield 'key prefix' => ['testConfigurationKeysUseTheModulePrefix', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['OTHER_A' => 1];
        }, 'starts with "HEAVENBARE_"'];
        yield 'key capitals' => ['testConfigurationKeysUseTheModulePrefix', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['HEAVENBARE_a' => 1];
        }, 'matches PCRE pattern'];
        yield 'default and secret' => ['testEveryConfigurationConstantHasThePrefixAndIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['HEAVENBARE_A' => 1];
            public const GENERATED_CONFIGURATION = ['HEAVENBARE_A'];
        }, 'A key is both a default and a generated secret.'];
        yield 'constant prefix' => ['testEveryConfigurationConstantHasThePrefixAndIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIG_A = 'OTHER_A';
        }, 'CONFIG_A'];
        yield 'constant not installed' => ['testEveryConfigurationConstantHasThePrefixAndIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIG_A = 'HEAVENBARE_A';
        }, 'CONFIG_A is neither in CONFIGURATION nor in GENERATED_CONFIGURATION'];
        yield 'global twice' => ['testEveryGlobalConfigurationKeyIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const CONFIGURATION = ['HEAVENBARE_A' => 1];
            public const GLOBAL_CONFIGURATION = ['HEAVENBARE_A', 'HEAVENBARE_A'];
        }, 'A key is in GLOBAL_CONFIGURATION twice.'];
        yield 'global not installed' => ['testEveryGlobalConfigurationKeyIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const GLOBAL_CONFIGURATION = ['HEAVENBARE_A'];
        }, 'HEAVENBARE_A is global but install does not create it.'];
        yield 'global not a list' => ['testEveryGlobalConfigurationKeyIsInstalled', 'heavenbare', new class extends AbstractModuleDefinition {
            public const GLOBAL_CONFIGURATION = 'HEAVENBARE_A';
        }, 'is of type array'];
        yield 'no down migration' => ['testEveryUpMigrationHasADownMigration', 'heavenbroken', new BareDefinition(), 'migrations/up/202611070000 has no down migration.'];
        yield 'migration name' => ['testMigrationFilesFollowTheNamingConvention', 'heavenbroken', new BareDefinition(), 'item.sql'];
        yield 'table prefix' => ['testTheTablesUseTheModulePrefixAndAreCreatedByAMigration', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABLE_LOG = 'other_log';
        }, 'TABLE_LOG'];
        yield 'table not a name' => ['testTheTablesUseTheModulePrefixAndAreCreatedByAMigration', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABLE = ['heavenbroken_item'];
        }, 'TABLE'];
        yield 'table not created' => ['testTheTablesUseTheModulePrefixAndAreCreatedByAMigration', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABLE_LOG = 'heavenbroken_log';
        }, 'TABLE_LOG: no up migration creates heavenbroken_log.'];
        yield 'hook without method' => ['testEveryHookHasItsMethod', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const HOOKS = ['actionCartSave'];
        }, 'Hook actionCartSave has no method hookActionCartSave() (HK-01).'];
        yield 'display hook without widget' => ['testEveryHookHasItsMethod', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const HOOKS = ['displayHome'];
        }, 'Hook displayHome has no method hookDisplayHome()'];
        yield 'tab twice' => ['testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminHeavenbrokenA', 'wording' => 'A'], ['class_name' => 'AdminHeavenbrokenA', 'wording' => 'A']];
            public const TABS_WORDING_DOMAIN = 'Modules.Heavenbroken.Admin';
        }, 'Two tabs have the same class name.'];
        yield 'tab class' => ['testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminOther', 'wording' => 'A']];
        }, 'starts with "AdminHeavenbroken"'];
        yield 'tab route' => ['testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminHeavenbrokenA', 'route_name' => 'other_a', 'wording' => 'A']];
        }, 'starts with "heavenbroken_"'];
        yield 'tab wording' => ['testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminHeavenbrokenA']];
        }, 'Tab AdminHeavenbrokenA has no wording.'];
        yield 'tab domain' => ['testTheTabsHaveTheModulePrefixAWordingAndAUniqueClassName', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminHeavenbrokenA', 'wording' => 'A']];
        }, 'The tab names need the wording domain Modules.Heavenbroken.Admin'];
        yield 'legacy controller without tab' => ['testEveryLegacyControllerOfTheRoutesIsATab', 'heavenbroken', new BareDefinition(), '_legacy_controller AdminHeavenbrokenLost has no tab'];
        yield 'tab without route' => ['testTheRouteOfEveryTabExists', 'heavenbroken', new class extends AbstractModuleDefinition {
            public const TABS = [['class_name' => 'AdminHeavenbrokenA', 'route_name' => 'heavenbroken_a', 'wording' => 'A']];
        }, 'Route heavenbroken_a of tab AdminHeavenbrokenA is not in config/routes.yml.'];
    }
}
