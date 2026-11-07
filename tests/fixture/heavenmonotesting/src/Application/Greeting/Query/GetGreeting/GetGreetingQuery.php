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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Asks for the greeting of a shop; proves the bus of the fixture reaches a tagged handler (CommandBusTestCase).
 */
final class GetGreetingQuery
{
    /**
     * Holds the shop the question is about.
     */
    public function __construct(public readonly int $shopId)
    {
    }
}
