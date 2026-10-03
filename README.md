# vivutio/vivutio

**The vivutio core.** vivutio is a platform for the travel trade: suppliers who
run properties, tour operators and travel agents each run their own installation and add the
modules that apply to them. This package is what every installation shares.

It is one repository and one version, made of the packages below. It has no
release yet.

## Contents

- [The packages](#the-packages)
- [Requirements](#requirements)
- [Development](#development)
- [A module's authority suite](#a-modules-authority-suite)
- [Licence](#licence)

## The packages

| Package | Directory | For |
|---|---|---|
| `vivutio/contracts` | `src/Vivutio/Contracts` | The interfaces a module declares itself with and the core's packages meet each other through |
| `vivutio/registry-bundle` | `src/Vivutio/Bundle/RegistryBundle` | The modules an installation holds and what each declares |
| `vivutio/identity-bundle` | `src/Vivutio/Bundle/IdentityBundle` | The organization, its users, tiers and positions, permissions and the voters that decide them |
| `vivutio/shell-bundle` | `src/Vivutio/Bundle/ShellBundle` | The design system, the layouts and the navigation |
| `vivutio/place-bundle` | `src/Vivutio/Bundle/PlaceBundle` | Countries, destinations and their fees |
| `vivutio/partner-bundle` | `src/Vivutio/Bundle/PartnerBundle` | The organizations an installation trades with and the channel each is reached through |
| `vivutio/connect-bundle` | `src/Vivutio/Bundle/ConnectBundle` | The connection to the vivutio hub, off until an installation turns it on |

The contracts are MIT while everything else here is AGPL: a module declares
itself by implementing them, so their licence decides what licence a module may
carry.

Requiring `vivutio/vivutio` provides all of them. Today each bundle registers
and nothing more; the table says what each is for, not what it already does.

An installation is complete without the hub. No package outside the Connect
bundle depends on it, and a specification in `tests/Core` keeps it so.

## Requirements

- PHP 8.4 or newer
- Symfony 8

## Development

```
composer update
composer check
```

`composer check` runs the code style check, static analysis at the highest
level, the boundary check and the test suite. It passes before every commit;
`composer cs:fix` applies the code style first.

The boundary check is `composer require-check`: each package's `composer.json`
declares what that package may use, and a symbol it uses without declaring the
dependency that provides it fails the build. That is what keeps each manifest
true, and what would make splitting this repository a move of files.

The specifications about the bundles together are in `tests/Core`, and run
inside the application in `tests/Application`, which registers every core
bundle the way an installation does.

## A module's authority suite

Every module's suite is held to the same five proofs as the core: every route
names the attribute it checks, one voter answers each question, the reviewed
authority table holds, no write leaves anybody holding more than its sender
could grant, and no marker value reaches somebody who may not see it. The proofs
ship in the Identity bundle; a module's suite extends them and says only what
is its own:

```php
use Vivutio\Bundle\IdentityBundle\Test\AuthorityTestCase;
use Vivutio\Bundle\IdentityBundle\Test\Probe;

final class PropertyAuthorityTest extends AuthorityTestCase
{
    protected static function probes(): array
    {
        return [
            new Probe('property_list', 'GET', '/properties'),
            new Probe('property_save', 'POST', '/properties/new', ['name' => 'A camp'], formAt: '/properties/new'),
        ];
    }

    protected static function packageDirectory(): string
    {
        return \dirname(__DIR__).'/src';
    }

    protected static function authorityTable(): string
    {
        return __DIR__.'/authority-table.md';
    }
}
```

Its test kernel turns on `framework.test` and uses PostgreSQL, which the base
empties and migrates for each test. A route of the module that checks nothing
is listed in `openRoutes()` with its reason, and a sensitive field of its own is
seeded with a marker in `seedCanaries()`. The first run asks for the table:

```
VIVUTIO_RECORD_AUTHORITY_TABLE=1 vendor/bin/phpunit --filter PropertyAuthorityTest
```

and every later change to it fails until it is recorded again and its diff is
read. `tests/Application/NotesModule` is a stand-in module built this way.

## Licence

AGPL-3.0-or-later. See [LICENSE](LICENSE).
