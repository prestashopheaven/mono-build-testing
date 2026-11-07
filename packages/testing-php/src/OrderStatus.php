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

/**
 * Changes the status of an order in a test like the order page of the back office does: an employee in the context
 * (PrestaShop 8.1 writes the stock movement of the change with the context employee and fails without one), then
 * OrderHistory::changeIdOrderState() (the hooks actionOrderStatusUpdate and actionOrderStatusPostUpdate, invoices,
 * delivery slips, stock) and the history row. Lesson of heavenautoorderstatus (2026-11-06).
 */
final class OrderStatus
{
    /**
     * Changes the status. The employee: $employeeId, else the one in the context, else the first active SuperAdmin
     * (else the employee with the lowest id); the context employee is restored afterwards.
     *
     * @param bool $useExistingPayment true like the order page for an order without invoice (no new payment row)
     * @param bool $addHistory false to only run changeIdOrderState() without writing the ps_order_history row (some
     *                         tests check what the hook alone does)
     *
     * @return \OrderHistory the new history entry (saved when $addHistory)
     *
     * @throws \RuntimeException when there is no employee, or PrestaShop does not save the history row
     */
    public static function change(int $orderId, int $stateId, bool $useExistingPayment = true, bool $addHistory = true, ?int $employeeId = null): \OrderHistory
    {
        return self::asEmployee(static function (\Employee $employee) use ($orderId, $stateId, $useExistingPayment, $addHistory): \OrderHistory {
            self::clearCache();
            $history = new \OrderHistory();
            $history->id_order = $orderId;
            $history->id_employee = (int) $employee->id;
            $history->changeIdOrderState($stateId, $orderId, $useExistingPayment);
            if ($addHistory && !$history->add()) {
                throw new \RuntimeException(sprintf('PrestaShop did not save the status %d of order %d.', $stateId, $orderId));
            }
            self::clearCache();

            return $history;
        }, $employeeId);
    }

    /**
     * Runs $action with an employee in the context (the order page, a back-office controller) and restores the
     * context employee afterwards, also when $action throws.
     *
     * @template T
     *
     * @param callable(\Employee): T $action
     *
     * @return T
     *
     * @throws \RuntimeException when there is no employee
     */
    public static function asEmployee(callable $action, ?int $employeeId = null): mixed
    {
        $context = \Context::getContext();
        $previous = $context->employee;
        $employee = self::employee($employeeId);
        $context->employee = $employee;
        try {
            return $action($employee);
        } finally {
            $context->employee = $previous;
        }
    }

    /**
     * The employee of a change: $employeeId, else the loaded context employee, else the first active SuperAdmin, else
     * the employee with the lowest id.
     *
     * @throws \RuntimeException when there is none
     */
    public static function employee(?int $employeeId = null): \Employee
    {
        if ($employeeId === null) {
            $current = \Context::getContext()->employee;
            if ($current instanceof \Employee && \Validate::isLoadedObject($current)) {
                return $current;
            }
            $db = \Db::getInstance();
            $employeeId = (int) $db->getValue('SELECT `id_employee` FROM `' . _DB_PREFIX_ . 'employee` WHERE `active` = 1 AND `id_profile` = ' . (int) _PS_ADMIN_PROFILE_ . ' ORDER BY `id_employee` ASC')
                ?: (int) $db->getValue('SELECT MIN(`id_employee`) FROM `' . _DB_PREFIX_ . 'employee`');
        }
        $employee = new \Employee($employeeId);
        if (!\Validate::isLoadedObject($employee)) {
            throw new \RuntimeException(sprintf('No employee %d for the status change.', $employeeId));
        }

        return $employee;
    }

    /**
     * The current status of an order, read from the database (not from a cached Order object).
     */
    public static function current(int $orderId): int
    {
        return (int) \Db::getInstance()->getValue('SELECT `current_state` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . $orderId);
    }

    /**
     * Empties the caches of orders and their history, so the next read sees the change.
     */
    public static function clearCache(): void
    {
        \Cache::clean('objectmodel_Order_*');
        \Cache::clean('objectmodel_OrderHistory_*');
        \Order::cleanHistoryCache();
    }
}
