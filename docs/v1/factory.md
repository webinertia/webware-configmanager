# The Factory

`Webware\ConfigManager\Factory` provides the ability to load configuration files to an
array or to a `Webware\ConfigManager\Config` object. The factory has two purposes

- Loading configuration file(s)
- Storing a configuration file

<!-- markdownlint-disable-next-line heading-increment -->
> ### Storage writes to a single file
>
> Storing the configuration always writes to a **single** file. The factory is
> not aware of merged configuration files, and as such cannot split
> configuration to multiple files.  If you want to store particular
> configuration sections to separate files, you should separate them manually.

## Loading configuration files

The first example illustrates loading a single configuration file:

```php
// Load a PHP file as array:
$config = Webware\ConfigManager\Factory::fromFile(__DIR__ . '/config/my.config.php');

// Load an XML file as Config object; the second parameter, when true,
// casts the configuration to a Config instance:
$config = Webware\ConfigManager\Factory::fromFile(__DIR__.'/config/my.config.xml', true);
```

The next example demonstrates merging multiple files; note that they are in
separate formats!

```php
$config = Webware\ConfigManager\Factory::fromFiles([
    __DIR__.'/config/my.config.php',
    __DIR__.'/config/my.config.xml',
]);
```

### Steering the merge

Merging follows the rules the Mezzio and Laminas config aggregator use. Where those
packages accept `Laminas\Stdlib\ArrayUtils\MergeRemoveKey` and `MergeReplaceKey`
objects, this package accepts `Webware\ConfigManager\Merge\MergeDirective`, which
plays the same role without a dependency on `laminas/laminas-stdlib`:

```php
use Webware\ConfigManager\Merge\MergeDirective;

return [
    // Drop this key from the merged result.
    'cache' => MergeDirective::remove(),

    // Replace the whole value instead of merging into it.
    'router' => MergeDirective::replace(['routes' => []]),
];
```

A directive returned by any configuration file is honoured wherever the aggregator
would honour the Laminas marker, with the same precedence: `replace()` applies even
when the base has no such key, and takes effect before the append rule for integer
keys; `remove()` only acts on a key that already exists, and is otherwise discarded
rather than stored.

## Loading configuration from config providers

Components in this ecosystem, and Mezzio and Laminas packages generally, expose a
`ConfigProvider` with an `__invoke()` that returns an array. `Factory::fromProviders()`
merges a list of them into one configuration, so a set of provider classes is as valid
a source as a set of files:

```php
use Webware\ConfigManager\Factory;

$config = Factory::fromProviders([
    Webware\Core\ConfigProvider::class,
    Webware\Log\ConfigProvider::class,
    Webware\Theme\ConfigProvider::class,
]);
```

`returnConfigObject` behaves as it does for the file loaders:

```php
// Returns a Config instance instead of an array.
$config = Factory::fromProviders([Webware\Core\ConfigProvider::class], true);
```

Provider references and the merge follow the same rules as
`Laminas\ConfigAggregator\ConfigAggregator`, so a provider list written for that package
works unchanged:

| Reference | Behaviour |
|---|---|
| `class-string` | Instantiated with **no arguments**, then must be callable |
| callable or object | Used verbatim |
| same reference twice | Throws `InvalidArgumentException` |
| class that does not exist | Throws `InvalidArgumentException` |
| constructor requiring arguments | Throws `InvalidArgumentException`; supply an instance instead |
| returns something other than an array | Throws `InvalidArgumentException` |

Providers are merged in the order given, so a later provider wins, and
[MergeDirective](#steering-the-merge) is honoured in provider output exactly as it is in
file output:

```php
$config = Factory::fromProviders([
    Webware\Core\ConfigProvider::class,
    new class {
        public function __invoke(): array
        {
            return ['cache' => MergeDirective::remove()];
        }
    },
]);
```

## Storing configuration

Sometimes you may want to write configuration to a file. To do this, use the
factory's `toFile()` method:

```php
$config = new Webware\ConfigManager\Config([], true);
$config->settings = [];
$config->settings->myname = 'framework';
$config->settings->date   = '2012-12-12 12:12:12';

//Store the configuration
Webware\ConfigManager\Factory::toFile(__DIR__ . '/config/my.config.php', $config);

//Store an array
$config = [
    'settings' => [
        'myname' => 'framework',
        'data'   => '2012-12-12 12:12:12',
    ],
];

Webware\ConfigManager\Factory::toFile(__DIR__ . '/config/my.config.php', $config);
```
