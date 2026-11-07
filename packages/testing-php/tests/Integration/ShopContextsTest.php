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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\ShopContexts;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * The shop and language contexts without a request: legacy and back office (PS 9 builders), restoring them, removing
 * values per shop and group, and finding shops in the multistore round.
 */
final class ShopContextsTest extends TestCase
{
    /** @var array{0: int|null, 1: int|null} */
    private array $before;

    protected function setUp(): void
    {
        $this->before = ShopContexts::shopContext();
    }

    protected function tearDown(): void
    {
        ShopContexts::restoreShopContext($this->before);
        ShopContexts::removeShopAndGroupValues([$this->key()]);
    }

    public function testTheBackOfficeShopContextSetsTheLegacyContextToo(): void
    {
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $group = (int) \Shop::getGroupFromShop($default);

        ShopContexts::backOfficeShopContext($default);
        self::assertSame([\Shop::CONTEXT_SHOP, $default], ShopContexts::shopContext());

        ShopContexts::backOfficeShopContext(null, $group);
        self::assertSame(\Shop::CONTEXT_GROUP, \Shop::getContext());
        self::assertSame($group, (int) \Shop::getContextShopGroupID());

        ShopContexts::backOfficeShopContext(null);
        self::assertSame(\Shop::CONTEXT_ALL, \Shop::getContext());
    }

    public function testTheShopContextIsRestored(): void
    {
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        ShopContexts::backOfficeShopContext(null);

        ShopContexts::restoreShopContext([\Shop::CONTEXT_SHOP, $default]);
        self::assertSame([\Shop::CONTEXT_SHOP, $default], ShopContexts::shopContext());

        ShopContexts::restoreShopContext([null, null]);
        self::assertSame([\Shop::CONTEXT_SHOP, $default], ShopContexts::shopContext(), 'No context: the default shop.');
    }

    public function testTheBackOfficeLanguageContextCanBeBuiltWithoutARequest(): void
    {
        // PS 9: finds the LanguageContextBuilder behind the request subscriber (throws when it cannot); PS 8: nothing to do.
        ShopContexts::backOfficeLanguageContext();
        ShopContexts::backOfficeLanguageContext();

        $this->addToAssertionCount(1);
    }

    public function testShopAndGroupValuesAreRemoved(): void
    {
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $group = (int) \Shop::getGroupFromShop($default);
        \Configuration::updateValue($this->key(), 'shop value', false, $group, $default);
        \Configuration::updateValue($this->key(), 'group value', false, $group, null);

        ShopContexts::removeShopAndGroupValues([$this->key()]);

        self::assertSame(0, (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'' . pSQL($this->key()) . '\' AND (`id_shop` IS NOT NULL OR `id_shop_group` IS NOT NULL)'));
        self::assertSame(\Configuration::getGlobalValue($this->key()), \Configuration::get($this->key(), null, $group, $default), 'The shop inherits the global value again.');
    }

    public function testTheShopsOfTheMultistoreRound(): void
    {
        $two = ShopContexts::shopsInTwoGroups();
        $sibling = ShopContexts::siblingShop();
        if (!\Shop::isFeatureActive()) {
            self::assertNull($two);
            self::assertNull($sibling);

            return;
        }
        // ps-module shops run, multistore round: shops 1 and 2 in group 1, shop 3 in group 2.
        self::assertIsArray($two);
        self::assertNotSame($two['group'], $two['otherGroup']);
        self::assertSame($two['group'], (int) \Shop::getGroupFromShop($two['shop']));
        self::assertSame($two['otherGroup'], (int) \Shop::getGroupFromShop($two['otherShop']));
        self::assertIsInt($sibling);
        self::assertSame((int) \Shop::getGroupFromShop((int) \Configuration::get('PS_SHOP_DEFAULT')), (int) \Shop::getGroupFromShop($sibling));
    }

    /**
     * The text setting of the fixture module.
     */
    private function key(): string
    {
        return TestedModule::configurationPrefix() . 'GREETING';
    }
}
