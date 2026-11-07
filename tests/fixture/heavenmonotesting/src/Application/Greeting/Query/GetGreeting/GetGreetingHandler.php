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

namespace PrestaShopHeaven\MonoTesting\Fixture\Application\Greeting\Query\GetGreeting;

use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Answers GetGreetingQuery with the greeting of the shop (its own value, else its group's, else the global one).
 */
final class GetGreetingHandler
{
    /**
     * Returns the greeting of the shop of the query.
     */
    public function handle(GetGreetingQuery $query): string
    {
        return (string) \Configuration::get(ModuleDefinition::CONFIG_GREETING, null, (int) \Shop::getGroupFromShop($query->shopId), $query->shopId);
    }
}
