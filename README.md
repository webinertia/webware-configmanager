# webware/webware-configmanager

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-configmanager)](https://packagist.org/packages/webware/webware-configmanager)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-configmanager)](https://packagist.org/packages/webware/webware-configmanager)
[![License](https://img.shields.io/github/license/webinertia/webware-configmanager)](LICENSE)
[![Required CI](https://github.com/webinertia/webware-configmanager/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/webware-configmanager/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-configmanager/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-configmanager)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-configmanager%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-configmanager/1.0.x)

Nested object property based access to configuration data, with pluggable readers, writers and processors.

## Installation

```bash
composer require webware/webware-configmanager
```

## Documentation

- [Introduction](docs/v1/intro.md)
- [Theory of Operation](docs/v1/theory.md)
- [Readers](docs/v1/reader.md)
- [Writers](docs/v1/writer.md)
- [Processors](docs/v1/processor.md)
- [The Factory](docs/v1/factory.md)

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
Both plugin managers identify plugins by fully qualified class name only; no short names or aliases
are registered. The extension map used by `Factory` points at those class names.

## Relation to laminas-config-aggregator

This package is a fork of `laminas/laminas-config`, which the Laminas TSC has archived. It supplies
the nested configuration object and the file readers/writers; it does not aggregate providers. Use it
alongside `laminas/laminas-config-aggregator`, which collects `ConfigProvider` output into the `config`
service this package reads.

## Origin

Forked from `laminas/laminas-config` 3.12.x, which remains BSD-3-Clause licensed. See [LICENSE.md](LICENSE.md)
and [COPYRIGHT.md](COPYRIGHT.md).