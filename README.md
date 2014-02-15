Console Tools
===========

Console utility module for Zend Framework 2
For actions of database. Create migrations, apply fixtures and reset schema

##Install

Add to composer.json
```
"require": {
    "newage/console-tools": "<release-number>"
},
"repositories": [
    {
        "type": "vcs",
        "url":  "git@github.com:newage/console-tools.git"
    }
]
```

Add to ./config/application.config.php
```
'modules' => array(
    'ConsoleTools'
),
```

Copy config file console-tools.local.php.dist to ./config/autoload/console-tools.local.php
```
$> cp ./vendor/newage/console-tools/config/console-tools.local.php.dist ./config/autoload/console-tools.local.php
```

##Usage

Start console tools
```
$> php ./public/index.php
```