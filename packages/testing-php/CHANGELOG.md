# Changelog

The version is the one of the monorepo `mono-build-testing`.

## 1.0.0

First version, merged from the `tests/` folders of the 17 modules in `modules/` and `heavenupdater` (scan of
2026-11-07, `docs/packages/inventory.md`). The namespace of every helper is `PrestaShopHeaven\Build\TestingPhp\`
(`Mail\` for the mail helpers, `TestCase\` for the base test cases) instead of `<Module>\Tests\Integration\`.

### Identical everywhere: moved as they are

| File | Copies | Note |
|---|---|---|
| `tests/Integration/ShopContexts.php` | 18 | Only the namespace |
| `tests/Integration/Containers.php` | 18 | Only the namespace and a comment (the kernel of `IntegrationBootstrap::boot()`) |
| `tests/Integration/RecordingFrontController.php` | 18 | Only the namespace |
| `tests/Integration/Support/fake-smtp-server.php` | 9 (2 variants, only the header comment differs) | `resources/fake-smtp-server.php`; the comment of the updater ("recipe mails") merged in |

### Drifted: merged

**`tests/Integration/Http.php`**, 3 variants:
- 12 (`heavenautoorderstatus`, `heavencartjourney`, `heavencmsblog`, `heavencreditcopy`, `heavendeleteorders`,
  `heavendeliverycopy`, `heavenfooterpaymenticons`, `heaveninvoicecopy`, `heavenipriskdetector`, `heavenlangimages`,
  `heavenproductasnew`, `heavenupdater`): `symfonyForm()` matches `<input>` without content and only `<select>` /
  `<textarea>` up to their end tag.
- 5 (`heavenattachmentmailer`, `heavenpricedropalert`, `heavenproductchangelog`, `heavenproducthistory`,
  `heavenproductstocksorting`): the older regex with one optional content group for all three tags; an `<input>` (the
  multistore checkbox) could swallow the `<select>` or `<textarea>` after it. A bug, fixed by the first variant.
- 1 (`heavenemailcopypro`): the same fix written differently (`count($m) > 2`), without the `?? ''` guards.

Resolution: the regex of the first variant (same behaviour as the third). An `<input>` now carries an empty content
element, so the guards and casts on it are gone (the form of the third variant). The parsing moved to `HtmlForm`
(unit tested); `Http` keeps every method name and delegates. The module name in `moduleConfigurationPage()` comes from
`TestedModule` (new optional second parameter `$module`); the demo login is the constants `EMPLOYEE_EMAIL` /
`EMPLOYEE_PASSWORD`. Nothing removed.

**`tests/Integration/bootstrap.php`**, 2 variants: 17 modules / `heavenupdater`, which also defines
`HEAVENUPDATER_SERVER_URL` and `HEAVENUPDATER_EXTRA_TRUSTED_KEYS` before the kernel boots. Resolution:
`IntegrationBootstrap::boot($module, $moduleDir)` does what all 18 do (PrestaShop first, the module's autoloader, the
kernel, `_PS_ADMIN_DIR_`, the hint without a shop) and sets `TestedModule`; module-specific constants stay in the
module's bootstrap, above the `boot()` line (they do not need PrestaShop).

**`tests/Integration/Support/FakeSmtpServer.php`**, 3 variants:
- 5 (`heavencartjourney`, `heavencreditcopy`, `heavenemailcopypro`, `heavenipriskdetector`, `heavenpricedropalert`):
  `start()`, `configureUnreachable()`, `mails()`, `stop()`.
- 3 (`heavenattachmentmailer`, `heavendeliverycopy`, `heaveninvoicecopy`): also `rawMails()` and the static
  `attachments($raw)` (MIME parts with base64 / quoted-printable, RFC 2231 file names, folded headers).
- 1 (`heavenupdater`): also saves, sets (`owner@example.test`) and restores `PS_SHOP_EMAIL`; encryption always `'off'`;
  `stop()` forgets the saved settings and the process (the instance can start again) and removes the mail folder
  recursively.

Resolution: every method of every variant, with the same names and results. Encryption: `''` with Swift Mailer
(PS 8, which turns `'off'` into the scheme `off://`) and `'off'` otherwise (PS 9: anything else, `''` too, means TLS),
the rule of the 8 module variants; on PS 9 that is the updater's `'off'`. `PS_SHOP_EMAIL`: saved and restored by every
use now, and set to `MailSettings::SHOP_EMAIL` (`owner@example.test`, the updater's value, a valid address so a mail to
the shop can be sent); no module test asserts the original shop address, and the tests of `heavencartjourney` and
`heavenipriskdetector` that set it themselves after `start()` restore it themselves. The reusable instance and the
recursive removal of the updater. New: the messages are sorted by number (`sort()` on the file names put `10.eml`
before `2.eml`). The class is now three: `SmtpCatcher` (the server process, unit tested), `MailSettings` (the shop's
settings, also for Mailpit) and `MimeParts` (`attachments()`, new `header()`); `FakeSmtpServer` keeps its API.

**`tests/Integration/Support/FormFactories.php`**, 2 variants: 4 (`heavenattachmentmailer`,
`heavenfooterpaymenticons`, `heavenpricedropalert`, `heavenproducthistory`) / 3 (`heavenautoorderstatus`,
`heavencreditcopy`, `heavenemailcopypro`) with a public `HANDLER` constant (used by their `ConfigurationTypeTest`) and
`fromHandler()` (the factory without touching the contexts). Resolution: `fromHandler()` kept; the constant becomes
`handlerId(?string $module = null)` = `prestashopheaven.<module>.form.configuration_handler` (a package has no module
name constant): replace `FormFactories::HANDLER` with `FormFactories::handlerId()`. `backOffice()` and `fromHandler()`
take an optional other handler id.

**`tests/Unit/bootstrap.php`**, 2 variants: 16 / `heavencartjourney` and `heavenproducthistory`, which also load a
stand-in for Symfony's `TranslatorInterface` when it is missing. Resolution: `UnitBootstrap::boot()` does both for every
module (the stand-in only loads when the interface is missing); the stub is `resources/stubs/translator-interface.stub`.
The module's bootstrap now loads its autoloader first (no class loads before the constants are defined).

### Base test cases (from the shared tests)

**`tests/Integration/InstallationTest.php`**, 4 variants → `TestCase\InstallationTestCase`:
- 15 the same.
- `heavencmsblog`: the hidden tab of the settings page exists and belongs to the module; the webservice knows the
  resources of `WEBSERVICE_RESOURCES` with specific management.
- `heaveninvoicecopy`: a global row in `ps_configuration` instead of `Configuration::hasKey()` (PrestaShop stores an
  empty default as NULL, and `hasKey()` then says the key is missing).
- `heavenpricedropalert`: `setUp()` reloads the configuration (`hasKey()` only reads the static cache, which another
  test may have emptied); the generated secrets have a value (`/^[A-Za-z0-9]{16,}$/`).

Resolution: all of them, for every module: the global-row check in both key tests, the configuration reload, the
secret check (runtime-kit writes 32 hex characters), the tab check for every tab of the definition, and the webservice
check driven by the constant `WEBSERVICE_RESOURCES` of the definition (4 modules have it; override
`webserviceResources()` otherwise). The module name and definition come from `moduleName()` and `definition()`.

**`tests/Integration/CommandBusTest.php`** (18 identical) → `TestCase\CommandBusTestCase`: on runtime-cqrs: the bus
under `ModuleCommandBus::serviceId($module)` instead of the module's own `SERVICE_ID` constant; the check on the main
class is generic (`ucfirst($module)`, and `getCommandBus()` of `ModuleServices` exists).

**`tests/Unit/Install/ModuleDefinitionTest.php`**, 2 variants → `TestCase\ModuleDefinitionTestCase`: 17 /
`heavenpricedropalert`, which adds checks on `CONFIG_*` constants, `GLOBAL_CONFIGURATION`, its tables, a trait per hook,
the tabs and the routes, plus checks of its own. Resolution: the general checks for every module, made general so the
definitions of all 17 modules and the updater pass them (checked with a scan of their definitions):
- `CONFIG_*`: only constants that name one key (`heavenproducthistory`'s `CONFIG_GROUP_KEYS` is an array of keys);
- tables: every `TABLE` / `TABLE_*` constant instead of the two names of `heavenpricedropalert`;
- hooks: a method `hook<Name>()` in the main file or `src/` (instead of the file `src/Module/Hook/<Name>.php`), or a
  display hook served by the module's widget (`heavencmsblog`, `heavenfooterpaymenticons`); `hooksServedElsewhere()`
  for hooks a package serves;
- routes: only legacy controllers of the module's own pages (`Admin<Module>…`) must be tabs; routes on core pages
  (`AdminOrders`, `AdminProducts`, `AdminCmsContent` in 4 modules) are left alone;
- tabs: unique class names `Admin<Module>…`, routes `<module>_…`, a wording, the domain `Modules.<Module>.Admin`.

Not in the base: "the methods give the constants" of `heavenpricedropalert` (that tests runtime-kit, TST rule: a
module does not test package code) and its module-specific checks (its form switches, its two tables); they stay in
its subclass.

### New

- `Mail\MailpitClient`: Mailpit's API v1 (the mail checks of the memory note "mail testing with Mailpit" as a client),
  over curl or an injected transport.
- `OrderStatus`: a status change like the order page with an employee in the context. From
  `heavenautoorderstatus/StatusAutomationBusTest` (employee 1, restored), `heavendeliverycopy/Support/Orders::changeState()`
  (lowest employee, not restored, no history row) and `heaveninvoicecopy/Support/Fixtures` (lowest employee, restored,
  history row). Merged: the loaded context employee, else the first active SuperAdmin (as
  `LegacyOrderStatusChanger` of `heavenautoorderstatus`), else the lowest id; always restored, also on an error; the
  history row by default (`$addHistory = false` for the behaviour of `heavendeliverycopy`); the order caches emptied
  before and after.
- `TestedModule`, `HtmlForm`, `IntegrationBootstrap`, `UnitBootstrap`, `Mail\SmtpCatcher`, `Mail\MailSettings`,
  `Mail\MimeParts::header()`.
