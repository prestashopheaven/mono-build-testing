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

use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint as CoreShopConstraint;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Shop contexts for integration tests: the back-office shop context of PS 9 without a request, the legacy
 * context, and finding shops in a multistore test shop (the multistore round of ps-module shops).
 */
final class ShopContexts
{
    /**
     * Sets the language context of the back-office container like a request does (PS 9.2): TranslatableType
     * reads the LanguageContext, which LanguageContextSubscriber (a kernel.request listener) only fills during
     * a request; without it building the form fails with "Cannot build Language context as no defaultLanguageId
     * has been defined". PS 8 has no LanguageContextBuilder.
     *
     * @throws \RuntimeException when the builder cannot be found on PS 9
     */
    public static function backOfficeLanguageContext(): void
    {
        if (!class_exists('PrestaShop\PrestaShop\Core\Context\LanguageContextBuilder')) {
            return;
        }
        $dispatcher = Containers::backOffice()->get('event_dispatcher');
        if (!$dispatcher instanceof EventDispatcherInterface) {
            throw new \RuntimeException('No event dispatcher in the back-office container.');
        }
        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');
        foreach ($dispatcher->getListeners('kernel.request') as $listener) {
            if (is_array($listener) && is_object($listener[0]) && property_exists($listener[0], 'languageContextBuilder')) {
                $builder = (new \ReflectionProperty($listener[0], 'languageContextBuilder'))->getValue($listener[0]);
                if (is_object($builder) && method_exists($builder, 'setDefaultLanguageId') && method_exists($builder, 'setLanguageId')) {
                    $builder->setDefaultLanguageId($languageId);
                    $builder->setLanguageId($languageId);

                    return;
                }
            }
        }
        throw new \RuntimeException('LanguageContextBuilder not found through LanguageContextSubscriber.');
    }

    /**
     * Removes every own value per shop and per group of the given configuration keys, so the next test starts
     * from the global value. Not Configuration::deleteFromContext(): in a shop context it ignores the group id.
     *
     * @param string[] $keys
     */
    public static function removeShopAndGroupValues(array $keys): void
    {
        foreach ($keys as $key) {
            \Db::getInstance()->delete('configuration', '`name` = \'' . pSQL($key) . '\' AND (`id_shop` IS NOT NULL OR `id_shop_group` IS NOT NULL)');
        }
        \Configuration::resetStaticCache();
    }

    /**
     * Sets the shop context of the back-office container like a request does, and the legacy one (Shop::setContext()).
     * PS 9 builds its ShopContext (used by the multistore form extension) from ShopContextBuilder, which only the
     * request subscriber fills: without a request every form fails with "Cannot build shop context as no
     * shopConstraint has been defined". The builder is private; ShopContextSubscriber (a kernel.request listener
     * of the public event dispatcher; its AuthenticationSuccessEvent listener sits on the firewall dispatcher) holds it. The ShopContext is a lazy proxy built once, so it is reset to be built again.
     * PS 8 has no ShopContextBuilder: its forms read the legacy context only.
     *
     * @param int|null $shopId a shop, or null
     * @param int|null $groupId a shop group (when $shopId is null), or null for all shops
     */
    public static function backOfficeShopContext(?int $shopId, ?int $groupId = null): void
    {
        if ($shopId !== null) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, $shopId);
            $constraint = CoreShopConstraint::shop($shopId);
        } elseif ($groupId !== null) {
            \Shop::setContext(\Shop::CONTEXT_GROUP, $groupId);
            $constraint = CoreShopConstraint::shopGroup($groupId);
        } else {
            \Shop::setContext(\Shop::CONTEXT_ALL);
            $constraint = CoreShopConstraint::allShops();
        }
        if (!class_exists('PrestaShop\PrestaShop\Core\Context\ShopContextBuilder')) {
            return;
        }
        $container = Containers::backOffice();
        $dispatcher = $container->get('event_dispatcher');
        if (!$dispatcher instanceof EventDispatcherInterface) {
            throw new \RuntimeException('No event dispatcher in the back-office container.');
        }
        $builder = null;
        foreach ($dispatcher->getListeners('kernel.request') as $listener) {
            if (is_array($listener) && is_object($listener[0]) && property_exists($listener[0], 'shopContextBuilder')) {
                $builder = (new \ReflectionProperty($listener[0], 'shopContextBuilder'))->getValue($listener[0]);
            }
        }
        if (!is_object($builder) || !method_exists($builder, 'setShopId') || !method_exists($builder, 'setShopConstraint')) {
            throw new \RuntimeException('ShopContextBuilder not found through ShopContextSubscriber.');
        }
        $groupShops = $groupId !== null ? array_map('intval', array_values(\Shop::getShops(true, $groupId, true))) : [];
        $builder->setShopId($shopId ?? ($groupShops[0] ?? (int) \Configuration::get('PS_SHOP_DEFAULT')));
        $builder->setShopConstraint($constraint);

        // The ShopContext of the multistore checkboxes: a lazy proxy, built on first use; build it again.
        // The container of PS 8 types get() as ?object, the one of PS 9 as object: (object) suits both.
        $enabler = (object) $container->get('prestashop.multistore_checkbox_enabler');
        if (property_exists($enabler, 'shopContext')) {
            $shopContext = (new \ReflectionProperty($enabler, 'shopContext'))->getValue($enabler);
            if (is_object($shopContext) && method_exists($shopContext, 'resetLazyObject')) {
                $shopContext->resetLazyObject();
            }
        }
    }

    /**
     * The shop context of PrestaShop (Shop::getContext() and its id), to restore after a test.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function shopContext(): array
    {
        $type = \Shop::getContext();

        return [$type, $type === \Shop::CONTEXT_GROUP ? \Shop::getContextShopGroupID() : \Shop::getContextShopID()];
    }

    /**
     * Restores a shop context from shopContext().
     *
     * @param array{0: int|null, 1: int|null} $context
     */
    public static function restoreShopContext(array $context): void
    {
        if ($context[0] === null) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) \Configuration::get('PS_SHOP_DEFAULT'));

            return;
        }
        \Shop::setContext($context[0], $context[1]);
    }

    /**
     * Two active shops in different shop groups (multistore round: shop 1 in group 1, shop 3 in group 2),
     * or null when the shop has no such shops.
     *
     * @return array{shop: int, group: int, otherShop: int, otherGroup: int}|null
     */
    public static function shopsInTwoGroups(): ?array
    {
        if (!\Shop::isFeatureActive()) {
            return null;
        }
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $group = (int) \Shop::getGroupFromShop($default);
        foreach (array_map('intval', array_values(\Shop::getShops(true, null, true))) as $shop) {
            $otherGroup = (int) \Shop::getGroupFromShop($shop);
            if ($otherGroup !== $group) {
                return ['shop' => $default, 'group' => $group, 'otherShop' => $shop, 'otherGroup' => $otherGroup];
            }
        }

        return null;
    }

    /**
     * Another active shop in the group of the default shop (multistore round: shop 2), or null.
     */
    public static function siblingShop(): ?int
    {
        if (!\Shop::isFeatureActive()) {
            return null;
        }
        $default = (int) \Configuration::get('PS_SHOP_DEFAULT');
        $group = (int) \Shop::getGroupFromShop($default);
        foreach (array_map('intval', array_values(\Shop::getShops(true, $group, true))) as $shop) {
            if ($shop !== $default) {
                return $shop;
            }
        }

        return null;
    }
}
