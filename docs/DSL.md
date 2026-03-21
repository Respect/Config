# INI DSL Guide

Respect\Config extends the standard INI format with a DSL for declaring dependency
injection containers. Everything in this guide maps to the PHP API described in the
[Feature Guide](README.md) — if you haven't read that first, start there.

## Loading INI

```php
use Respect\Config\IniLoader;

// From a file
$container = IniLoader::load('services.ini');

// From a string
$container = IniLoader::load('db_host = localhost');

// Onto an existing container
IniLoader::load('overrides.ini', $container);
```

## Simple Values

```ini
app_name   = "My Application"
per_page   = 20
tax_rate   = 0.075
error_mode = PDO::ERRMODE_EXCEPTION
severity   = E_USER_ERROR
```

```php
$container->get('per_page');   // 20 (int)
$container->get('tax_rate');   // 0.075 (float)
$container->get('error_mode'); // 2 (PDO::ERRMODE_EXCEPTION)
```

## Sequences

Comma-separated values inside brackets produce PHP arrays:

```ini
allowed_origins = [http://localhost:8000, http://localhost:3000]
```

```php
// ['http://localhost:8000', 'http://localhost:3000']
$container->get('allowed_origins'); 
```

## Instances

Create instances using INI sections. The section name becomes the container key,
the class name follows after a space:

```ini
[connection PDO]
dsn = "sqlite:app.db"
```

```php
$container->get('connection'); // PDO instance
```

The `instanceof` keyword uses the class name itself as the container key:

```ini
[instanceof PDO]
dsn = "sqlite:app.db"
```

```php
$container->get(PDO::class); // PDO instance, keyed by class name
```

## Constructor Parameters

Parameter names under a section are matched to the class constructor via reflection:

```ini
[connection PDO]
dsn      = "sqlite:app.db"
username = "root"
password = "secret"
```

You can also pass all constructor arguments as a positional list:

```ini
[connection PDO]
__construct = ["sqlite:app.db", "root", "secret"]
```

 1. Set only the parameters you need — unset parameters keep their defaults.
 2. Trailing `null` parameters are automatically stripped so defaults apply.

## References

Use `[name]` as a parameter value to reference another container entry. This is
the INI equivalent of passing an `Instantiator` object as a parameter in PHP:

Given the class:

```php
class Mapper {
    public function __construct(public PDO $db) {}
}
```

Wire it with `[name]` references:

```ini
[connection PDO]
dsn = "sqlite:app.db"

[mapper Mapper]
db = [connection]
```

```php
$container->get('mapper'); // Mapper instance with the PDO connection injected
```

References also work inside sequences:

```ini
admin = admin@example.com
notify = [[admin], ops@example.com]
```

```php
$container->get('notify'); // ['admin@example.com', 'ops@example.com']
```

## Method Calls

Call methods on an instance after construction using `[]` syntax.
Each `methodName[]` entry is one call:

```ini
[connection PDO]
dsn = "sqlite:app.db"
setAttribute[] = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
setAttribute[] = [PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC]
exec[] = "PRAGMA journal_mode=WAL"
exec[] = "PRAGMA foreign_keys=ON"
```

```php
$container->get('connection'); // PDO with attributes set and PRAGMAs executed
```

## Static Factory Methods

Static methods use the same `[]` syntax. They are detected automatically via
reflection:

```ini
[y2k DateTime]
createFromFormat[] = [Y-m-d, 2000-01-01]
```

```php
$container->get('y2k'); // DateTime for 2000-01-01
```

The `Container` will skip the constructor and use the factory, same as the pure
PHP version.

## Properties

Names that don't match a constructor parameter or method are set as public
properties on the instance:

```php
class Request {
    public int $timeout = 10;
    public string $base_url = '';
}
```

```ini
[request Request]
timeout  = 30
base_url = "https://api.example.com"
```

```php
$container->get('request')->base_url; // 'https://api.example.com'
```

The resolution order is: constructor parameter → static method → instance method → property.

## Autowiring

Use the `autowire` modifier to enable automatic type-hint resolution:

```php
class UserRepository {
    public bool $cacheEnabled = false;
    public function __construct(public PDO $db) {}
}
```

```ini
[connection PDO]
dsn = "sqlite:app.db"

[repository autowire UserRepository]
```

```php
$container->get('repository'); // UserRepository with PDO auto-injected
```

The `PDO` instance is injected automatically because the container has an
entry keyed by `PDO`, matching the type hint on the constructor.

Explicit parameters can be mixed in alongside autowiring:

```ini
[repository autowire UserRepository]
cacheEnabled = true
```

## Factory (Fresh Instances)

Use the `new` modifier to create a fresh instance on every access:

```php
class PostController {
    public function __construct(public Mapper $mapper) {}
}
```

```ini
[controller new PostController]
mapper = [mapper]
```

```php
$a = $container->get('controller'); // new PostController
$b = $container->get('controller'); // another new PostController
assert($a !== $b);
```
Dependencies like `[mapper]` are still resolved and cached normally.

## String Interpolation

The `[name]` placeholder syntax can also be used inline within a string to
build composite values. This always produces a string:

```ini
db_driver = mysql
db_host   = localhost
db_name   = myapp
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
```

```php
$container->get('db_dsn'); // "mysql:host=localhost;dbname=myapp"
```

Only root-level simple scalars can be interpolated.

## State Precedence

When loading INI onto a pre-populated container, existing non-Instantiator
values take precedence:

```php
$container = new Container(['env' => 'production']);
IniLoader::load('config.ini', $container);
// If config.ini has env = development, the existing 'production' value wins
```

This allows environment-specific values to be set before loading the INI file,
ensuring they are not overwritten.

***

See also:

- [Home](../README.md)
- [Contributing](../CONTRIBUTING.md)
- [Feature Guide](README.md)
- [Installation](INSTALL.md)
- [License](../LICENSE.md)
