# Feature Guide

## Variable Expanding

myconfig.ini:

````ini
db_driver = "mysql"
db_host   = "localhost"
db_name   = "my_database"
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
````

myapp.php:

````php
$c = new Container('myconfig.ini');
echo $c->db_dsn; //mysql:host=localhost;dbname=my_database
````

Note that this works only for variables without ini [sections].

## Sequences

myconfig.ini:

````ini
allowed_users = [foo,bar,baz]
````

myapp.php:

````php
$c = new Container('myconfig.ini');
print_r($c->allowed_users); //array('foo', 'bar', 'baz')
````

Variable expanding also works on sequences. You can express something like this:

myconfig.ini:

````ini
admin_user = foo
allowed_users = [[admin_user],bar,baz]
````

myapp.php:

````php
$c = new Container('myconfig.ini');
print_r($c->allowed_users); //array('foo', 'bar', 'baz')
````

## Constant Evaluation

myconfig.ini:

````ini
error_mode = PDO::ERRMODE_EXCEPTION
````

Needless to say that this would work on sequences too.

## Instances

Using sections

myconfig.ini:

````ini
[something stdClass]
````

myapp.php:

````php
$c = new Container('myconfig.ini');
echo get_class($c->something); //stdClass
````

Using names

myconfig.ini:

````ini
date DateTime = now
````

myapp.php:

````php
$c = new Container('myconfig.ini');
echo get_class($c->something); //DateTime
````

## Callbacks

myconfig.ini:

````ini
db_driver = "mysql"
db_host   = "localhost"
db_name   = "my_database"
db_user   = "my_user"
db_pass   = "my_pass"
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
````


myapp.php:

````php
$c = new Container('myconfig.ini');
$c->connection = function() use($c) {
    return new PDO($c->db_dsn, $c->db_user, $c->db_pass);
};
echo get_class($c->connection); //PDO
````

## Instance Passing

myconfig.ini:

````ini
[myClass DateTime]

[anotherClass stdClass]
myProperty = [myClass]
````

myapp.php:

````php
$c = new Container('myconfig.ini');
echo get_class($c->myClass); //DateTime
echo get_class($c->anotherClass); //stdClass
echo get_class($c->myClass->myProperty); //DateTime
````

Obviously, this works on sequences too.

## Instance Constructor Parameters

Parameter names by reflection:

myconfig.ini:

````ini
[connection PDO]
dsn      = "mysql:host=localhost;dbname=my_database"
username = "my_user"
password = "my_pass"
````

Method call by sequence:

myconfig.ini:

````ini
[connection PDO]
__construct = ["mysql:host=localhost;dbname=my_database", "my_user", "my_pass"]
````

Using Names and Sequences:

myconfig.ini:

````ini
connection PDO = ["mysql:host=localhost;dbname=my_database", "my_user", "my_pass"]
````

## Instantiation by Static Factory Methods

myconfig.ini:

````ini
[y2k DateTime]
createFromFormat[] = [Y-m-d H:i:s, 2000-01-01 00:00:01]
````

## Instance Method Calls

myconfig.ini:

````ini
[connection PDO]
dsn             = "mysql:host=localhost;dbname=my_database"
username        = "my_user"
password        = "my_pass"
setAttribute    = [PDO::ATTR_ERRMODE, PDO::ATTR_EXCEPTION]
exec[]          = "SET NAMES UTF-8"
````

## Instance Properties

myconfig.ini:

````ini
[something stdClass]
foo = "bar"
````

***

See also:

- [Home](../README.md)
- [Contributing](../CONTRIBUTING.md)
- [Installation](INSTALL.md)
- [License](../LICENSE.md)
- [What is a DSL?](DSL.md)
