# Webware\\ConfigManager\\Processor

`Webware\ConfigManager\Processor` provides the ability to perform operations on a
`Webware\ConfigManager\Config` object. `Webware\ConfigManager\Processor` is itself an interface that
defining two methods: `process()` and `processValue()`.

webware-configmanager provides the following concrete implementations:

- `Webware\ConfigManager\Processor\Constant`: manage PHP constant values.
- `Webware\ConfigManager\Processor\Filter`: filter the configuration data using `Laminas\Filter`.
- `Webware\ConfigManager\Processor\Queue`: manage a queue of operations to apply to configuration data.
- `Webware\ConfigManager\Processor\Token`: find and replace specific tokens.
- `Webware\ConfigManager\Processor\Translator`: translate configuration values in other languages using `Laminas\I18n\Translator`.

<!-- markdownlint-disable-next-line heading-increment -->
> ### What gets processed?
>
> Typically, you will process configuration _values_. However, there are use
> cases for supplying constant and/or token _keys_; one common one is for
> using class-based constants as keys to avoid using magic "strings":
>
> ```json
> {
>     "Acme\\Compoment::CONFIG_KEY": {}
> }
> ```
>
> As such, as of version 3.1.0, the `Constant` and `Token` processors can
> optionally also process the keys of the `Config` instance provided to them, by
> calling `enableKeyProcessing()` on their instances, or passing a boolean
> `true` value for the fourth constructor argument.

## Webware\\ConfigManager\\Processor\\Constant

### Using Webware\\ConfigManager\\Processor\\Constant

This example illustrates the basic usage of `Webware\ConfigManager\Processor\Constant`:

```php
define ('TEST_CONST', 'bar');

// Provide the second parameter as boolean true to allow modifications:
$config = new Webware\ConfigManager\Config(['foo' => 'TEST_CONST'], true);
$processor = new Webware\ConfigManager\Processor\Constant();

echo $config->foo . ',';
$processor->process($config);
echo $config->foo;
```

This example returns the output: `TEST_CONST,bar`.

As of version 3.1.0, you can also tell the `Constant` processor to process keys:

```php
// At instantiation:
$processor = new Webware\ConfigManager\Processor\Constant(true, '', '', true);

// Or later, via a method call:
$processor->enableKeyProcessing();
```

When enabled, any constant values found in keys will also be replaced.

## Webware\\ConfigManager\\Processor\\Filter

### Using Webware\\ConfigManager\\Processor\\Filter

This example illustrates basic usage of `Webware\ConfigManager\Processor\Filter`:

```php
use Laminas\Filter\StringToUpper;
use Webware\ConfigManager\Processor\Filter as FilterProcessor;
use Webware\ConfigManager\Config;

// Provide the second parameter as boolean true to allow modifications:
$config = new Config(['foo' => 'bar'], true);
$upper = new StringToUpper();

$upperProcessor = new FilterProcessor($upper);

echo $config->foo . ',';
$upperProcessor->process($config);
echo $config->foo;
```

This example returns the output: `bar,BAR`.

## Webware\\ConfigManager\\Processor\\Queue

### Using Webware\\ConfigManager\\Processor\\Queue

This example illustrates basic usage of `Webware\ConfigManager\Processor\Queue`:

```php
use Laminas\Filter\StringToLower;
use Laminas\Filter\StringToUpper;
use Webware\ConfigManager\Processor\Filter as FilterProcessor;
use Webware\ConfigManager\Processor\Queue;
use Webware\ConfigManager\Config;

// Provide the second parameter as boolean true to allow modifications:
$config = new Config(['foo' => 'bar'], true);
$upper  = new StringToUpper();
$lower  = new StringToLower();

$lowerProcessor = new FilterProcessor($lower);
$upperProcessor = new FilterProcessor($upper);

$queue = new Queue();
$queue->insert($upperProcessor);
$queue->insert($lowerProcessor);
$queue->process($config);

echo $config->foo;
```

This example returns the output: `bar`. The filters in the queue are applied in
_FIFO_ (First In, First Out) order .

## Webware\\ConfigManager\\Processor\\Token

### Using Webware\\ConfigManager\\Processor\\Token

This example illustrates basic usage of `Webware\ConfigManager\Processor\Token`:

```php
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Processor\Token as TokenProcessor;

// Provide the second parameter as boolean true to allow modifications:
$config = new Config(['foo' => 'Value is TOKEN'], true);
$processor = new TokenProcessor();

$processor->addToken('TOKEN', 'bar');
echo $config->foo . ',';
$processor->process($config);
echo $config->foo;
```

This example returns the output: `Value is TOKEN,Value is bar`.

As of version 3.1.0, you can also tell the `Token` processor to process keys:

```php
// At instantiation:
$processor = new Webware\ConfigManager\Processor\Token($tokens, '', '', true);

// Or later, via a method call:
$processor->enableKeyProcessing();
```

When enabled, any token values found in keys will also be replaced.

### Using Token processor as a simple environment processor

Token processor can be utilized to populate config values using common
format `%env(ENV_VAR)%` with values from environment by setting Token
processor `$prefix` and `$suffix` parameters to `%env(` and `)%` respectively:

```php
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Processor\Token as TokenProcessor;

putenv('AMQP_PASSWORD=guest');

// Populate list if tokens to replace from environment:
$processor = new TokenProcessor(getenv(), '%env(', ')%');

// Provide the second parameter as boolean true to allow modifications:
$config = new Config([
    'host' => '127.0.0.1',
    'port' => 5672,
    'username' => '%env(AMQP_USER)%',
    'password' => '%env(AMQP_PASSWORD)%',
    'vhost' => '/',
], true);

$processor->process($config);
print_r($config->toArray());
// Array
// (
//     [host] => 127.0.0.1
//     [port] => 5672
//     [username] => %env(AMQP_USER)%
//     [password] => guest
//     [vhost] => /
// )
```

Do note, however, that only values present in environment will be replaced.
This allows multiple fallback processors to be provided as a queue.

## Webware\\ConfigManager\\Processor\\Translator

### Using Webware\\ConfigManager\\Processor\\Translator

This example illustrates basic usage of `Webware\ConfigManager\Processor\Translator`:

```php
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Processor\Translator as TranslatorProcessor;
use Laminas\I18n\Translator\Translator;

// Provide the second parameter as boolean true to allow modifications:
$config = new Config(['animal' => 'dog'], true);

/*
 * The following mapping is used for the translation
 * loader provided to the translator instance:
 *
 * $italian = [
 *     'dog' => 'cane'
 * ];
 */

$translator = new Translator();
// ... configure the translator ...
$processor = new TranslatorProcessor($translator);

echo "English: {$config->animal}, ";
$processor->process($config);
echo "Italian: {$config->animal}";
```

This example returns the output: `English: dog,Italian: cane`.
