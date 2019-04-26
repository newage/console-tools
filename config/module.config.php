<?php

return [
    'controllers' => [
        'invokables' => [
            'ConsoleTools\Controller\Migration' => 'ConsoleTools\Controller\MigrationController',
            'ConsoleTools\Controller\Schema' => 'ConsoleTools\Controller\SchemaController',
            'ConsoleTools\Controller\Fixture' => 'ConsoleTools\Controller\FixtureController',
        ],
    ],
    'console' => [
        'router' => [
            'routes' => [
                'schema-clean' => [
                    'options' => [
                        'route'    => 'schema clean [<file>]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Schema',
                            'action'     => 'clean'
                        ]
                    ]
                ],
                'migration-create' => [
                    'options' => [
                        'route'    => 'migration create [<short_name>]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'create'
                        ]
                    ]
                ],
                'migration-upgrade' => [
                    'options' => [
                        'route'    => 'migration migrate [<number>] [--silent] [--percona] [--port=]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'upgrade'
                        ]
                    ]
                ],
                'migration-execute' => [
                    'options' => [
                        'route'    => 'migration execute <number> (--up|--down) [--percona] [--port=]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'execute'
                        ]
                    ]
                ],
                'migration-last' => [
                    'options' => [
                        'route'    => 'migration last',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'last'
                        ]
                    ]
                ],
                'fixture-create' => [
                    'options' => [
                        'route'    => 'fixture create [<short_name>]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Fixture',
                            'action'     => 'create'
                        ]
                    ]
                ],
                'fixture-apply' => [
                    'options' => [
                        'route'    => 'fixture apply [<name>] [--up|--down] [--silent]',
                        'defaults' => [
                            'controller' => 'ConsoleTools\Controller\Fixture',
                            'action'     => 'apply'
                        ]
                    ]
                ]
            ],
        ],
    ],
];
