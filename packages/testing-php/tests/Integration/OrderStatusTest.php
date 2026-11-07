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
use PrestaShopHeaven\Build\TestingPhp\OrderStatus;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * A status change like the order page on a demo order: it works on every version (PS 8.1 needs the employee for the
 * stock movement), the module's actionOrderStatusPostUpdate sees the employee in the context, and the context
 * employee is back afterwards.
 */
final class OrderStatusTest extends TestCase
{
    private int $order = 0;

    private int $state = 0;

    private mixed $employee = null;

    protected function setUp(): void
    {
        $this->order = (int) \Db::getInstance()->getValue('SELECT MIN(`id_order`) FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_shop` = ' . (int) \Configuration::get('PS_SHOP_DEFAULT'));
        if ($this->order === 0) {
            self::markTestSkipped('The shop has no demo order.');
        }
        $this->state = OrderStatus::current($this->order);
        $this->employee = \Context::getContext()->employee;
        \Context::getContext()->employee = null;
    }

    protected function tearDown(): void
    {
        if ($this->order > 0 && OrderStatus::current($this->order) !== $this->state) {
            OrderStatus::change($this->order, $this->state);
        }
        \Context::getContext()->employee = $this->employee;
        \Db::getInstance()->delete($this->table(), '`id_order` = ' . $this->order);
    }

    public function testTheChangeRunsLikeTheOrderPageWithAnEmployee(): void
    {
        $new = $this->otherState();
        $historyBefore = $this->historyRows();

        $history = OrderStatus::change($this->order, $new);

        self::assertSame($new, OrderStatus::current($this->order));
        self::assertSame($historyBefore + 1, $this->historyRows());
        self::assertGreaterThan(0, (int) $history->id);
        self::assertGreaterThan(0, (int) $history->id_employee);
        $seen = \Db::getInstance()->getRow('SELECT `id_order_state`, `id_employee` FROM `' . _DB_PREFIX_ . $this->table() . '` WHERE `id_order` = ' . $this->order . ' ORDER BY `id_status_change` DESC');
        self::assertIsArray($seen, 'The hook of the module ran.');
        self::assertSame($new, (int) $seen['id_order_state']);
        self::assertSame((int) $history->id_employee, (int) $seen['id_employee'], 'The hook saw the employee in the context.');
        self::assertNull(\Context::getContext()->employee, 'The context employee is back.');
    }

    public function testWithoutHistoryOnlyTheHookRuns(): void
    {
        $historyBefore = $this->historyRows();

        OrderStatus::change($this->order, $this->otherState(), true, false);

        self::assertSame($historyBefore, $this->historyRows());
        self::assertSame(1, (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . $this->table() . '` WHERE `id_order` = ' . $this->order));
    }

    public function testTheEmployeeOfTheContextOrTheGivenOneIsUsed(): void
    {
        $first = OrderStatus::employee();
        self::assertTrue(\Validate::isLoadedObject($first));

        \Context::getContext()->employee = $first;
        self::assertSame($first, OrderStatus::employee(), 'The loaded context employee.');
        self::assertSame((int) $first->id, (int) OrderStatus::employee((int) $first->id)->id);

        $seen = OrderStatus::asEmployee(static fn (\Employee $employee): int => (int) \Context::getContext()->employee->id, (int) $first->id);
        self::assertSame((int) $first->id, $seen);
    }

    public function testTheContextEmployeeIsBackAfterAnError(): void
    {
        $this->expectException(\DomainException::class);
        try {
            OrderStatus::asEmployee(static function (\Employee $employee): int {
                throw new \DomainException('boom ' . (int) $employee->id);
            });
        } finally {
            self::assertNull(\Context::getContext()->employee);
        }
    }

    public function testAnUnknownEmployeeIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No employee 999999999 for the status change.');

        OrderStatus::employee(999999999);
    }

    /**
     * A status other than the current one: "Processing in progress", or "Payment accepted" when the order has that.
     */
    private function otherState(): int
    {
        $preparation = (int) \Configuration::get('PS_OS_PREPARATION');

        return $this->state !== $preparation ? $preparation : (int) \Configuration::get('PS_OS_PAYMENT');
    }

    /**
     * The history rows of the order.
     */
    private function historyRows(): int
    {
        return (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_history` WHERE `id_order` = ' . $this->order);
    }

    /**
     * The table of the fixture's status hook.
     */
    private function table(): string
    {
        return TestedModule::name() . '_status_change';
    }
}
