<?php

return array(
    'controllers' => array(
        'invokables' => array(
            'ConsoleTools\Controller\Migration' => 'ConsoleTools\Controller\MigrationController',
            'ConsoleTools\Controller\Schema' => 'ConsoleTools\Controller\SchemaController',
            'ConsoleTools\Controller\Fixture' => 'ConsoleTools\Controller\FixtureController',
        ),
    ),
    'console' => array(
        'router' => array(
            'routes' => array(
                'schema-clean' => array(
                    'options' => array(
                        'route'    => 'schema clean [<file>]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Schema',
                            'action'     => 'clean'
                        )
                    )
                ),
                'migration-create' => array(
                    'options' => array(
                        'route'    => 'migration create [<short_name>]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'create'
                        )
                    )
                ),
                'migration-upgrade' => array(
                    'options' => array(
                        'route'    => 'migration migrate [<number>] [--percona] [--port=]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'upgrade'
                        )
                    )
                ),
                'migration-execute' => array(
                    'options' => array(
                        'route'    => 'migration execute <number> (--up|--down) [--percona] [--port=]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'execute'
                        )
                    )
                ),
                'migration-last' => array(
                    'options' => array(
                        'route'    => 'migration last',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'last'
                        )
                    )
                ),
                'fixture-apply' => array(
                    'options' => array(
                        'route'    => 'fixture apply [<name>]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Fixture',
                            'action'     => 'apply'
                        )
                    )
                )
            ),
        ),
    ),
);
