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

namespace PrestaShopHeaven\MonoTesting\Fixture\Module\Hook;

use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Records every status change with the employee of the context, so OrderStatusTest sees that the change ran like the
 * order page (hook called, employee present).
 */
trait ActionOrderStatusPostUpdate
{
    /**
     * Writes the order, the new status and the context employee into heavenmonotesting_status_change.
     *
     * @param array<string, mixed> $params ['newOrderStatus' => OrderState, 'id_order' => int]
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $state = $params['newOrderStatus'] ?? null;
        $employee = $this->context->employee;
        \Db::getInstance()->insert(ModuleDefinition::TABLE, [
            'id_order' => (int) ($params['id_order'] ?? 0),
            'id_order_state' => $state instanceof \OrderState ? (int) $state->id : 0,
            'id_employee' => $employee instanceof \Employee ? (int) $employee->id : 0,
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }
}
