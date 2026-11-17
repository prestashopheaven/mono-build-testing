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

namespace PrestaShopHeaven\Build\TestingPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Build\TestingPhp\HtmlForm;

/**
 * Pages and answers read like a browser: redirects, hidden inputs, Symfony forms (regex) and any form (DOM).
 */
final class HtmlFormTest extends TestCase
{
    private const PAGE = 'http://shop.test:8080/admin-dev/index.php/modules/heavenfoo/configuration?_token=abc';

    public function testTheLocationBecomesAnAbsoluteUrl(): void
    {
        $response = static fn (string $headers): array => ['status' => 302, 'headers' => $headers, 'body' => '', 'url' => self::PAGE];

        self::assertSame('http://shop.test:8080/admin-dev/index.php/x', HtmlForm::location($response("HTTP/1.1 302 Found\r\nLocation: /admin-dev/index.php/x\r\n")));
        self::assertSame('https://other.test/y', HtmlForm::location($response("HTTP/1.1 302 Found\r\nlocation: https://other.test/y\r\n")));
        self::assertSame('', HtmlForm::location($response("HTTP/1.1 200 OK\r\n")));
    }

    public function testHiddenInputsAreDecoded(): void
    {
        $html = '<form><input type="hidden" name="token" value="a&amp;b"><input type="hidden" name="flag">'
            . '<input type="hidden" value="no name"><input type="text" name="email" value="x"></form>';

        self::assertSame(['token' => 'a&b', 'flag' => ''], HtmlForm::hiddenInputs($html));
    }

    public function testTheSymfonyFormGivesWhatABrowserSends(): void
    {
        $html = '<form name="search"><input name="q" value="ignored"></form>'
            . '<form name="form" method="post" action="/admin-dev/index.php/save?_token=abc">'
            . '<input type="hidden" name="form[_token]" value="csrf">'
            . '<input type="checkbox" name="form[multistore_name]" value="1">'
            . '<input type="text" name="form[name]" value="Shop &amp; Co">'
            . '<input type="checkbox" name="form[enabled]" value="1" checked>'
            . '<input type="checkbox" name="form[on]" checked>'
            . '<input type="radio" name="form[mode]" value="a">'
            . '<input type="radio" name="form[mode]" value="b" checked>'
            . '<input name="form[plain]" value="p">'
            . '<input type="text" name="form[off]" value="x" disabled>'
            . '<input type="text" value="no name">'
            . '<select name="form[country]"><option value="nl">NL</option><option selected value="de">DE</option></select>'
            . '<select name="form[lang]"><option value="en" selected>EN</option></select>'
            . '<select name="form[none]"><option value="x">X</option></select>'
            . '<textarea name="form[note]">  Hello &lt;b&gt;  </textarea>'
            . '</form>';

        self::assertSame([
            'action' => 'http://shop.test:8080/admin-dev/index.php/save?_token=abc',
            'fields' => [
                'form[_token]' => 'csrf',
                'form[name]' => 'Shop & Co',
                'form[enabled]' => '1',
                'form[on]' => '1',
                'form[mode]' => 'b',
                'form[plain]' => 'p',
                'form[country]' => 'de',
                'form[lang]' => 'en',
                'form[none]' => '',
                'form[note]' => 'Hello <b>',
            ],
        ], HtmlForm::symfonyForm($html, self::PAGE));
    }

    public function testASymfonyFormWithoutActionPostsToThePage(): void
    {
        $empty = HtmlForm::symfonyForm('<form action=""><input type="hidden" name="f[_token]" value="t"></form>', self::PAGE);
        $none = HtmlForm::symfonyForm('<form><input type="hidden" name="f[_token]" value="t"></form>', self::PAGE);
        $absolute = HtmlForm::symfonyForm('<form action="https://x.test/a"><input type="hidden" name="f[_token]" value="t"></form>', self::PAGE);

        self::assertSame(self::PAGE, $empty['action'] ?? null);
        self::assertSame(self::PAGE, $none['action'] ?? null);
        self::assertSame('https://x.test/a', $absolute['action'] ?? null);
        self::assertNull(HtmlForm::symfonyForm('<form><input name="q"></form><p>no form</p>', self::PAGE));
    }

    public function testFormFieldsKeepTheOrderRepeatsAndSkipTemplates(): void
    {
        $html = '<html><body><form name="product" method="post" action="/admin-dev/product/save">'
            . '<template><div><input name="product[proto]" value="x"></div></template>'
            . '<input type="hidden" name="product[_token]" value="t">'
            . '<input type="text" name="product[name]" value="Mug">'
            . '<input type="text" value="no name">'
            . '<input type="text" name="product[off]" value="x" disabled>'
            . '<input type="checkbox" name="product[tags][]" value="a" checked>'
            . '<input type="checkbox" name="product[tags][]" value="b">'
            . '<input type="checkbox" name="product[flag]" checked>'
            . '<input type="radio" name="product[mode]" value="m" checked>'
            . '<input type="submit" name="product[save]" value="Save">'
            . '<input type="file" name="product[image]">'
            . '<select name="product[shops][]" multiple><option value="1" selected>1</option><option value="2" selected>2</option><option value="3">3</option></select>'
            . '<select name="product[type]"><option>standard</option><option value="pack">pack</option></select>'
            . '<select name="product[unit]"><option value="kg" selected>kg</option><option value="g" selected>g</option></select>'
            . '<select name="product[empty]"></select>'
            . '<textarea name="product[description]">Nice &amp; big</textarea>'
            . '</form></body></html>';

        self::assertSame([
            'action' => 'http://shop.test:8080/admin-dev/product/save',
            'fields' => [
                ['product[_token]', 't'],
                ['product[name]', 'Mug'],
                ['product[tags][]', 'a'],
                ['product[flag]', 'on'],
                ['product[mode]', 'm'],
                ['product[shops][]', '1'],
                ['product[shops][]', '2'],
                ['product[type]', 'standard'],
                ['product[unit]', 'kg'],
                ['product[description]', 'Nice & big'],
            ],
        ], HtmlForm::formFields($html, self::PAGE, 'product'));
    }

    public function testAMultipleSelectWithoutSelectionSendsNothing(): void
    {
        $html = '<form name="form" action="/save">'
            . '<select name="form[excluded][]" multiple><option value="3">Lamps</option><option value="4">Tools</option></select>'
            . '<select name="form[hour]"><option value="1">01:00</option><option value="6">06:00</option></select>'
            . '</form>';

        self::assertSame([['form[hour]', '1']], HtmlForm::formFields($html, self::PAGE, 'form')['fields'] ?? null, 'Like a browser: a single select sends its first option.');
    }

    public function testFormFieldsOfAFormWithoutActionOrAnUnknownForm(): void
    {
        $result = HtmlForm::formFields('<form name="f"><input name="a" value="1"></form>', self::PAGE, 'f');
        $absolute = HtmlForm::formFields('<form name="f" action="https://x.test/a"></form>', self::PAGE, 'f');

        self::assertSame(['action' => self::PAGE, 'fields' => [['a', '1']]], $result);
        self::assertSame(['action' => 'https://x.test/a', 'fields' => []], $absolute);
        self::assertNull(HtmlForm::formFields('<form name="f"></form>', self::PAGE, 'other'));
    }

    public function testEncodedFieldsKeepRepeatedNames(): void
    {
        self::assertSame('a%5B%5D=1&a%5B%5D=2&b=x%20y%26z', HtmlForm::encodeFields([['a[]', '1'], ['a[]', '2'], ['b', 'x y&z']]));
        self::assertSame('', HtmlForm::encodeFields([]));
    }
}
