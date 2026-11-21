# Theory of Operation

Configuration data are made accessible to `Webware\ConfigManager\Config`'s constructor via
an associative array, which may be multi-dimensional so data can be organized
from general to specific. Concrete adapter classes adapt configuration data from
storage to produce the associative array for `Webware\ConfigManager\Config`'s constructor.
If needed, user scripts may provide such arrays directly to
`Webware\ConfigManager\Config`'s constructor, without using a reader class.

Each value in the configuration data array becomes a property of the
`Webware\ConfigManager\Config` object.  The key is used as the property name. If a value
is itself an array, then the resulting object property is created as a new
`Webware\ConfigManager\Config` object, loaded with the array data. This occurs
recursively, such that a hierarchy of configuration data may be created with any
number of levels.

<!-- markdownlint-disable-next-line heading-increment -->
> ### Extending `Webware\ConfigManager\Config` class
>
> If you decide to extend `Webware\ConfigManager\Config` class, each property (subnode)
> becomes the same type as the parent class. For example:
>
> ```php
> class ExtendedConfig extends Webware\ConfigManager\Config
> {
> }
>
> $config = new ExtendedConfig(['node' => ['key' => 'value']]);
>
> echo get_class($config->node);
> // the result of above is ExtendedConfig not Webware\ConfigManager\Config!
> ```
>
> This casting occurs in the constructor, which uses the construct `new
> static()` for any array subvalues it encounters when traversing the provided
> array. As such, we DO NOT RECOMMEND extending the constructor; instead, create
> a [named constructor](http://verraes.net/2014/06/named-constructors-in-php/):
>
> ```php
> class ExtendedConfig extends Webware\ConfigManager\Config
> {
>     public static function createWithDefaults()
>     {
>         return new self([ /* ... */ ]);
>     }
> }
> ```

`Webware\ConfigManager\Config` implements the [Countable](http://php.net/manual/en/class.countable.php)
and [Iterator](http://php.net/manual/en/class.iterator.php) interfaces in order
to facilitate simple access to configuration data. Thus, `Webware\ConfigManager\Config`
objects support the [count()](http://php.net/count) function and PHP constructs
such as [foreach](http://php.net/foreach).

By default, configuration data made available through `Webware\ConfigManager\Config` are
read-only, and an assignment (e.g., `$config->database->host = 'example.com';`)
results in an exception. This default behavior may be overridden through the
constructor, allowing modification of data values.  Also, when modifications are
allowed, `Webware\ConfigManager\Config` supports unsetting of values (e.g.,
`unset($config->database->host)`). The `isReadOnly()` method can be used to
determine if modifications to a given `Webware\ConfigManager\Config` object are allowed,
and the `setReadOnly()` method can be used to stop any further modifications to
a `Webware\ConfigManager\Config` object that was created allowing modifications.

> ### Modifying Config does not save changes
>
> It is important not to confuse such in-memory modifications with saving
> configuration data out to specific storage media. Tools for creating and
> modifying configuration data for various storage media are out of scope with
> respect to `Webware\ConfigManager\Config`. Third-party open source solutions are readily
> available for the purpose of creating and modifying configuration data for
> various storage media.

If you have two `Webware\ConfigManager\Config` objects, you can merge them into a single
object using the `merge()` function. For example, given `$config` and
`$localConfig`, you can merge data from `$localConfig` to `$config` using
`$config->merge($localConfig);`. The items in `$localConfig` will override any
items with the same name in `$config`.

> ### Merging requires modifications
>
> The `Webware\ConfigManager\Config` object that is performing the merge must have been
> constructed to allow modifications, by passing `TRUE` as the second parameter
> of the constructor. The `setReadOnly()` method can then be used to prevent any
> further modifications after the merge is complete.
