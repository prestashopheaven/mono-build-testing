# Changelog

All packages of this monorepo share one version. Per package details: its own `CHANGELOG.md`.

## 1.0.1

- `build-testing-php` 1.0.1: `HtmlForm::formFields()` sends nothing for a `<select multiple>` without a selected
  option, like a browser (it sent the first option). A single select without a selection still sends its first option.

## 1.0.0

- Monorepo created (2026-11-07), milestone M4 of the package collection (`docs/packages/STATUS.md`).
- `build-testing-php` 1.0.0: the integration helpers of the modules (`ShopContexts`, `Containers`,
  `RecordingFrontController`, `Http`, `FormFactories`, `Mail\FakeSmtpServer`), merged from their drifted copies; the
  unit and integration bootstraps as `UnitBootstrap::boot()` and `IntegrationBootstrap::boot()`; the base test cases
  `InstallationTestCase`, `CommandBusTestCase` and `ModuleDefinitionTestCase` on runtime-kit and runtime-cqrs; new: a
  Mailpit client, `OrderStatus` (status changes with an employee), `MailSettings`, `HtmlForm`, `TestedModule`.
- `build-testing-js` 1.0.0: `_dev/tests/support` (`shop.js`, `setup.js`, `shop.d.ts`), `withTestingSupport()` for the
  module's Vitest config, `Paths`.
- Fixture module `tests/fixture/heavenmonotesting`: the integration tests of `build-testing-php` in real shops, through
  the helpers themselves.
