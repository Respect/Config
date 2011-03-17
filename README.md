Respect\Config
==============

A powerful, small, deadly simple configurator and dependency injection container made to be easy. Featuring:

* INI configuration files only. Simpler than YAML, XML or JSON (see samples below).
* Uses the same native, fast parser that powers php.ini.
* Extends the INI configuration with a custom dialect.
* Implements lazy loading for object instances.
* Can describe any array, instance or variable.

Feature Guide
=============

Variable Expanding (Implemented)
------------------

myconfig.ini:

    db_driver = "mysql"
    db_host   = "localhost"
    db_name   = "my_database"
    db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"

myapp.php:

    $c = new Container('myconfig.ini:');
    echo $c->db_dsn; //mysql:host=localhost;dbname=my_database

Sequences  (Implemented)
---------

myconfig.ini:

    allowed_users = [foo,bar,baz]

myapp.php:

    $c = new Container('myconfig.ini:');
    print_r($c->allowed_users); //array('foo', 'bar', 'baz')

Variable expanding also works on sequences. You can express something like this:

myconfig.ini:

    admin_user = foo
    allowed_users = [[admin_user],bar,baz]

myapp.php:

    $c = new Container('myconfig.ini:');
    print_r($c->allowed_users); //array('foo', 'bar', 'baz')

Constant Evaluation  (Implemented)
-------------------

myconfig.ini:

    error_mode = PDO::ERRMODE_EXCEPTION

myapp.php:

    $c = new Container('myconfig.ini:');
    print_r($c->error_mode); //2, the value of the constant

Needless to say that this would work on sequences too.

Instances 
---------

Using sections (Implemented):

myconfig.ini:

    [something stdClass]

myapp.php:

    $c = new Container('myconfig.ini:');
    echo get_class($c->something); //stdClass

Using names (Partially Implemented):

myconfig.ini:

    date DateTime = 

myapp.php:

    $c = new Container('myconfig.ini:');
    echo get_class($c->something); //DateTime

Callbacks (Implemented):
---------

myconfig.ini:

    db_driver = "mysql"
    db_host   = "localhost"
    db_name   = "my_database"
    db_user   = "my_user"
    db_pass   = "my_pass"
    db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"


myapp.php:

    $c = new Container('myconfig.ini:');
    $c->connection = function() use($c) {
        return new PDO($c->db_dsn, $c->db_user, $c->db_pass);
    };
    echo get_class($c->connection); //PDO

Instance Passing (Implemented):
----------------

myconfig.ini:

    [myClass DateTime]

    [anotherClass stdClass]
    myProperty = [myClass]

myapp.php:

    $c = new Container('myconfig.ini:');
    echo get_class($c->myClass); //DateTime
    echo get_class($c->anotherClass); //stdClass
    echo get_class($c->myClass->myProperty); //DateTime

Obviously, this works on sequences too.

Instance Constructor Parameters (Not Implemented Yet)
-------------------------------

Parameter names by reflection:

myconfig.ini:

    [connection PDO]
    dsn      = "mysql:host=localhost;dbname=my_database"
    username = "my_user"
    password = "my_pass"

Method call by sequence:

myconfig.ini:

    [connection PDO]
    __construct = ["mysql:host=localhost;dbname=my_database", "my_user", "my_pass"]

Using Names and Sequences:

myconfig.ini:

    connection PDO = ["mysql:host=localhost;dbname=my_database", "my_user", "my_pass"]

Instantiation by Factory Methods (Not Implemented Yet)
--------------------------------

myconfig.ini:

    [em Doctrine\ORM\EntityManager]
    create = [[connectionOptions], [config]]

Instance Method Calls (Not Implemented Yet)
---------------------

myconfig.ini:

    [connection PDO]
    dsn      = "mysql:host=localhost;dbname=my_database"
    username = "my_user"
    password = "my_pass"
    setAttribute = [PDO::ATTR_ERRMODE, PDO::ATTR_EXCEPTION]


Instance Properties (Not Implemented Yet)
-------------------

myconfig.ini:

    [something stdClass]
    foo = "bar"

Use Cases
=========

Case 1: INI based injector configuration (not working yet)
----------------------------------------

Based on http://components.symfony-project.org/dependency-injection/trunk/book/05-Service-Description

myconfig.ini:

    smtp_host = "smtp.gmail.com"

    [smtp_config]
    auth     = "login"
    username = "foo"
    password = "bar"
    ssl      = "ssl"
    port     = "495"

    [smtpTransport Zend_Mail_Transport_Smtp]
    host   = [smtp_host]
    config = [smtp_config]

    [mailer Zend_Mail]
    setDefaultTransport = [smtpTransport]
   

myapp.php:

    $c = new Container('myconfig.ini:');
    $c->mailer; //returns a configured Zend_Mail according to the ini file
    
Case 2: Mixed INI/PHP (this already works!)
----------------------------------------

myconfig.ini:

    db_driver = "mysql"
    db_host   = "localhost"
    db_name   = "my_database"
    db_user   = "root"
    db_pass   = ""
    db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
   

myapp.php:

    $c = new Container('myconfig.ini:');
    $c->connection = function() use($c) {
        return new PDO($c->db_dsn, $c->db_user, $c->db_pass);
    };

Case 3: INI Configuration using methods and constructors (not working yet)
----------------------------------------

myconfig.ini:

    db_driver = "mysql"
    db_host   = "localhost"
    db_name   = "my_database"
    db_user   = "root"
    db_pass   = ""
    db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
    
    [connection PDO]
    dsn            = [db_dsn]   ; this is a constructor parameter
    username       = [db_user]  ; this is a constructor parameter
    password       = [db_pass]  ; this is a constructor parameter
    setAttribute[] = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTIONS] 
    setAttribute[] = [PDO::ATTR_CASE, PDO::CASE_NORMAL]
    exec[]         = "SET NAMES UTF-8"
    exec[]         = "SET CHARSET UTF-8"
   

myapp.php:

    $c = new Container('myconfig.ini:');
    $c->connection; //returns PDO configured with setAttribute() and exec()


