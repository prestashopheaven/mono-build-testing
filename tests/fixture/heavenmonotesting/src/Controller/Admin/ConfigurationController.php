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

namespace PrestaShopHeaven\MonoTesting\Fixture\Controller\Admin;

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The settings page the Configure button opens (trait ConfigurationPage): shows and saves the multistore form.
 */
final class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * Receives the form handler of the settings form.
     */
    public function __construct(private readonly FormHandlerInterface $formHandler)
    {
    }

    /**
     * Shows the settings form with the values of the shop context.
     */
    public function indexAction(): Response
    {
        return $this->renderPage($this->formHandler->getForm());
    }

    /**
     * Saves the submitted settings, then redirects back to the page.
     */
    public function saveAction(Request $request): Response
    {
        $form = $this->formHandler->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->formHandler->save($form->getData());
            if ($errors === []) {
                $this->addFlash('success', 'Successful update');

                return $this->redirectToRoute('heavenmonotesting_configuration');
            }
            foreach ($errors as $error) {
                $this->addFlash('error', is_array($error) ? (string) ($error['key'] ?? '') : (string) $error);
            }
        }

        return $this->renderPage($form);
    }

    /**
     * Renders the page for the given (possibly invalid) form.
     *
     * @param FormInterface<string, FormInterface> $form
     */
    private function renderPage(FormInterface $form): Response
    {
        return $this->render('@Modules/heavenmonotesting/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $form->createView(),
            'layoutTitle' => 'Testing packages fixture',
        ]);
    }
}
