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
                        'route'    => 'migration create',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'create'
                        )
                    )
                ),
                'migration-upgrade' => array(
                    'options' => array(
                        'route'    => 'migration upgrade [<number>]',
                        'defaults' => array(
                            'controller' => 'ConsoleTools\Controller\Migration',
                            'action'     => 'upgrade'
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
