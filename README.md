Samples (not implemented yet)
=============================

Case 1: INI based injector configuration
----------------------------------------

Based on http://components.symfony-project.org/dependency-injection/trunk/book/05-Service-Description

### myconfig.ini ###

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
   

### myapp.php ###
    <?php

    use Respect\Config\Container;

    $c = new Container('myconfig.ini');
    $c->mailer; //returns a configured Zend_Mail according to the ini file
    
Case 2: Mixed INI/PHP 
----------------------------------------

Based on http://components.symfony-project.org/dependency-injection/trunk/book/05-Service-Description

### myconfig.ini ###

    db_driver = "mysql"
    db_host   = "localhost"
    db_name   = "my_database"
    db_user   = "root"
    db_pass   = ""
    db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
   

### myapp.php ###
    <?php

    use Respect\Config\Container;

    $c = new Container('myconfig.ini');
    $c->connection = function() use($c) {
        return new PDO($c->db_dsn, $c->db_user, $c->db_pass);
    };

Case 3: INI Configuration using methods and constructors
----------------------------------------

Based on http://components.symfony-project.org/dependency-injection/trunk/book/05-Service-Description

### myconfig.ini ###

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
   

### myapp.php ###
    <?php

    use Respect\Config\Container;

    $c = new Container('myconfig.ini');
    $c->connection; //returns PDO configured with setAttribute() and exec()


