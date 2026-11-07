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
 * HTTP requests from the integration tests to the shop they run in (inside its container), for front
 * controllers and back-office pages: real requests through the web server, like a browser. The parsing of pages and
 * forms is HtmlForm's; the methods here keep their old names so module tests call Http:: for everything.
 */
final class Http
{
    /** The demo employee of the test shops (ps-module shops, the internal test shop). */
    public const EMPLOYEE_EMAIL = 'demo@prestashop.com';

    /** Its password. */
    public const EMPLOYEE_PASSWORD = 'prestashop_demo';

    /** @var array{jar: string, token: string}|null the back-office session of this test run */
    private static ?array $session = null;

    /**
     * Sends a request. The URL keeps the shop domain (Host header, cookies, redirects), but curl connects to
     * the web server of the container (127.0.0.1:80): the published port of the domain only exists on the host.
     *
     * @param array<string, string>|string|null $post form fields for a POST request, or a raw body (XML/JSON for the webservice, which reads php://input)
     * @param string|null $jar cookie file of a session (back office)
     * @param string[] $headers extra request headers
     *
     * @return array{status: int, headers: string, body: string, url: string} the headers of every response (redirects too), as sent
     *
     * @throws \RuntimeException when curl cannot open the URL
     */
    public static function request(string $url, array|string|null $post = null, ?string $jar = null, bool $follow = false, array $headers = []): array
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? 'localhost') : 'localhost';
        $port = is_array($parts) ? ($parts['port'] ?? 80) : 80;
        $curl = curl_init($url) ?: throw new \RuntimeException('curl cannot open ' . $url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECT_TO => [$host . ':' . $port . ':127.0.0.1:80'],
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($jar !== null) {
            curl_setopt($curl, CURLOPT_COOKIEJAR, $jar);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $jar);
        }
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, is_string($post) ? $post : http_build_query($post));
        }
        $response = (string) curl_exec($curl);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
            'url' => (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
        ];
    }

    /**
     * The Location header of a response as an absolute URL ('' without one), see HtmlForm::location().
     *
     * @param array{status: int, headers: string, body: string, url: string} $response
     */
    public static function location(array $response): string
    {
        return HtmlForm::location($response);
    }

    /**
     * URL of the back office of the shop (index.php in the admin folder).
     */
    public static function backOfficeUrl(): string
    {
        return \Context::getContext()->link->getBaseLink() . basename(_PS_ADMIN_DIR_) . '/index.php';
    }

    /**
     * Logs in to the back office as the demo employee, like "ps-module shops" does (ps-test.php
     * backOfficeSmoke()). Returns the cookie file of the session and the Symfony _token.
     *
     * @return array{jar: string, token: string}
     *
     * @throws \RuntimeException when the login fails
     */
    public static function backOfficeLogin(): array
    {
        $jar = (string) tempnam(sys_get_temp_dir(), 'psbo');
        $login = self::request(self::backOfficeUrl() . '?controller=AdminLogin', null, $jar, true);
        $fields = HtmlForm::hiddenInputs($login['body']);
        $fields['email'] = self::EMPLOYEE_EMAIL;
        $fields['passwd'] = self::EMPLOYEE_PASSWORD;
        $fields['submitLogin'] = '1';
        $dashboard = self::request($login['url'], $fields, $jar, true);
        if (preg_match('/_token=([A-Za-z0-9_\-.]+)/', $dashboard['body'] . $dashboard['url'], $m) !== 1) {
            @unlink($jar);

            throw new \RuntimeException(sprintf('Back-office login failed (HTTP %d, landed on %s).', $dashboard['status'], $dashboard['url']));
        }

        return ['jar' => $jar, 'token' => $m[1]];
    }

    /**
     * One back-office session per test run (logging in is slow); the cookie file is removed at the end.
     *
     * @return array{jar: string, token: string}
     *
     * @throws \RuntimeException when the login fails
     */
    public static function backOfficeSession(): array
    {
        if (self::$session === null) {
            self::$session = self::backOfficeLogin();
            $jar = self::$session['jar'];
            register_shutdown_function(static function () use ($jar): void {
                @unlink($jar);
            });
        }

        return self::$session;
    }

    /**
     * Opens Modules > Configure of the module, logged in: getContent() redirects to the module's own settings
     * page. With multistore, first switches the shop context ('' = all shops, 's-2' = shop 2, 'g-2' = group 2;
     * PrestaShop keeps it in the employee cookie).
     *
     * @param string|null $module the technical name; default the module under test (TestedModule)
     *
     * @return array{status: int, headers: string, body: string, url: string} the settings page
     *
     * @throws \RuntimeException when the login fails
     */
    public static function moduleConfigurationPage(string $shopContext = '', ?string $module = null): array
    {
        $session = self::backOfficeSession();
        $page = self::request(self::backOfficeUrl() . '/improve/modules/manage/action/configure/' . TestedModule::name($module) . '?_token=' . $session['token'], null, $session['jar'], true);
        if (\Shop::isFeatureActive() && $page['status'] === 200) {
            $url = (string) preg_replace('/([?&])setShopContext=[^&]*&?/', '$1', $page['url']);
            self::request($url . (str_contains($url, '?') ? '&' : '?') . 'setShopContext=' . $shopContext, null, $session['jar'], true);
            $page = self::request($page['url'], null, $session['jar'], true);
        }

        return $page;
    }

    /**
     * Hidden input fields of an HTML page (login tokens), see HtmlForm::hiddenInputs().
     *
     * @return array<string, string>
     */
    public static function hiddenInputs(string $html): array
    {
        return HtmlForm::hiddenInputs($html);
    }

    /**
     * The Symfony form of a page with the fields a browser would send, see HtmlForm::symfonyForm().
     *
     * @return array{action: string, fields: array<string, string>}|null
     */
    public static function symfonyForm(string $html, string $pageUrl): ?array
    {
        return HtmlForm::symfonyForm($html, $pageUrl);
    }

    /**
     * The product edit page of the back office, logged in (PS 8.1: /sell/catalog/products-v2/, PS 9:
     * /sell/catalog/products/). With multistore on, the page shows a shop picker instead of the form in the
     * "all shops" context (pre_select_product_shop), so it opens in the context of one shop like a merchant does:
     * the default shop, or $shopId.
     *
     * @return array{status: int, headers: string, body: string, url: string}
     *
     * @throws \RuntimeException when no product edit page answers
     */
    public static function productEditPage(int $productId, ?int $shopId = null): array
    {
        $session = self::backOfficeSession();
        $shop = \Shop::isFeatureActive() ? '&setShopContext=s-' . ($shopId ?? (int) \Configuration::get('PS_SHOP_DEFAULT')) : '';
        foreach (['/sell/catalog/products-v2/', '/sell/catalog/products/'] as $path) {
            $page = self::request(self::backOfficeUrl() . $path . $productId . '/edit?_token=' . $session['token'] . $shop, null, $session['jar'], true);
            if ($page['status'] !== 404) {
                return $page;
            }
        }

        throw new \RuntimeException(sprintf('No product edit page found for product %d.', $productId));
    }

    /**
     * A form of a page as a browser sends it (DOMDocument, for the product page), see HtmlForm::formFields().
     *
     * @return array{action: string, fields: list<array{0: string, 1: string}>}|null
     */
    public static function formFields(string $html, string $pageUrl, string $formName): ?array
    {
        return HtmlForm::formFields($html, $pageUrl, $formName);
    }

    /**
     * The body of a form POST from the fields of formFields(), see HtmlForm::encodeFields().
     *
     * @param list<array{0: string, 1: string}> $fields
     */
    public static function encodeFields(array $fields): string
    {
        return HtmlForm::encodeFields($fields);
    }
}
