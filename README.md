# webware/webware-configmanager

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-configmanager)](https://packagist.org/packages/webware/webware-configmanager)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-configmanager)](https://packagist.org/packages/webware/webware-configmanager)
[![License](https://img.shields.io/github/license/webinertia/webware-configmanager)](LICENSE)
[![Required CI](https://github.com/webinertia/webware-configmanager/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/webware-configmanager/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-configmanager/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-configmanager)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-configmanager%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-configmanager/1.0.x)

Nested object property based access to configuration data, with pluggable readers, writers and processors.

This package is the fork of `laminas/laminas-config` and a drop-in replacement for
`laminas/laminas-config-aggregator`, so one package supplies both the aggregator and the
configuration object it produces.

## Installation

```bash
composer require webware/webware-configmanager
```

## Documentation

The 1.0 release line is documented under [docs/1.0](docs/1.0):

- [Introduction](docs/1.0/intro.md)
- [Theory of Operation](docs/1.0/theory.md)
- [Readers](docs/1.0/reader.md)
- [Writers](docs/1.0/writer.md)
- [Processors](docs/1.0/processor.md)
- [The Factory](docs/1.0/factory.md)

## Usage

```php
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Factory;

$config = new Config([
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'webware',
    ],
]);

echo $config->db->host; // 127.0.0.1
```

`Factory` reads and writes `.ini`, `.json`, `.xml`, `.yaml`/`.yml`, `.properties` and `.php` files, and
merges several files into one configuration:

```php
$config = Factory::fromFiles(['config/autoload/global.php', 'config/autoload/local.php'], true);
```

Readers and writers are resolved through `ReaderPluginManager` and `WriterPluginManager` when
`laminas/laminas-servicemanager` is installed, and through the standalone plugin managers otherwise.
Both resolve a plugin by fully qualified class name and by file extension; `ConfigProvider` declares
those aliases, and the extension map `Factory` uses is read from the same declarations.

## Replacing laminas-config-aggregator

`ConfigAggregator` takes the same arguments in the same order and `getMergedConfig()` returns the
same array, so replacing the aggregator is a class name change. `ArrayProvider` and `PhpFileProvider`
are here too.

```php
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Provider\PhpFileProvider;

$aggregator = new ConfigAggregator(
    [
        \Mezzio\ConfigProvider::class,
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    ],
    'data/cache/config-cache.php',
);

return $aggregator->getMergedConfig();
```

Because `ConfigAggregator` extends `Config`, the merged result is the configuration rather than an
array that has to be wrapped: `get()`, array access, iteration and `toArray()` all work on it.
`getMergedConfig()` remains for the aggregator's own signature. The `ENABLE_CACHE` and
`CACHE_FILEMODE` constants are redeclared on it, so existing references keep resolving. For the
deprecated `LaminasConfigProvider`, use `Factory::fromFiles()`.

## Declaring merge order

A provider can declare its position instead of relying on where it appears in the list, which matters
because `laminas-component-installer` prepends what it writes, so a package installed last lands
first and loses every key it shares with a package it builds on.

```php
use Webware\ConfigManager\Attribute\LoadAfter;

#[LoadAfter(OtherProvider::class)]
final class ConfigProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return [];
    }
}
```

`#[LoadBefore]` is the counterpart and both accept several targets. A provider that declares nothing
keeps the position it was given, a target that is not installed is ignored, and a cycle is reported
as an exception naming its participants.

## Origin

Forked from `laminas/laminas-config` 3.12.x, which remains BSD-3-Clause licensed. See [LICENSE.md](LICENSE.md)
and [COPYRIGHT.md](COPYRIGHT.md).
