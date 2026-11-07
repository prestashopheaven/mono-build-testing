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
use PrestaShopHeaven\Build\TestingPhp\Containers;
use PrestaShopHeaven\Build\TestingPhp\FormFactories;
use PrestaShopHeaven\Build\TestingPhp\ShopContexts;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;
use PrestaShopHeaven\Runtime\Cqrs\ModuleCommandBus;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The form factory of the back office from the fixture's form handler: the settings form of the fixture builds and
 * renders without a request, and reset() puts the request stack and the shop context back.
 */
final class FormFactoriesTest extends TestCase
{
    protected function tearDown(): void
    {
        FormFactories::reset();
    }

    public function testTheHandlerIdFollowsTheModule(): void
    {
        self::assertSame('prestashopheaven.' . TestedModule::name() . '.form.configuration_handler', FormFactories::handlerId());
        self::assertSame('prestashopheaven.heavenfoo.form.configuration_handler', FormFactories::handlerId('heavenfoo'));
    }

    public function testTheSettingsFormOfTheModuleBuildsAndRenders(): void
    {
        $before = ShopContexts::shopContext();
        $factory = FormFactories::backOffice();
        $type = 'PrestaShopHeaven\MonoTesting\Fixture\Form\ConfigurationType';

        $form = $factory->create($type, ['greeting' => 'Hi', 'limit' => 3], ['csrf_protection' => false]);
        $view = $form->createView();

        self::assertTrue($form->has('greeting'));
        self::assertTrue($form->has('limit'));
        self::assertSame('Hi', $view->children['greeting']->vars['value']);
        self::assertSame($factory, FormFactories::fromHandler(), 'The same factory without touching the contexts.');
        self::assertSame($factory, FormFactories::backOffice(FormFactories::handlerId()), 'A second call keeps the request.');

        $stack = Containers::backOffice()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $request = $stack->getCurrentRequest();
        self::assertNotNull($request);
        self::assertTrue($request->hasSession());

        FormFactories::reset();
        self::assertNotSame($request, $stack->getCurrentRequest(), 'reset() takes the request off the stack.');
        self::assertSame($before, ShopContexts::shopContext(), 'reset() restores the shop context.');
        FormFactories::reset();
    }

    public function testAServiceThatIsNoFormHandlerIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not a form handler');

        FormFactories::fromHandler(ModuleCommandBus::serviceId(TestedModule::name()));
    }
}
