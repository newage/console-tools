<?php

namespace ConsoleTools;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface as Console;

/**
 * 
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class Module
{
    
    /**
     * Create documentaion for console usage that module
     * 
     */
    public function getConsoleUsage(Console $console)
    {
        return array(
            'Schema:',
            'schema clean [dump file]' => 'Clean current schema and apply dump file \'clean.sql\'',
            array('[dump file]', '[Optional] name of sql file for apply from folder ./config/sql'),
            
            'Migrations:',
            'migration create'  => 'Create new migration',
            'migration upgrade [migration number]' => 'Upgrade to last migration',
            array('[migration number]', '[Optional] Number of migration for upgrade or downgrade'),
            'migration current' => 'Show current applying migration number',
        );
    }
    
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
