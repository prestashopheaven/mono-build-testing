# mono-build-testing

Shared test helpers of the PrestaHaven modules: PHP integration helpers for real shops (contexts, containers, HTTP, mail, forms, order status), base test cases for the tests every module repeats, and the JavaScript test doubles for Vitest.

A monorepo of the PrestaHaven package collection: every folder in `packages/` is one Composer package, published as a
read-only repo `prestashopheaven/build-<folder>` on Packagist. All packages of this monorepo share one version.
The plan, the rules and the other monorepos: `docs/packages/` in the PrestaHaven workspace.

## Packages

| Package | Use when |
|---|---|
| [`prestashopheaven/build-testing-php`](packages/testing-php) | A module's PHPUnit tests: the bootstraps of `tests/Unit` and `tests/Integration`, integration tests that need a shop context, a container, back-office pages, forms, mails or order status changes, and the shared tests every module repeats (installation, command bus, module definition). |
| [`prestashopheaven/build-testing-js`](packages/testing-js) | A module has JavaScript or TypeScript tests in `_dev/tests` (Vitest + jsdom): import the PrestaShop test doubles and use the shared setup file instead of copying `_dev/tests/support`. |

Both are build packages: `require-dev` in a module, never in its zip. The exact lines a module needs are in each
package's README.

(`ps-module mono packages` lists them; the package index in the workspace has every class.)

## Work on it

```
composer install
composer check                 # ps-module mono check: the definition of done of every package
composer phpstan               # PHPStan against PrestaShop 8.1.0 and 9.2.0 (tests/phpstan; mono check skips it for build monorepos)
npm ci && npm test             # the JavaScript tests of build-testing-js (Vitest + jsdom)
ps-module mono package <dir> "<description>" "<use when>" <keywords>   # a new package
```

The integration tests of `build-testing-php` run in real shops through the fixture module
`tests/fixture/heavenmonotesting` (base port 52000; CI runs it on every PrestaShop version):

```
cd tests/fixture/heavenmonotesting
composer install               # copies the package's tests/Integration into tests/Packages
vendor/bin/ps-module shops run 9.2.0 --skip-upgrade --skip-behat
```

Changes are made here, never in a split repo.

## Release

1. Write the changes under `## X.Y.Z` in `CHANGELOG.md` (and in the `CHANGELOG.md` of each changed package).
2. `ps-module mono set-version X.Y.Z` (root version, branch aliases, internal constraints).
3. Commit `Release X.Y.Z - <notes>` on `main` and push.

The release workflow checks everything, tags `vX.Y.Z`, makes the GitHub release, splits every package to its repo with
the same tag and updates Packagist. Never tag by hand.
