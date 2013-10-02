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
            'schema clean [<dump>]' => 'Clean current schema and apply dump file \'clean.sql\'',
            array('<dump>'           , 'Name of sql file for apply from folder ./config/sql'),
            
            'Migrations:',
            'migration create'                          => 'Create new migration on format "YmdHis"',
            'migration migrate [<migration>]'           => 'Execute a migration to a specified version or the latest available version.',
            'migration execute <migration> --up|--down' => 'Execute a single migration version up or down manually.',
            'migration last'                            => 'Show last applied migration number',
            array('<migration>'                          , 'Number of migration'),
            array('--up'                                 , 'Execute action up of one migration'),
            array('--down'                               , 'Execute action down of one migration'),
            
            'Fixtures:',
            'fixture apply [<fixture>]' => 'Apply all/name fixture',
            array('<fixture>'            , 'Apply only there fixture'),
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
        return include __DIR__ . '/../../config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }
}
