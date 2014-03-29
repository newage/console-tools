<?php

return array(
    'db' => array(
        'driver'    => 'Pdo_Mysql',
        'database'  => 'test',
        'username'  => 'root',
        'password'  => '123456',
        'hostname'  => '127.0.0.1',
        'driver_options' => array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        )
    ),
    'service_manager' => array(
        'factories' => array(
            'Zend\Db\Adapter\Adapter' => function($serviceManager) {
                $adapterFactory = new Zend\Db\Adapter\AdapterServiceFactory();
                $adapter = $adapterFactory->createService($serviceManager);

                Zend\Db\TableGateway\Feature\GlobalAdapterFeature::setStaticAdapter($adapter);

                return $adapter;
            }
        )
    )
);
