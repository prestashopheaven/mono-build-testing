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
use PrestaShopHeaven\Build\TestingPhp\Http;
use PrestaShopHeaven\Build\TestingPhp\ShopContexts;
use PrestaShopHeaven\Build\TestingPhp\TestedModule;

/**
 * Real requests to the shop the tests run in: the front office, the back-office login and session, the settings page
 * of the fixture module per shop context (with its multistore checkboxes), saving it like a browser, and the product
 * page.
 */
final class HttpTest extends TestCase
{
    protected function tearDown(): void
    {
        ShopContexts::removeShopAndGroupValues([$this->key()]);
    }

    public function testTheFrontOfficeAnswers(): void
    {
        $home = Http::request(\Context::getContext()->link->getBaseLink());

        self::assertSame(200, $home['status']);
        self::assertStringContainsString('</html>', $home['body']);
        self::assertStringStartsWith('HTTP/', $home['headers']);
    }

    public function testTheBackOfficeRedirectsToTheLoginWithoutASession(): void
    {
        self::assertStringEndsWith(basename(_PS_ADMIN_DIR_) . '/index.php', Http::backOfficeUrl());
        $page = Http::request(Http::backOfficeUrl() . '?controller=AdminDashboard');

        self::assertContains($page['status'], [301, 302]);
        self::assertStringStartsWith('http', Http::location($page));
        // PrestaShop 9 has a Symfony login page (/login), PrestaShop 8 the legacy controller AdminLogin.
        self::assertStringContainsString(version_compare(_PS_VERSION_, '9.0.0', '>=') ? '/login' : 'AdminLogin', Http::location($page));
    }

    public function testOneSessionPerRun(): void
    {
        $session = Http::backOfficeSession();

        self::assertFileExists($session['jar']);
        self::assertNotSame('', $session['token']);
        self::assertSame($session, Http::backOfficeSession());
    }

    public function testTheSettingsPageOfTheModuleHasItsForm(): void
    {
        $page = Http::moduleConfigurationPage();

        self::assertSame(200, $page['status']);
        self::assertStringContainsString('/' . TestedModule::name() . '/configuration', $page['url']);
        $form = Http::symfonyForm($page['body'], $page['url']);
        self::assertIsArray($form);
        self::assertSame((string) \Configuration::getGlobalValue($this->key()), $form['fields'][$this->field($form['fields'], 'greeting')]);
        self::assertSame((string) (int) \Configuration::getGlobalValue(TestedModule::configurationPrefix() . 'LIMIT'), $form['fields'][$this->field($form['fields'], 'limit')]);
        self::assertSame([], preg_grep('/\[multistore_/', array_keys($form['fields'])), 'All shops: no multistore checkboxes.');
        self::assertSame($page['status'], Http::moduleConfigurationPage('', TestedModule::name())['status']);
    }

    public function testSavingTheFormInAShopContextGivesTheShopItsOwnValue(): void
    {
        $shop = ShopContexts::siblingShop();
        if ($shop === null) {
            self::markTestSkipped('Needs the multistore round (a second shop in the default group).');
        }
        $global = (string) \Configuration::getGlobalValue($this->key());
        $page = Http::moduleConfigurationPage('s-' . $shop);
        $form = Http::symfonyForm($page['body'], $page['url']);
        self::assertIsArray($form);
        // Without an own value the field is disabled in a shop context (a browser leaves it out, and so does
        // symfonyForm()); ticking the checkbox enables it, so the name comes from the page.
        self::assertSame(1, preg_match('/name="([^"]*\[greeting\])"/', $page['body'], $match), 'The shop context shows the field.');
        $greeting = $match[1];
        $checkbox = (string) preg_replace('/\[greeting\]$/', '[multistore_greeting]', $greeting);
        self::assertStringContainsString('name="' . $checkbox . '"', $page['body'], 'The shop context shows the multistore checkbox.');

        $fields = $form['fields'];
        $fields[$checkbox] = '1';
        $fields[$greeting] = 'Hello shop ' . $shop;
        $session = Http::backOfficeSession();
        $saved = Http::request($form['action'], $fields, $session['jar'], true);

        self::assertSame(200, $saved['status']);
        \Configuration::resetStaticCache();
        self::assertSame('Hello shop ' . $shop, \Configuration::get($this->key(), null, (int) \Shop::getGroupFromShop($shop), $shop));
        self::assertSame($global, (string) \Configuration::getGlobalValue($this->key()), 'The global value stays.');
        Http::moduleConfigurationPage('');
    }

    public function testTheProductPageAndItsForm(): void
    {
        $productId = (int) \Db::getInstance()->getValue('SELECT MIN(`id_product`) FROM `' . _DB_PREFIX_ . 'product`');
        $page = Http::productEditPage($productId);

        self::assertSame(200, $page['status']);
        $form = Http::formFields($page['body'], $page['url'], 'product');
        self::assertIsArray($form);
        self::assertNotSame([], $form['fields']);
        self::assertStringContainsString('product%5B', Http::encodeFields($form['fields']));
        self::assertNotSame([], Http::hiddenInputs($page['body']));
    }

    /**
     * The name of the form field that ends with [<name>].
     *
     * @param array<string, string> $fields
     */
    private function field(array $fields, string $name): string
    {
        foreach (array_keys($fields) as $field) {
            if (str_ends_with($field, '[' . $name . ']')) {
                return $field;
            }
        }
        self::fail("No field [$name] in " . implode(', ', array_keys($fields)));
    }

    /**
     * The text setting of the fixture module.
     */
    private function key(): string
    {
        return TestedModule::configurationPrefix() . 'GREETING';
    }
}
