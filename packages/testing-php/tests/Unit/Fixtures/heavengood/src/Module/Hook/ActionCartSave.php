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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit\Fixtures\Heavengood;

/**
 * The hook trait of the fixture module "heavengood" (HK-01): only read as text.
 */
trait ActionCartSave
{
    /**
     * Does nothing.
     *
     * @param array<string, mixed> $params
     */
    public function hookActionCartSave(array $params): void
    {
    }
}
