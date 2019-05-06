#!/usr/bin/env php
<?php

$basePath = getcwd();

ini_set('user_agent', 'Zend Framework 2 command line tool');

// load autoloader
if (file_exists("$basePath/vendor/autoload.php")) {
    require_once "$basePath/vendor/autoload.php";
} elseif (\Phar::running()) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo 'Error: I cannot find the autoloader of the application.' . PHP_EOL;
    echo "Check if $basePath contains a valid ZF2 application." . PHP_EOL;
    exit(2);
}

$appConfig = [
    'modules' => [
        'ConsoleTools',
    ],
    'module_listener_options' => [
        'config_glob_paths'    => [
          'config/autoload/{,*.}{global,local}.php',
        ],
        'module_paths' => [
            './',
            './vendor'
        ]
    ]
];


Zend\Mvc\Application::init($appConfig)->run();
