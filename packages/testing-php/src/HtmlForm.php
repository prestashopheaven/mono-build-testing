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
 * Reads pages and answers of the shop like a browser for the integration tests (Http): the hidden inputs, the fields
 * of a form, the body a browser would post, and the target of a redirect. No PrestaShop needed.
 */
final class HtmlForm
{
    /**
     * The Location header of a response as an absolute URL ('' without one). PrestaShop's Symfony pages
     * redirect with a path ("/admin-dev/index.php/..."), which curl cannot open on its own.
     *
     * @param array{status: int, headers: string, body: string, url: string} $response
     */
    public static function location(array $response): string
    {
        if (preg_match('/^location:\s*(\S+)/im', $response['headers'], $m) !== 1) {
            return '';
        }
        $location = $m[1];
        if (str_starts_with($location, '/')) {
            $location = self::origin($response['url']) . $location;
        }

        return $location;
    }

    /**
     * Hidden input fields of an HTML page (login tokens).
     *
     * @return array<string, string>
     */
    public static function hiddenInputs(string $html): array
    {
        $fields = [];
        preg_match_all('/<input[^>]*type="hidden"[^>]*>/i', $html, $inputs);
        foreach ($inputs[0] as $input) {
            if (preg_match('/name="([^"]+)"/', $input, $n) === 1) {
                preg_match('/value="([^"]*)"/', $input, $v);
                $fields[html_entity_decode($n[1])] = html_entity_decode($v[1] ?? '');
            }
        }

        return $fields;
    }

    /**
     * The Symfony form of a page (the form with a "<name>[_token]" field): its action and the fields a
     * browser would send (text and hidden inputs, checked radios and checkboxes, selected options).
     * A regular expression: fine for simple pages (module settings, grid filters). Pages with nested </form>
     * tags in <template> elements or repeated field names (the product page) need formFields().
     *
     * @return array{action: string, fields: array<string, string>}|null
     */
    public static function symfonyForm(string $html, string $pageUrl): ?array
    {
        preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $forms, PREG_SET_ORDER);
        foreach ($forms as $form) {
            if (preg_match('/name="[^"]+\[_token\]"/', $form[2]) !== 1) {
                continue;
            }
            preg_match('/action="([^"]*)"/', $form[1], $action);
            // An empty action (action="") posts to the page itself, like a browser does.
            $target = ($action[1] ?? '') !== '' ? html_entity_decode($action[1]) : $pageUrl;
            if (str_starts_with($target, '/')) {
                $target = self::origin($pageUrl) . $target;
            }
            $fields = [];
            // An <input> has no content; only <select> and <textarea> take everything up to their own end tag. (One
            // optional content group for all three let an <input> swallow a <select> or <textarea> after it, e.g. the
            // multistore checkbox before a select or textarea field.)
            preg_match_all('/<(input)\b([^>]*)>|<(select|textarea)\b([^>]*)>(.*?)<\/\3>/is', $form[2], $matches, PREG_SET_ORDER);
            $tags = array_map(static fn (array $m): array => ($m[1] ?? '') !== ''
                ? ['', 'input', $m[2], '']
                : ['', strtolower($m[3]), $m[4], $m[5]], $matches);
            foreach ($tags as $tag) {
                if (preg_match('/\bname="([^"]+)"/', $tag[2], $n) !== 1 || preg_match('/\bdisabled\b/', $tag[2]) === 1) {
                    continue;
                }
                $name = html_entity_decode($n[1]);
                preg_match('/\btype="([^"]+)"/', $tag[2], $t);
                preg_match('/\bvalue="([^"]*)"/', $tag[2], $v);
                $type = strtolower($t[1] ?? ($tag[1] === 'input' ? 'text' : $tag[1]));
                if (in_array($type, ['radio', 'checkbox'], true)) {
                    if (preg_match('/\bchecked\b/', $tag[2]) === 1) {
                        $fields[$name] = html_entity_decode($v[1] ?? '1');
                    }
                    continue;
                }
                if ($tag[1] === 'select') {
                    preg_match('/<option[^>]*\bselected\b[^>]*value="([^"]*)"|<option[^>]*value="([^"]*)"[^>]*\bselected\b/i', $tag[3], $o);
                    $fields[$name] = html_entity_decode(($o[1] ?? '') !== '' ? $o[1] : ($o[2] ?? ''));
                    continue;
                }
                $fields[$name] = $tag[1] === 'textarea' ? html_entity_decode(trim($tag[3])) : html_entity_decode($v[1] ?? '');
            }

            return ['action' => $target, 'fields' => $fields];
        }

        return null;
    }

    /**
     * A form of a page as a browser sends it: its action and every successful control in document order (text,
     * hidden and other inputs, checked boxes and radios, selected options, textareas), without disabled controls
     * and without prototypes in <template> elements. Parsed with DOMDocument, so nested </form> tags in templates
     * (the product page) and repeated names (multiple selects) work. null when the page has no form with that
     * name attribute (the product page: "product").
     *
     * @return array{action: string, fields: list<array{0: string, 1: string}>}|null
     */
    public static function formFields(string $html, string $pageUrl, string $formName): ?array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@name="' . $formName . '"]');
        $form = $forms === false ? null : $forms->item(0);
        if (!$form instanceof \DOMElement) {
            return null;
        }
        // An empty action posts to the page itself, like a browser does.
        $action = $form->getAttribute('action') !== '' ? $form->getAttribute('action') : $pageUrl;
        if (str_starts_with($action, '/')) {
            $action = self::origin($pageUrl) . $action;
        }
        $fields = [];
        foreach ($xpath->query('.//input | .//select | .//textarea', $form) ?: [] as $control) {
            if (!$control instanceof \DOMElement || $control->getAttribute('name') === '' || $control->hasAttribute('disabled')) {
                continue;
            }
            $templates = $xpath->query('ancestor::template', $control);
            if ($templates !== false && $templates->length > 0) {
                continue;
            }
            $name = $control->getAttribute('name');
            $type = strtolower($control->getAttribute('type'));
            if ($control->tagName === 'textarea') {
                $fields[] = [$name, $control->textContent];
            } elseif ($control->tagName === 'select') {
                $selected = $xpath->query('.//option[@selected]', $control);
                // Nothing selected: a single select sends its first option, a multiple select sends nothing.
                $options = $selected !== false && $selected->length > 0
                    ? $selected
                    : ($control->hasAttribute('multiple') ? [] : $xpath->query('.//option[1]', $control));
                foreach ($options ?: [] as $option) {
                    if ($option instanceof \DOMElement) {
                        $fields[] = [$name, $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent];
                    }
                    if (!$control->hasAttribute('multiple')) {
                        break;
                    }
                }
            } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                if ($control->hasAttribute('checked')) {
                    $fields[] = [$name, $control->hasAttribute('value') ? $control->getAttribute('value') : 'on'];
                }
            } elseif (!in_array($type, ['submit', 'button', 'reset', 'image', 'file'], true)) {
                $fields[] = [$name, $control->getAttribute('value')];
            }
        }

        return ['action' => $action, 'fields' => $fields];
    }

    /**
     * The body of a form POST from the fields of formFields() (repeated names stay repeated), for Http::request() with
     * the header "Content-Type: application/x-www-form-urlencoded".
     *
     * @param list<array{0: string, 1: string}> $fields
     */
    public static function encodeFields(array $fields): string
    {
        return implode('&', array_map(static fn (array $field): string => rawurlencode($field[0]) . '=' . rawurlencode($field[1]), $fields));
    }

    /**
     * The scheme, host and port of a URL ("http://localhost:8080"), for paths without a host.
     */
    private static function origin(string $url): string
    {
        return (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $url);
    }
}
