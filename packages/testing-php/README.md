# prestashopheaven/build-testing-php

PHP test helpers of the PrestaHaven modules: integration helpers for real shops (shop and language contexts,
containers, HTTP requests to the shop, a fake SMTP server, a Mailpit client, back-office form factories, order status
changes with an employee), the unit and integration bootstraps, and base test cases for installation, command bus and
module definition. A build package: `require-dev` only, never in a module zip (PKG-03).

## Use when

A module's PHPUnit tests: the bootstraps of `tests/Unit` and `tests/Integration`, integration tests that need a shop
context, a container, back-office pages, forms, mails or order status changes, and the shared tests every module
repeats (installation, command bus, module definition).

## Install

```
composer require --dev prestashopheaven/build-testing-php:^1.0
```

It requires `runtime-kit` and `runtime-cqrs` (the base test cases use `ModuleDefinitionInterface` and the bus of
`runtime-cqrs` under `prestashopheaven.<module>.command_bus`) and PHPUnit 10.5.

## The bootstraps

`tests/Unit/bootstrap.php` (defines `_PS_VERSION_` and `_DB_PREFIX_`, loads stand-ins for interfaces PrestaShop ships,
such as Symfony's `TranslatorInterface`, only when they are missing):

```php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
PrestaShopHeaven\Build\TestingPhp\UnitBootstrap::boot();
```

`tests/Integration/bootstrap.php` (PrestaShop first, so its autoloader and classes win; then the module's autoloader,
the Symfony kernel like `bin/console`, and the module under test for the helpers). The file is loaded by path because
nothing of `vendor/` may load before PrestaShop:

```php
require_once dirname(__DIR__, 2) . '/vendor/prestashopheaven/build-testing-php/src/IntegrationBootstrap.php';
PrestaShopHeaven\Build\TestingPhp\IntegrationBootstrap::boot('heavenfoo', dirname(__DIR__, 2));
```

Module-specific constants (the updater's `HEAVENUPDATER_SERVER_URL`, for example) go above the `boot()` line.

## The base test cases

Each shared test becomes a few lines; module-specific checks stay in the class as extra test methods.

`tests/Unit/Install/ModuleDefinitionTest.php` (hooks, configuration keys and `CONFIG_*` constants,
`GLOBAL_CONFIGURATION`, migrations, `TABLE`/`TABLE_*`, a method per hook (or the widget for display hooks), tabs,
routes; INS-01, INS-02, DB-01, HK-01, BO-11):

```php
final class ModuleDefinitionTest extends ModuleDefinitionTestCase
{
    protected function moduleName(): string
    {
        return 'heavenfoo';
    }

    protected function definition(): ModuleDefinitionInterface
    {
        return new ModuleDefinition();
    }
}
```

Overridable: `moduleDir()` (default: the folder above the test with `<module>.php`), `configurationPrefix()` (default
`HEAVENFOO_`), `hooksServedElsewhere()` (hooks a package trait serves).

`tests/Integration/InstallationTest.php` (installed and active, hooks, a global row per key, generated secrets, the
tables of the migrations, the tabs, the webservice resources, MS-02..04): the same two methods, extending
`InstallationTestCase`. The webservice resources come from the constant `WEBSERVICE_RESOURCES` of the definition
(override `webserviceResources()` otherwise).

`tests/Integration/CommandBusTest.php` (the bus in both containers, reachable through `ModuleServices`, a message
without handler refused):

```php
final class CommandBusTest extends CommandBusTestCase
{
    protected function moduleName(): string
    {
        return 'heavenfoo';
    }
}
```

## The helpers

Namespace `PrestaShopHeaven\Build\TestingPhp\`. The module's copies in `tests/Integration/` keep their method names,
so moving is a change of `use` lines (see the CHANGELOG for the few renames).

| Class | What |
|---|---|
| `Containers` | `front()` (puts the front container on the context, so `Module::get()` and the bus work), `backOffice()`, `bootKernel()` |
| `ShopContexts` | The back-office shop and language context of PS 9 without a request, the legacy context, saving and restoring it, removing values per shop and group, the shops of the multistore round |
| `RecordingFrontController` | A real `FrontController` that records `registerStylesheet()`/`registerJavascript()` (hook tests) |
| `Http` | Requests to the shop inside its container, the back-office login and session, `moduleConfigurationPage($shopContext)` of the module under test, the product edit page, the forms of a page (`symfonyForm()`, `formFields()`, `encodeFields()`, `hiddenInputs()`, `location()`) |
| `HtmlForm` | The parsing behind `Http` (no PrestaShop needed) |
| `FormFactories` | The form factory of the back office with every PrestaShop extension, from the module's form handler `handlerId()` = `prestashopheaven.<module>.form.configuration_handler`; `backOffice()`, `fromHandler()`, `reset()` |
| `OrderStatus` | `change($orderId, $stateId)` like the order page, with an employee in the context (PS 8.1 writes the stock movement with it), `asEmployee($callable)`, `employee()`, `current($orderId)`, `clearCache()` |
| `PublicHost` | `url($shopUrl)` / `urls([...])` put test URLs on the public example host www.example.org, `isTestHost($host)`: the test shops run on localhost, which a module that sends URLs out rightly skips, so a sending or queue test uses public URLs and asserts what is sent, or keeps the shop host and asserts the skip (TST-15) |
| `Mail\FakeSmtpServer` | `start()`, `mails()`, `rawMails()`, `attachments($raw)`, `configureUnreachable()`, `stop()`: PrestaShop's `Mail::send()` to a fake SMTP server |
| `Mail\MailpitClient` | Mailpit's API v1: `messages()`, `search('to:x')`, `waitFor()`, `message()`, `attachments()`, `raw()`, `part()`, `deleteAll()`, `deleteSearch()`, `isReachable()`; address from `MAILPIT_URL` (default `http://mailpit:8025`) |
| `Mail\MailSettings` | Points the shop's mail settings at a server and restores them: `pointAt($host, $port)`, `restore()` (Mailpit: `pointAt($client->smtpHost(), $client->smtpPort())`) |
| `Mail\SmtpCatcher`, `Mail\MimeParts` | The SMTP server process and the MIME parsing behind `FakeSmtpServer` |
| `TestedModule` | The module under test that the bootstrap sets (name, folder, configuration prefix) |

Example: a status hook test.

```php
$history = OrderStatus::change($orderId, (int) Configuration::get('PS_OS_PAYMENT'));
// the hooks ran with an employee in the context; the context employee is back
```

Example: a mail end to end with Mailpit (skipped when none runs next to the shop).

```php
$mailpit = new MailpitClient();
if (!$mailpit->isReachable()) {
    self::markTestSkipped('No Mailpit');
}
$settings = new MailSettings();
$settings->pointAt($mailpit->smtpHost(), $mailpit->smtpPort());
// ... the module sends ...
$found = $mailpit->waitFor('to:customer@example.test');
$settings->restore();
```

## Tests of this package

Unit tests with 100 % coverage (`composer check` in the monorepo). The classes that need a real shop are listed in
`extra.prestashopheaven.integration-tested`; their tests (`tests/Integration/`) run in real shops through the fixture
module `tests/fixture/heavenmonotesting` of the monorepo, with the helpers themselves.

---

Read-only split of [`prestashopheaven/mono-build-testing`](https://github.com/prestashopheaven/mono-build-testing): changes,
issues and pull requests go there. MIT License.
