Console Tools
===========

Console utility module for Zend Framework 2
For actions of database. Create migrations, apply fixtures and reset schema

##Instal

Add to jour composer.json
```
"require": {
    ...
    "newage/console-tools": "<release-number>"
},
"repositories": [
    {
        "type": "vcs",
        "url":  "git@github.com:newage/console-tools.git"
    }
]
```

Copy zf.php file to home project folder
```
$> cp vendor/neeage/console-tools/zf.php zf.php
```

##Usage

Execute on console:
```
$> php zf.php
```