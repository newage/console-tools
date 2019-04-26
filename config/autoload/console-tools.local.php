<?php

return [
    'console-tools' => [
        'enable' => [
            'migrations' => true,
            'fixtures' => true,
            'schema' => true
        ],
        'migration_template' => 'Y-m-d_H-i-s',
        'migration_folder' => 'build/migrations',
        'fixture_template' => 'Y-m-d_H-i-s',
        'fixture_folder' => 'build/fixtures',
    ],
];
