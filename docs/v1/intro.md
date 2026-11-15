# Introduction

webware-configmanager is designed to simplify access to configuration data within applications. It
provides a nested object, property-based user interface for accessing this configuration data within
application code. The configuration data may come from a variety of formats supporting hierarchical
data storage. Currently, webware-configmanager provides adapters that read and write configuration
data stored in INI, JSON, YAML, and XML files.

## Using Reader Classes

Normally, users will use one of the [reader classes](reader.md) to read a
configuration file, but if configuration data is available in a PHP array, one
may simply pass the data to `Webware\ConfigManager\Config`'s constructor in order to
utilize a simple object-oriented interface:

```php
// An array of configuration data is given
$configArray = [
    'webhost'  => 'www.example.com',
    'database' => [
        'adapter' => 'pdo_mysql',
        'params'  => [
            'host'     => 'db.example.com',
            'username' => 'dbuser',
            'password' => 'secret',
            'dbname'   => 'mydatabase',
        ],
    ],
];

// Create the object-oriented wrapper using the configuration data
$config = new Webware\ConfigManager\Config($configArray);

// Print a configuration datum (results in 'www.example.com')
echo $config->webhost;
```

As illustrated in the example above, `Webware\ConfigManager\Config` provides nested object
property syntax to access configuration data passed to its constructor.

Along with the object-oriented access to the data values, `Webware\ConfigManager\Config`
also has a `get()` method that accepts a default value to return if the data
element requested doesn't exist in the configuration array. For example:

```php
$host = $config->database->get('host', 'localhost');
```

## Using PHP Configuration Files

PHP-based configuration files are often recommended due to the speed with which
they are parsed, and the fact that they can be cached by opcode caches.

The following code illustrates how to use PHP configuration files.

Create a sepatared PHP file which contains the configuration, e.g. `config.php`:

```php
return [
    'webhost'  => 'www.example.com',
    'database' => [
        'adapter' => 'pdo_mysql',
        'params'  => [
            'host'     => 'db.example.com',
            'username' => 'dbuser',
            'password' => 'secret',
            'dbname'   => 'mydatabase',
        ],
    ],
];
```

Use the configuration array from this file in another PHP script, e.g.
`index.php`:

```php
$config = new Webware\ConfigManager\Config(include 'config.php');

echo $config->webhost; // 'www.example.com'
```

## Reading configuration from a container

`Webware\ConfigManager\AbstractConfigFactory` is a Laminas abstract factory that turns
subtrees of the container's `config` service into individually injectable services, so a
constructor can depend on `config-db` rather than on the whole `config` array:

```php
// config/autoload/dependencies.global.php
return [
    'dependencies' => [
        'abstract_factories' => [
            Webware\ConfigManager\AbstractConfigFactory::class,
        ],
    ],
];
```

With that registered, a service name is mapped to a config key by matching it against a
list of patterns and taking the first capture group:

| Requested name | Config key |
|---|---|
| `config-db` | `db` |
| `config_db` | `db` |
| `Db\Config` | `Db` |
| `db-config` | `db` |

A key whose value is absent, or is `null`, is not resolvable: `has()` reports false and
`get()` throws. Falsy values that are not `null`, such as `0`, `''`, `false` and `[]`,
are resolvable.

Patterns are validated when they are supplied. A pattern that is not a non-empty string,
or that does not compile as a regular expression, raises
`Webware\ConfigManager\Exception\InvalidArgumentException` immediately rather than
failing later at match time. A pattern that matches without a capturing group is treated
as no match.

## Typed resolution

`resolve()` applies a caller-supplied `Psl\Type\TypeInterface` to the resolved entry, so
the return type is stated by the caller instead of described in a docblock:

```php
use Psl\Type;

$database = (new Webware\ConfigManager\AbstractConfigFactory())->resolve(
    container    : $container,
    requestedName: 'config-db',
    type         : Type\shape([
        'host' => Type\non_empty_string(),
        'port' => Type\positive_int(),
    ]),
);
```

The source is always the container's `config` service, the same one the abstract factory
reads, so a type can never be applied to an arbitrary array. A missing entry and an entry
that does not match both raise
`Webware\ConfigManager\Exception\InvalidArgumentException`; the coercion failure is
attached as the previous exception.

## Merging configuration from providers

An application's configuration is normally assembled from a list of `ConfigProvider`
classes rather than read from one file. `Webware\ConfigManager\ConfigAggregator` does
that, and is a drop-in replacement for `Laminas\ConfigAggregator\ConfigAggregator`:

```php
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Provider\ArrayProvider;
use Webware\ConfigManager\Provider\PhpFileProvider;

$aggregator = new ConfigAggregator(
    [
        Webware\Core\ConfigProvider::class,
        Webware\Log\ConfigProvider::class,
        new ArrayProvider(['config_cache_path' => 'data/cache/config-cache.php']),
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    ],
    'data/cache/config-cache.php',
);

return $aggregator->getMergedConfig();
```

The provider contract, the merge rules and the cache semantics are the aggregator's, so
the provider list needs no rewriting. See [the factory](factory.md) for the provider
table, the file ordering, and how the cache is read and written.

Once the array is built, `Webware\ConfigManager\Config` gives it object access, and
`Webware\ConfigManager\AbstractConfigFactory` makes its subtrees injectable.