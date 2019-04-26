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
 * @since      php 7.1 or higher
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
        $docs = [];

        if ($this->config['console-tools']['enable']['schema']) {
            $docs = array_merge($docs, [
                'Schema:',
                'schema clean [<dump>]' => 'Clean current schema and apply dump file \'clean.sql\'',
                ['<dump>'           , 'Name of sql file for apply from folder ./config/sql'],
            ]);
        }

        if ($this->config['console-tools']['enable']['migrations']) {
            $docs = array_merge($docs, [
                'Migrations:',
                'migration create [<short_name>]'           => 'Create new migration on format "Y-m-d_H-i-s" with short name if needed',
                'migration migrate [<migration>] [--silent] [--percona] [--port=<port>]' =>
                    'Execute a migration to a specified version or the latest available version.',
                'migration execute <migration> --up|--down [--percona] [--port=<port>]' =>
                    'Execute a single migration version up or down manually.',
                'migration last [--show]'                     => 'Show last applied migration number',
                ['<migration>'                          , 'Number of migration'],
                ['--up'                                 , 'Execute action up in a one migration'],
                ['--down'                               , 'Execute action down in a one migration'],
                ['--show'                               , 'Show sql code for last migration'],
                ['--percona'                            , 'Executing ALTER TABLE use percona tool'],
                ['--silent'                            , 'Silent executing without escaping'],
                ['<port>'                              , 'Executing ALTER TABLE use percona tool on specific port'],
            ]);
        }

        if ($this->config['console-tools']['enable']['fixtures']) {
            $docs = array_merge($docs, [
                'Fixtures:',
                'fixture create [<short_name>]'           => 'Create new fixture on format "Y-m-d_H-i-s" with short name if needed',
                'fixture apply [<fixture>--up|--down] [--silent]' => 'Apply all/name fixture',
                ['<fixture>'            , 'Apply only there fixture name'],
                ['--up'                                 , 'Execute action up in a one fixture'],
                ['--down'                               , 'Execute action down in a one fixture'],
                ['--silent'                            , 'Silent executing without escaping'],
            ]);
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
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__,
                ],
            ],
        ];
    }
}
