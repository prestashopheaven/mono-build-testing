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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Builds the two service containers of a real shop for integration tests. The tests run without a
 * request, so Module::get() has no container until front() puts the front one on the context.
 */
final class Containers
{
    private static ?KernelInterface $kernel = null;

    /**
     * The light front-office container, built like FrontController::buildContainer().
     * Setting it on the context makes Module::get() (and so the command bus) work.
     */
    public static function front(): ContainerInterface
    {
        $context = \Context::getContext();
        if (!isset($context->container)) {
            $context->container = \PrestaShop\PrestaShop\Adapter\ContainerBuilder::getContainer('front', _PS_MODE_DEV_);
        }

        return $context->container;
    }

    /**
     * The Symfony container of the back office and bin/console.
     */
    public static function backOffice(): ContainerInterface
    {
        if (self::$kernel === null) {
            // The kernel IntegrationBootstrap::boot() (tests/Integration/bootstrap.php) booted, else a new one.
            $booted = $GLOBALS['kernel'] ?? null;
            self::$kernel = $booted instanceof KernelInterface ? $booted : self::bootKernel(true);
        }

        return self::$kernel->getContainer();
    }

    /**
     * Boots the back-office kernel: AdminKernel on PS 9 (a kernel per application), AppKernel on PS 8.
     * Through reflection, so PHPStan accepts it against both versions (each lacks or abstracts the other class).
     *
     * @throws \RuntimeException when neither kernel class can be created
     */
    public static function bootKernel(bool $debug): KernelInterface
    {
        foreach (['AdminKernel', 'AppKernel'] as $class) {
            if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $kernel = (new \ReflectionClass($class))->newInstance('dev', $debug);
            $kernel->boot();

            return $kernel;
        }
        throw new \RuntimeException('No AdminKernel or AppKernel to boot.');
    }
}
