Respect\Config [![Build Status](https://travis-ci.org/Respect/Config.png?branch=develop)](https://travis-ci.org/Respect/Config)
==============

[![Total Downloads](https://poser.pugx.org/respect/config/downloads.png)](https://packagist.org/packages/respect/config)
[![Latest Stable Version](https://poser.pugx.org/respect/config/v/stable.png)](https://packagist.org/packages/respect/config)


A powerful, small, deadly simple configurator and dependency injection container made to be easy. Featuring:

* INI configuration files only. Simpler than YAML, XML or JSON (see samples below).
* Uses the same native, fast parser that powers php.ini.
* Extends the INI configuration with a custom dialect.
* Implements lazy loading for object instances.

Installation
------------

Packages available on [PEAR](http://respect.li/pear) and [Composer](http://packagist.org/packages/Respect/Config). Autoloading is [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) compatible.

Autoloading
-----------

You can set up Respect\Config for autoloading. We recommend using the
SplClassLoader. Here's a nice sample:

````php
set_include_path('/my/library' . PATH_SEPARATOR . get_include_path());
require_once 'SplClassLoader.php';
$respectLoader = new \SplClassLoader();
$respectLoader->register();
````


Running Tests
-------------

We didn't created our tests just for us to apreciate. To run them,
you'll need phpunit 3.5 or greater. Then, just chdir into the `/tests` folder
we distribute and run them like this:

````bash
cd /my/RespectConfig/tests
phpunit .
````

You can tweak the phpunit.xml under that `/tests` folder to your needs.

Feature Guide
=============

Variable Expanding
------------------

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

Sequences
---------

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

Constant Evaluation
-------------------

myconfig.ini:

````ini
error_mode = PDO::ERRMODE_EXCEPTION
````

Needless to say that this would work on sequences too.

Instances
---------

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

Callbacks
---------

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

Instance Passing
----------------

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

Instance Constructor Parameters
-------------------------------

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

Instantiation by Static Factory Methods
---------------------------------------

myconfig.ini:

````ini
[y2k DateTime]
createFromFormat[] = [Y-m-d H:i:s, 2000-01-01 00:00:01]
````

Instance Method Calls
---------------------

myconfig.ini:

````ini
[connection PDO]
dsn             = "mysql:host=localhost;dbname=my_database"
username        = "my_user"
password        = "my_pass"
setAttribute    = [PDO::ATTR_ERRMODE, PDO::ATTR_EXCEPTION]
exec[]          = "SET NAMES UTF-8"
````

Instance Properties
-------------------

myconfig.ini:

````ini
[something stdClass]
foo = "bar"
````

Known Limitations
=================

* Variable expanding only works for unsectioned keys.
* Empty strings, zeros and null are not properly treated yet.
* Constructors with non-null default parameter values may not work properly yet.
* The only way to use magic methods is to call them explicitly with __call
* Circular references haven't been tested and may not work
* May not work properly with the following conditions:
    * Static and normal methods with the same names within the same class
    * Methods and properties with same names within the same class
    * Methods and properties with same names as constructor parameters

Luckly, most of these limitations are known to be PHP bad practices. Keep up the
good work and you'll never face them.

License Information
===================

Copyright (c) 2009-2012, Alexandre Gomes Gaigalas.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Alexandre Gomes Gaigalas nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

