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
| returns a `Generator` | Each yielded array is merged in order |

A provider that streams arrays, rather than returning one, is merged value by value, so
`PhpFileProvider` can contribute one array per file it matches.

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

### Providers supplied by this package

Two providers replace the ones the Laminas config aggregator shipped, so a provider list
written against that package needs no rewriting:

```php
use Webware\ConfigManager\Provider\ArrayProvider;
use Webware\ConfigManager\Provider\PhpFileProvider;

$config = Factory::fromProviders([
    new ArrayProvider(['config_cache_path' => 'data/cache/config-cache.php']),
    new PhpFileProvider(__DIR__ . '/autoload/{{,*.}global,{,*.}local}.php'),
]);
```

`PhpFileProvider` includes each matching file and merges what it returns, in the order
the pattern produced. That order is the part that matters, and it is reproduced exactly:
`{{,*.}global,{,*.}local}.php` yields `global.php`, then every `*.global.php` in name
order, then `local.php`, then every `*.local.php`. A file that does not return an array is
reported with its path.

## Controlling the order providers are merged

Order decides which provider wins a shared key, and a provider list cannot express that
reliably on its own: `laminas-component-installer` **prepends** every provider it writes to
`config/config.php`, so a package installed last loads first and loses every key it shares
with a package it builds on. The usual workaround is editing the array by hand after each
install.

A provider now declares its own position, which puts the decision where the knowledge is:
the package that must override something.

```php
namespace Webware\Theme;

use Mezzio\LaminasView\ConfigProvider as LaminasViewConfigProvider;
use Webware\ConfigManager\Attribute\LoadAfter;

#[LoadAfter(LaminasViewConfigProvider::class)]
final class ConfigProvider
{
    public function __invoke(): array
    {
        // ...
    }
}
```

Two attributes are available:

| Attribute | Meaning |
|---|---|
| `#[LoadAfter(Target::class, ...)]` | This provider is merged **after** every listed provider |
| `#[LoadBefore(Target::class, ...)]` | This provider is merged **before** every listed provider |

Both accept several targets in one attribute, both may appear more than once, and both may
be used together. They require no interface and no dependency on this package in order to
be written: a PHP attribute is only checked against its class when it is instantiated, so a
`use` import of the attribute is enough, and the declaration is inert wherever this package
is not installed.

Three rules make the behaviour safe to rely on:

- **Providers that declare nothing keep their position.** The sort is stable, so a list
  with no declarations is merged in exactly the order it always was. Enabling this changes
  nothing until a declaration exists.
- **A target that is not installed is ignored.** A package cannot know which of its peers
  the host application uses, so a dangling reference is ordinary rather than an error.
- **A cycle is reported.** If the declarations demand an order that loops, an
  `InvalidArgumentException` names the providers involved. Silently picking a winner is how
  the problem this feature exists to solve would come back.

The order is applied by `Provider\ProviderOrderResolver`, which runs inside
`ConfigProviderResolver::aggregate()` and therefore applies to `Factory::fromProviders()`
and `ConfigAggregator` alike. Nothing has to be wired up.

### Ordering with the laminas aggregator

A consumer still on `Laminas\ConfigAggregator\ConfigAggregator` can apply the same ordering
through the aggregator's `preProcessors` hook, passing the resolver:

```php
$aggregator = new Laminas\ConfigAggregator\ConfigAggregator(
    providers       : $providers,
    cachedConfigFile: null,
    postProcessors  : [],
    preProcessors   : [new Webware\ConfigManager\Provider\ProviderOrderResolver()],
);
```

## Replacing the Laminas config aggregator

`Webware\ConfigManager\ConfigAggregator` takes the same constructor arguments as
`Laminas\ConfigAggregator\ConfigAggregator` and exposes the same `getMergedConfig()`, so
a consumer changes the import and nothing else:

```php
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Provider\ArrayProvider;
use Webware\ConfigManager\Provider\PhpFileProvider;

$aggregator = new ConfigAggregator(
    [
        Webware\Core\ConfigProvider::class,
        Webware\Log\ConfigProvider::class,
        new ArrayProvider($cacheConfig),
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    ],
    $cacheConfig['config_cache_path'],
);

return $aggregator->getMergedConfig();
```

### It is a Config

`ConfigAggregator` extends `Webware\ConfigManager\Config`. A provider returns an array and
a `Config` consumes one, so aggregating is how that array comes to exist rather than a
second container for it. The merged configuration is therefore readable through the same
accessors as any other `Config`:

```php
$aggregator->get('dependencies');
$aggregator['dependencies'];
count($aggregator);
$aggregator->toArray();
```

It is read-only, as a `Config` built for reading is. `getMergedConfig()` is kept because
it is the aggregator's signature; it returns `toArray()`.

The name is unchanged deliberately. `Laminas\ComponentInstaller`'s aggregator injector
matches a literal `new ConfigAggregator(`, so a consumer's `composer require` can keep
registering providers into `config/config.php` after the swap.

### Cache behaviour

The cache rules are the aggregator's, reproduced rather than tidied, because moving here
must not change how an application behaves:

- the cache is **read** whenever the file exists, regardless of the setting. A present
  cache file is authoritative and the providers do not run at all;
- the cache is **written** only when the merged configuration sets
  `ConfigAggregator::ENABLE_CACHE` to `true`;
- a failure to write is swallowed, because an unwritable cache costs performance, not
  correctness.

The consequence of the first rule is that turning the setting off does nothing while a
cache file exists. The file has to be deleted, which is what development mode does.

One deliberate difference: the setting must be the boolean `true`. The aggregator tested
it with `empty()`, so `1` or `'yes'` also enabled the cache; a mis-typed setting silently
changing whether configuration is cached is worse than simply not caching.

`ConfigAggregator::ENABLE_CACHE` and `ConfigAggregator::CACHE_FILEMODE` are declared on
the class, so an application's config files that reference them by that name keep
resolving after the class is swapped.

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

### Merging an update back into a config file

A Config is read only unless it is built with `$allowModifications`, and `merge()` is a
modification, so it refuses a read-only receiver for the same reason assigning does. A
config loaded for reading, including one produced by `ConfigAggregator`, is read only.
To change it, build a mutable copy:

```php
$loaded  = new Webware\ConfigManager\ConfigAggregator($providers, $cacheFile);
$updated = new Webware\ConfigManager\Config($loaded->toArray(), allowModifications: true);

$updated->merge(new Webware\ConfigManager\Config($submitted, allowModifications: true));

Webware\ConfigManager\Factory::toFile($cacheFile, $updated);
```

Only the receiver is changed, so the Config being merged in may be read only. Merging
rebuilds the receiver's children rather than patching them in place, so a Config object
obtained from it before the merge does not observe the change.
