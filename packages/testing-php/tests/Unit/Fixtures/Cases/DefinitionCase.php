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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Cases;

use PrestaShopHeaven\Build\TestingPhp\TestCase\ModuleDefinitionTestCase;
use PrestaShopHeaven\Runtime\Kit\Definition\ModuleDefinitionInterface;

/**
 * A module test with its module, definition and folder given from outside, so one test can run the base class on
 * many fixtures (broken ones too). Not a test file itself (no Test suffix).
 */
final class DefinitionCase extends ModuleDefinitionTestCase
{
    private string $module = '';

    private ?ModuleDefinitionInterface $moduleDefinition = null;

    private ?string $dir = null;

    /** @var string[] */
    private array $elsewhere = [];

    /**
     * A case for $module with $definition in $dir (null: the folder the base class finds).
     *
     * @param string[] $elsewhere hooks served elsewhere
     */
    public static function for(string $module, ModuleDefinitionInterface $definition, ?string $dir, array $elsewhere = []): self
    {
        $case = new self('testHooksAreUniqueNonEmptyNames');
        $case->module = $module;
        $case->moduleDefinition = $definition;
        $case->dir = $dir;
        $case->elsewhere = $elsewhere;

        return $case;
    }

    /**
     * The module given.
     */
    protected function moduleName(): string
    {
        return $this->module;
    }

    /**
     * The definition given.
     *
     * @throws \LogicException when none was given
     */
    protected function definition(): ModuleDefinitionInterface
    {
        return $this->moduleDefinition ?? throw new \LogicException('No definition.');
    }

    /**
     * The folder given, else the one the base class finds.
     */
    protected function moduleDir(): string
    {
        return $this->dir ?? parent::moduleDir();
    }

    /**
     * The hooks given.
     *
     * @return string[]
     */
    protected function hooksServedElsewhere(): array
    {
        return $this->elsewhere;
    }

    /**
     * The prefix the base class derives (public for the test).
     */
    public function prefix(): string
    {
        return $this->configurationPrefix();
    }
}
