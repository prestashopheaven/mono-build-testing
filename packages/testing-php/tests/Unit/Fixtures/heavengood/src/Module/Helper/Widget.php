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
 * The widget of the fixture module "heavengood": it serves the display hooks. Only read as text.
 */
trait Widget
{
    /**
     * Renders nothing.
     *
     * @param array<string, mixed> $configuration
     */
    public function renderWidget(?string $hookName, array $configuration): string
    {
        return '';
    }
}
