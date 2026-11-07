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
use PrestaShopHeaven\Build\TestingPhp\RecordingFrontController;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * A front controller that records the assets a hook registers: the fixture module loads its stylesheet on the home
 * page only (hook actionFrontControllerSetMedia, run through PrestaShop's Hook::exec()).
 */
final class RecordingFrontControllerTest extends TestCase
{
    private mixed $controller = null;

    protected function setUp(): void
    {
        $this->controller = \Context::getContext()->controller;
    }

    protected function tearDown(): void
    {
        \Context::getContext()->controller = $this->controller;
    }

    public function testItIsARealFrontControllerOfThePage(): void
    {
        $controller = new RecordingFrontController('product');

        self::assertInstanceOf(\FrontController::class, $controller);
        self::assertSame('product', $controller->getPageName());
        self::assertSame([], $controller->stylesheets);
        self::assertSame([], $controller->scripts);
    }

    public function testItRecordsWhatIsRegistered(): void
    {
        $controller = new RecordingFrontController('index');
        $controller->registerStylesheet('a', 'modules/x/a.css', ['media' => 'all']);
        $controller->registerJavascript('b', 'modules/x/b.js', ['position' => 'bottom']);
        $controller->registerJavascript('c', 'modules/x/c.js');

        self::assertSame(['a' => ['modules/x/a.css', ['media' => 'all']]], $controller->stylesheets);
        self::assertSame(['b' => ['modules/x/b.js', ['position' => 'bottom']], 'c' => ['modules/x/c.js', []]], $controller->scripts);
    }

    public function testTheHookOfTheModuleRegistersItsStylesheetOnTheHomePageOnly(): void
    {
        $home = $this->runHook('index');
        $product = $this->runHook('product');

        $id = 'module-' . TestedModule::name() . '-front';
        self::assertArrayHasKey($id, $home->stylesheets);
        self::assertSame('modules/' . TestedModule::name() . '/views/css/front.css', $home->stylesheets[$id][0]);
        self::assertArrayNotHasKey($id, $product->stylesheets);
    }

    /**
     * Runs actionFrontControllerSetMedia for the module with a recording controller of the page.
     */
    private function runHook(string $page): RecordingFrontController
    {
        $controller = new RecordingFrontController($page);
        \Context::getContext()->controller = $controller;
        $module = \Module::getInstanceByName(TestedModule::name());
        self::assertInstanceOf(\Module::class, $module);
        \Hook::exec('actionFrontControllerSetMedia', [], (int) $module->id);

        return $controller;
    }
}
