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
 * A front controller for hook tests: a real FrontController subclass (so instanceof checks pass) that
 * records registerStylesheet()/registerJavascript() instead of building the page. Its constructor skips
 * FrontController::__construct(), which sets up a whole page (cookies, cart, theme assets).
 */
final class RecordingFrontController extends \FrontController
{
    /** @var array<string, array{0: string, 1: array<string, mixed>}> registered stylesheets by id */
    public array $stylesheets = [];

    /** @var array<string, array{0: string, 1: array<string, mixed>}> registered scripts by id */
    public array $scripts = [];

    /**
     * A controller of the given page ('index' = the home page).
     */
    public function __construct(private readonly string $pageName)
    {
        $this->controller_type = 'front';
        $this->context = \Context::getContext();
    }

    /**
     * The page name the hook checks.
     */
    public function getPageName(): string
    {
        return $this->pageName;
    }

    /**
     * Records a stylesheet.
     *
     * @param string $id
     * @param string $relativePath
     * @param array<string, mixed> $params
     */
    public function registerStylesheet($id, $relativePath, $params = []): void
    {
        $this->stylesheets[(string) $id] = [(string) $relativePath, (array) $params];
    }

    /**
     * Records a script.
     *
     * @param string $id
     * @param string $relativePath
     * @param array<string, mixed> $params
     */
    public function registerJavascript($id, $relativePath, $params = []): void
    {
        $this->scripts[(string) $id] = [(string) $relativePath, (array) $params];
    }
}
