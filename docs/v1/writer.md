# Webware\\ConfigManager\\Writer

`Webware\ConfigManager\Writer` provides the ability to write config files from an array,
`Webware\ConfigManager\Config` instance, or any `Traversable` object. `Webware\ConfigManager\Writer`
is itself only an interface that defining the methods `toFile()` and
`toString()`.

We have six writers implementing the interface:

- `Webware\ConfigManager\Writer\Ini`
- `Webware\ConfigManager\Writer\JavaProperties`
- `Webware\ConfigManager\Writer\Json`
- `Webware\ConfigManager\Writer\PhpArray`
- `Webware\ConfigManager\Writer\Xml`
- `Webware\ConfigManager\Writer\Yaml`

## Webware\\ConfigManager\\Writer\\Ini

The INI writer has two modes for rendering with regard to sections. By default, the top-level
configuration is always written into section names. By calling
`$writer->setRenderWithoutSectionsFlags(true);` all options are written into the global namespace of
the INI file and no sections are applied.

`Webware\ConfigManager\Writer\Ini` has an additional option parameter, `nestSeparator`,
which defines with which character the single nodes are separated. The default
is a single dot (`.`), such as is accepted by `Webware\ConfigManager\Reader\Ini` by
default.

When modifying or creating a `Webware\ConfigManager\Config` object, there are several
considerations to keep in mind. To create or modify a value, you simply say set
the parameter of the `Config` object via the parameter accessor (`->`). To
create a section in the root or to create a branch, just create a new array
(`$config->branch = [];`).

### Using Webware\\ConfigManager\\Writer\\Ini

Consider the following code, which creates a configuration structure:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);
$config->production = [];

$config->production->webhost = 'www.example.com';
$config->production->database = [];
$config->production->database->params = [];
$config->production->database->params->host = 'localhost';
$config->production->database->params->username = 'production';
$config->production->database->params->password = 'secret';
$config->production->database->params->dbname = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\Ini();
echo $writer->toString($config);
```

The result of this code is the following INI string:

```ini
[production]
webhost = "www.example.com"
database.params.host = "localhost"
database.params.username = "production"
database.params.password = "secret"
database.params.dbname = "dbproduction"
```

You can use the method `toFile()` to store the INI data to a file instead.

## Webware\\ConfigManager\\Writer\\JavaProperties

- Since 3.2.0

The JavaProperties writer can only write single-dimensional configuration (i.e.,
key/value pairs); this is a limitation of the JavaProperties format.

`Webware\ConfigManager\Writer\JavaProperties` has a single, optional constructor
parameter, `delimiter`, which defines with which character the key/value pairs
are separated. The default is a single colon (`:`), such as is accepted by
`Webware\ConfigManager\Reader\JavaProperties` by default.

### Using Webware\\ConfigManager\\Writer\\JavaProperties

Consider the following code, creating configuration:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);

$config->webhost             = 'www.example.com'; // use object notation
$config['database.host']     = 'localhost';       // or array notation, for complex key names
$config['database.username'] = 'production';
$config['database.password'] = 'secret';
$config['database.dbname']   = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\JavaProperties();
echo $writer->toString($config);
```

The result of this code is the following JavaProperties string:

```properties
webhost:www.example.com
database.host:localhost
database.username:production
database.password:secret
database.dbname:dbproduction
```

You can use the method `toFile()` to store the JavaProperties data to a file instead.

### Using an alternate delimiter

If you want to use an alternate delimiter, such as `=`, pass it to the
constructor:

```php
$writer = new Webware\ConfigManager\Writer\JavaProperties('=');
```

Using the above configuration, we would now receive:

```properties
webhost=www.example.com
database.host=localhost
database.username=production
database.password=secret
database.dbname=dbproduction
```

## Webware\\ConfigManager\\Writer\\Xml

`Webware\ConfigManager\Writer\Xml` can be used to generate an XML string or file.

### Using Webware\\ConfigManager\\Writer\\Xml

Consider the following code, which creates a configuration structure:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);
$config->production = [];

$config->production->webhost = 'www.example.com';
$config->production->database = [];
$config->production->database->params = [];
$config->production->database->params->host = 'localhost';
$config->production->database->params->username = 'production';
$config->production->database->params->password = 'secret';
$config->production->database->params->dbname = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\Xml();
echo $writer->toString($config);
```

The result of this code is the following XML string:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<webware-configmanager>
    <production>
        <webhost>www.example.com</webhost>
        <database>
            <params>
                <host>localhost</host>
                <username>production</username>
                <password>secret</password>
                <dbname>dbproduction</dbname>
            </params>
        </database>
    </production>
</webware-configmanager>
```

You can use the method `toFile()` to store the XML data to a file.

## Webware\\ConfigManager\\Writer\\PhpArray

`Webware\ConfigManager\Writer\PhpArray` can be used to generate a PHP script that
represents and returns configuration.

### Using Webware\\ConfigManager\\Writer\\PhpArray

Consider the following code, which creates a configuration structure:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);
$config->production = [];

$config->production->webhost = 'www.example.com';
$config->production->database = [];
$config->production->database->params = [];
$config->production->database->params->host = 'localhost';
$config->production->database->params->username = 'production';
$config->production->database->params->password = 'secret';
$config->production->database->params->dbname = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\PhpArray();
echo $writer->toString($config);
```

The result of this code is the following PHP script:

```php
<?php
return [
  'production' =>
  [
    'webhost' => 'www.example.com',
    'database' =>
    [
      'params' =>
      [
        'host' => 'localhost',
        'username' => 'production',
        'password' => 'secret',
        'dbname' => 'dbproduction',
      ],
    ],
  ],
];
```

You can use the method `toFile()` to save the PHP script to a file.

## Webware\\ConfigManager\\Writer\\Json

`Webware\ConfigManager\Writer\Json` can be used to generate a JSON representation of
configuration.

### Using Webware\\ConfigManager\\Writer\\Json

Consider the following code, which creates a configuration structure:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);
$config->production = [];

$config->production->webhost = 'www.example.com';
$config->production->database = [];
$config->production->database->params = [];
$config->production->database->params->host = 'localhost';
$config->production->database->params->username = 'production';
$config->production->database->params->password = 'secret';
$config->production->database->params->dbname = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\Json();
echo $writer->toString($config);
```

The result of this code is the following JSON string:

```json
{
  "webhost": "www.example.com",
  "database": {
    "params": {
      "host": "localhost",
      "username": "production",
      "password": "secret",
      "dbname": "dbproduction"
    }
  }
}
```

You can use the method `toFile()` to save the JSON data to a file.

`Webware\ConfigManager\Writer\Json` uses `ext-json` to convert the data to JSON.

## Webware\\ConfigManager\\Writer\\Yaml

`Webware\ConfigManager\Writer\Yaml` can be used to generate a PHP code that returns the YAML
representation of configuration. In order to use the YAML writer, we need to pass a
callback to an external PHP library, or use the
[YAML PECL extension](http://www.php.net/manual/en/book.yaml.php).

### Using Webware\\ConfigManager\\Writer\\Yaml

Consider the following code, which creates a configuration structure using the
YAML PECL extension:

```php
// Create the config object
$config = new Webware\ConfigManager\Config([], true);
$config->production = [];

$config->production->webhost = 'www.example.com';
$config->production->database = [];
$config->production->database->params = [];
$config->production->database->params->host = 'localhost';
$config->production->database->params->username = 'production';
$config->production->database->params->password = 'secret';
$config->production->database->params->dbname = 'dbproduction';

$writer = new Webware\ConfigManager\Writer\Yaml();
echo $writer->toString($config);
```

The result of this code is the following YAML string contains the following value:

```yaml
webhost: www.example.com
database:
    params:
      host:     localhost
      username: production
      password: secret
      dbname:   dbproduction
```

You can use the method `toFile()` to save the YAML data to a file.

If you want to use an external YAML writer library, pass the callback function
that will generate the YAML from the configuration when instantiating the
writer.  For instance, to use the [Spyc](https://github.com/mustangostang/spyc/)
library:

```php
// include the Spyc library
require_once 'path/to/spyc.php';

$writer = new Webware\ConfigManager\Writer\Yaml(['Spyc', 'YAMLDump']);
echo $writer->toString($config);
```
