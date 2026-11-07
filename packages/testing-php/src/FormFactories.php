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

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Test helper: the form factory of the back-office container, with every form extension of PrestaShop (validation,
 * multistore, help texts) like the module pages and the product page use. The service form.factory itself is private
 * on PS 9 (removed from the compiled container), so it is taken from the public form handler of the module's settings
 * page, which receives it (handlerId()). Not a test itself.
 */
final class FormFactories
{
    /** @var Request|null the request with a session that backOffice() put on the request stack */
    private static ?Request $request = null;

    /** @var array{0: int|null, 1: int|null}|null the legacy shop context before backOffice() */
    private static ?array $shopContext = null;

    /**
     * The public service of a module that receives @form.factory (config/admin/services.yml):
     * prestashopheaven.<module>.form.configuration_handler.
     *
     * @param string|null $module the technical name; default the module under test (TestedModule)
     */
    public static function handlerId(?string $module = null): string
    {
        return 'prestashopheaven.' . TestedModule::name($module) . '.form.configuration_handler';
    }

    /**
     * The form factory of the back-office container, with the shop and language context of the default shop (PS 9
     * builds both only during a request; the multistore extension needs the shop context) and a request with a
     * session (the alerts extension of PrestaShop reads the flash bag when a view is built). Forms of a test are root
     * forms: create them with ['csrf_protection' => false] (in the product page the tab is a child form).
     * Call reset() in tearDown().
     *
     * @param string|null $handler the service id of a form handler; default handlerId()
     *
     * @throws \RuntimeException when the handler holds no form factory
     */
    public static function backOffice(?string $handler = null): FormFactoryInterface
    {
        self::$shopContext ??= ShopContexts::shopContext();
        ShopContexts::backOfficeShopContext((int) \Configuration::get('PS_SHOP_DEFAULT'));
        ShopContexts::backOfficeLanguageContext();
        $stack = Containers::backOffice()->get('request_stack');
        if ($stack instanceof RequestStack && self::$request === null) {
            self::$request = new Request();
            self::$request->setSession(new Session(new MockArraySessionStorage()));
            $stack->push(self::$request);
        }

        return self::fromHandler($handler);
    }

    /**
     * The form factory the settings handler holds, without touching the contexts.
     *
     * @param string|null $handler the service id of a form handler; default handlerId()
     *
     * @throws \RuntimeException when the handler holds no form factory
     */
    public static function fromHandler(?string $handler = null): FormFactoryInterface
    {
        $handler ??= self::handlerId();
        $service = Containers::backOffice()->get($handler);
        if (!$service instanceof FormHandlerInterface) {
            throw new \RuntimeException($handler . ' is not a form handler.');
        }
        $class = new \ReflectionClass($service);
        while ($class !== false) {
            if ($class->hasProperty('formFactory')) {
                $factory = $class->getProperty('formFactory')->getValue($service);
                if ($factory instanceof FormFactoryInterface) {
                    return $factory;
                }
            }
            $class = $class->getParentClass();
        }

        throw new \RuntimeException('No form factory in ' . $handler . '.');
    }

    /**
     * Takes the request of backOffice() off the request stack again and restores the legacy shop context.
     */
    public static function reset(): void
    {
        $stack = Containers::backOffice()->get('request_stack');
        if (self::$request !== null && $stack instanceof RequestStack && $stack->getCurrentRequest() === self::$request) {
            $stack->pop();
        }
        self::$request = null;
        if (self::$shopContext !== null) {
            ShopContexts::restoreShopContext(self::$shopContext);
            self::$shopContext = null;
        }
    }
}
