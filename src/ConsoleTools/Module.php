<?php

namespace ConsoleTools;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;

/**
 * 
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ConsoleUsageProviderInterface
{
    
    protected $config = null;

    public function onBootstrap(MvcEvent $e)
    {
        $this->config        = $e->getApplication()->getServiceManager()->get('config');
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    /**
     * Create documentation for console usage that module
     * 
     */
    public function getConsoleUsage(Console $console)
    {
        $docs = array();
        
        if ($this->config['console-tools']['enable']['schema']) {
            $docs = array_merge($docs, array(
                'Schema:',
                'schema clean [<dump>]' => 'Clean current schema and apply dump file \'clean.sql\'',
                array('<dump>'           , 'Name of sql file for apply from folder ./config/sql'),
            ));
        }
        
        if ($this->config['console-tools']['enable']['migrations']) {
            $docs = array_merge($docs, array(
                'Migrations:',
                'migration create [<short_name>]'           => 'Create new migration on format "YmdHis" with short name if needed',
                'migration migrate [<migration>]'           =>
                    'Execute a migration to a specified version or the latest available version.',
                'migration execute <migration> --up|--down' =>
                    'Execute a single migration version up or down manually.',
                'migration last --show'                     => 'Show last applied migration number',
                array('<migration>'                          , 'Number of migration'),
                array('--up'                                 , 'Execute action up of one migration'),
                array('--down'                               , 'Execute action down of one migration'),
                array('--show'                               , 'Show sql code for last migration'),
            ));
        }
        
        if ($this->config['console-tools']['enable']['fixtures']) {
            $docs = array_merge($docs, array(
                'Fixtures:',
                'fixture apply [<fixture>]' => 'Apply all/name fixture',
                array('<fixture>'            , 'Apply only there fixture'),
            ));
        }
        
        return $docs;
    }

    public function getConfig()
    {
        $config = include __DIR__ . '/../../config/module.config.php';

        /* Remove schema routes */
        if ($this->config['console-tools']['enable']['schema'] === false) {
            unset($config['console']['router']['routes']['schema-clean']);
        }

        /* Remove migrations routes */
        if ($this->config['console-tools']['enable']['migrations'] === false) {
            unset($config['console']['router']['routes']['migration-create']);
            unset($config['console']['router']['routes']['migration-upgrade']);
            unset($config['console']['router']['routes']['migration-execute']);
            unset($config['console']['router']['routes']['migration-last']);
        }

        /* Remove fixtures routes */
        if ($this->config['console-tools']['enable']['fixtures'] === false) {
            unset($config['console']['router']['routes']['fixture-apply']);
        }

        return $config;
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
