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

namespace PrestaShopHeaven\MonoTesting\Fixture\Form;

use PrestaShopBundle\Form\Admin\Type\MultistoreConfigurationType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The settings form of the fixture: a text and a number field, each with its multistore checkbox (MS-05), for the
 * tests of FormFactories, Http and ShopContexts, and the multistore round of ps-module shops.
 */
final class ConfigurationType extends TranslatorAwareType
{
    /** The text field. */
    public const FIELD_GREETING = 'greeting';

    /** The number field. */
    public const FIELD_LIMIT = 'limit';

    /**
     * Adds the two fields with their configuration keys.
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(self::FIELD_GREETING, TextType::class, [
                'label' => $this->trans('Greeting', 'Modules.Heavenmonotesting.Admin'),
                'required' => false,
                'multistore_configuration_key' => ModuleDefinition::CONFIG_GREETING,
            ])
            ->add(self::FIELD_LIMIT, IntegerType::class, [
                'label' => $this->trans('Limit', 'Modules.Heavenmonotesting.Admin'),
                'required' => false,
                'multistore_configuration_key' => ModuleDefinition::CONFIG_LIMIT,
            ]);
    }

    /**
     * Makes PrestaShop add the multistore checkbox to every field with a multistore_configuration_key.
     */
    public function getParent(): string
    {
        return MultistoreConfigurationType::class;
    }
}
