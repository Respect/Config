# Feature Guide

## Getting Started

Respect\Config is a lightweight dependency injection container. It implements PSR-11 (`ContainerInterface`) and supports lazy loading, autowiring, and factory patterns.

It also optionally supports container declarations as INI files.

---

Build a container programmatically:
```php
$container = new Container([
    'debug' => true,
    'locale' => 'en_US',
]);
echo $container->get('locale'); // en_US
```

**Alternatively**, you can load configuration from an INI file:
```php
$container = IniLoader::load('services.ini'); // INI path or INI-like string
```

The choice is yours: pure PHP, or declarative INI containers.
All the features below can also be declared in INI files: see the
[INI DSL Guide](DSL.md) for the full syntax reference.

## Simple Values

Plain values are stored directly in the container:

```php
$container = new Container([
    'app_name' => 'My Application',
    'per_page' => 20,
    'tax_rate' => 0.075,
]);
```

Values can also be set after construction:

```php
$container['cache_ttl'] = 3600;
$container->set('debug', false);
```

## Instances

Use `Instantiator` to register a class that will be instantiated on demand.
Constructor parameters are matched by name via reflection:

```php
$container = new Container([
    'connection' => new Instantiator(PDO::class, [
        'dsn' => 'sqlite:app.db', // only the parameters you need, by name
    ]),
]);

$pdo = $container->get('connection');           // PDO, only created when you access
assert($pdo === $container->get('connection')); // accessing again yields same instance
```

## Instance References

Pass an `Instantiator` as a parameter value to wire services together.
It will be resolved automatically when the parent is instantiated:

```php
class Mapper {
    public function __construct(public PDO $db) {}
}

$connection = new Instantiator(PDO::class, ['dsn' => 'sqlite:app.db']);

$container = new Container([
    'connection' => $connection,
    'mapper' => new Instantiator(Mapper::class, [
        'db' => $connection,
    ]),
]);
```

 1. References are resolved lazily: the referenced service is instantiated only
    when the dependent service needs it.
 2. Passing the same `Instantiator` object to multiple consumers ensures
    they all share a single instance.

## Method Calls

Sometimes, you want to call methods to configure the instance you just created.
You can make those into injection parameters as well.

Each inner array represents one call's arguments:

```php
$connection = new Instantiator(PDO::class, ['dsn' => 'sqlite:app.db']);
$connection->setParam('setAttribute', [
    [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION],
    [PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC],
]);
$connection->setParam('exec', [
    ['PRAGMA journal_mode=WAL'],
    ['PRAGMA foreign_keys=ON'],
]);

$container = new Container(['connection' => $connection]);
$container->get('connection'); // PDO with attributes set and PRAGMAs executed
```

## Static Factory Methods

Some instances require you to invoke a factory method, like
`DateTime::createFromFormat` which returns an instance of `DateTime`.

You can also express those as injection parameters, and the Instantiator
will understand they're a factory when loaded:

```php
$y2k = new Instantiator(DateTime::class);
$y2k->setParam('createFromFormat', [['Y-m-d', '2000-01-01']]);

$container = new Container(['y2k' => $y2k]);
$container->get('y2k'); // DateTime for 2000-01-01
```

## Properties

Names that don't match a constructor parameter or method are set as public
properties on the instance:

```php
class Request {
    public int $timeout = 10;
    public string $base_url = '';
}

$container = new Container([
    'request' => new Instantiator(Request::class, [
        'timeout' => 30,
        'base_url' => 'https://api.example.com',
    ]),
]);

$container->get('request')->base_url; // 'https://api.example.com'
```

The resolution order is: constructor parameter -> static method -> instance method -> property.

## Closures

Register closures for full programmatic control. The container is passed as
the argument:

```php
$container = new Container([
    'dsn' => 'sqlite:app.db',
    'connection' => function (Container $c) {
        $pdo = new PDO($c->get('dsn'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    },
]);

$container->get('connection'); // PDO instance
```

## Autowiring

Autowire resolves constructor dependencies automatically by matching type hints
against the container:

```php
class UserRepository {
    public function __construct(public PDO $db) {}
}

$container = new Container([
    PDO::class => new Instantiator(PDO::class, ['dsn' => 'sqlite:app.db']),
    'repository' => new Autowire(UserRepository::class),
]);
```

The `PDO` instance is injected automatically because the container has an
entry keyed by `PDO`, matching the type hint on the constructor.

## Ref: Explicit References

Use `Ref` to inject a specific container entry into an Autowire parameter:
whether to de-ambiguate instances with the same type, or to wire a plain 
value like an array or string:

```php
class UserRepository {
    public function __construct(public PDO $db, public array $ignoredPaths) {}
}

$container = new Container([
    'primary_db' => new Instantiator(PDO::class, ['dsn' => 'sqlite:primary.db']),
    'replica_db' => new Instantiator(PDO::class, ['dsn' => 'sqlite:replica.db']),
    'ignored_paths' => ['/var/log', '/tmp'],
    'rule_namespaces' => ['App\\Validators'],
    'repository' => new Autowire(UserRepository::class, [
        'db' => new Ref('replica_db'),              // Ref to de-ambiguate autowiring
        'ignoredPaths' => new Ref('ignored_paths'), // Ref to auto-wire non-class
    ]),
]);
```

`Ref` can only be used with `Autowire`, not with plain `Instantiator`.

## Factory (Fresh Instances)

By default, instances are cached. Use `Factory` to create a fresh instance on
every access:

```php
class PostController {
    public function __construct(public Mapper $mapper) {}
}

$container = new Container([
    'controller' => new Factory(PostController::class, [
        'mapper' => new Autowire(Mapper::class),
    ]),
]);

$first  = $container->get('controller'); // new PostController
$second = $container->get('controller'); // another new PostController
assert($first !== $second);
```

## Multiple Config Sources

`IniLoader` can load from files, strings, or arrays, and can layer configurations
onto an existing container:

```php
$container = new Container(['env' => 'production']);
IniLoader::load('base.ini', $container);
IniLoader::load('overrides.ini', $container);
```

Existing non-Instantiator values take precedence: if `env` is already set as a 
plain value, a subsequent INI load will not overwrite it.

## Error Handling

The container throws `Respect\Config\NotFoundException` (which implements
`Psr\Container\NotFoundExceptionInterface`) when accessing a missing key:

```php
$container->get('nonexistent'); // throws NotFoundException
```

Use `has()` to check before accessing:

```php
if ($container->has('cache')) {
    $cache = $container->get('cache');
}
```

Other exceptions that may be thrown:

 * `InvalidArgumentException`: from `IniLoader` when the input is not a valid
   INI file, string, or array; or from `Instantiator` when a `Ref` is used
   without `Autowire`.
 * `ReflectionException`: wrapped in `NotFoundException` when an Instantiator
   cannot reflect on the target class (e.g. class not found).

***

See also:

- [Home](../README.md)
- [Contributing](../CONTRIBUTING.md)
- [INI DSL Guide](DSL.md)
- [Installation](INSTALL.md)
- [License](../LICENSE.md)
