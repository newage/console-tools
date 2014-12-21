Console Tools
===========

Database migrations module for Zend Framework 2
For actions of database. Create migrations, apply fixtures and reset schema

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/newage/console-tools/badges/quality-score.png?s=16582f9d14bafbdb8c33887da89da3b15ed4dd3e)](https://scrutinizer-ci.com/g/newage/console-tools/)
[![Travis CI](https://travis-ci.org/newage/console-tools.svg)](https://travis-ci.org/newage/console-tools)
[![Coverage Status](https://img.shields.io/coveralls/newage/console-tools.svg)](https://coveralls.io/r/newage/console-tools)

##Install

Add to `composer.json`
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

Add to `./config/application.config.php`
```
'modules' => array(
    'ConsoleTools'
),
```

Copy config file `console-tools.local.php.dist` to `./config/autoload/console-tools.local.php`
```
$> cp ./vendor/newage/console-tools/config/console-tools.local.php.dist ./config/autoload/console-tools.local.php
```

##Usage

Start console tools
```
$> php ./public/index.php
```

##Bash Completion

For completion commands in bash in Ubuntu.
Need install `bash-completion` package if not it.
* Copy file `console-tool-completion.bash` to your `HOME DIR`
* Modify `bashrc`. Add string `source ~/console-tool-completion.bash`. Reload terminal.
