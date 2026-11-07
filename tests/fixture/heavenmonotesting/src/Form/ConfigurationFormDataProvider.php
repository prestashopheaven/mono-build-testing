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

use PrestaShop\PrestaShop\Core\Configuration\AbstractMultistoreConfiguration;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;
use PrestaShopHeaven\MonoTesting\Fixture\Install\ModuleDefinition;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;
use Symfony\Component\OptionsResolver\OptionsResolver;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Data of the settings form, read and written in the shop context of the back office with PrestaShop's own multistore
 * rules (AbstractMultistoreConfiguration): a field whose checkbox is off in a shop or group context inherits.
 */
final class ConfigurationFormDataProvider extends AbstractMultistoreConfiguration implements FormDataProviderInterface
{
    /**
     * Returns the values of the current shop context (FormDataProviderInterface).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->getConfiguration();
    }

    /**
     * Saves the submitted values (FormDataProviderInterface).
     *
     * @param array<string, mixed> $data
     *
     * @return string[] error messages, empty on success
     */
    public function setData(array $data): array
    {
        return $this->updateConfiguration($data);
    }

    /**
     * Returns the values of the current shop context (with fallback to group and global).
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        $shops = $this->getShopConstraint();

        return [
            ConfigurationType::FIELD_GREETING => (string) $this->configuration->get(ModuleDefinition::CONFIG_GREETING, null, $shops),
            ConfigurationType::FIELD_LIMIT => (int) $this->configuration->get(ModuleDefinition::CONFIG_LIMIT, null, $shops),
        ];
    }

    /**
     * Writes the values in the current shop context; a field without its ticked checkbox inherits again.
     *
     * @param array<string, mixed> $configuration form data, with multistore_<field> for ticked checkboxes
     *
     * @return string[] error messages, empty on success
     */
    public function updateConfiguration(array $configuration): array
    {
        try {
            $this->validateConfiguration($configuration);
        } catch (OptionsResolverException $e) {
            return ['The settings are not valid: ' . $e->getMessage()];
        }
        $shops = $this->getShopConstraint();
        $this->updateConfigurationValue(ModuleDefinition::CONFIG_GREETING, ConfigurationType::FIELD_GREETING, $configuration, $shops);
        $this->updateConfigurationValue(ModuleDefinition::CONFIG_LIMIT, ConfigurationType::FIELD_LIMIT, $configuration, $shops);

        return [];
    }

    /**
     * Describes the fields for validateConfiguration().
     */
    protected function buildResolver(): OptionsResolver
    {
        return (new OptionsResolver())
            ->setDefined([ConfigurationType::FIELD_GREETING, ConfigurationType::FIELD_LIMIT])
            ->setAllowedTypes(ConfigurationType::FIELD_GREETING, ['string', 'null'])
            ->setAllowedTypes(ConfigurationType::FIELD_LIMIT, ['int', 'null']);
    }
}
