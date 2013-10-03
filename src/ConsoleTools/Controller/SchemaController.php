<?php

namespace ConsoleTools\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Exception\RuntimeException;
use Zend\Db\Adapter\Adapter;


/**
 * Controller for console operations as clean and apply schema
 * Clean current schema and apply sql file
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class SchemaController extends AbstractActionController
{
    
    /**
     * Sql folder destination
     * 
     * @var string
     */
    protected $_schemaFolder = null;
    
    /**
     * Clean current schema and apply sql dump file
     */
    public function cleanAction()
    {
        $request = $this->getRequest();
        $console = $this->getServiceLocator()->get('console');
        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        
        $schemaName = $adapter->getCurrentSchema();
        $dumpFileName = $request->getParam('file');

        if (empty($dumpFileName)) {
            $dumpFileName = 'clean.sql';
        }

        $dumpFileDir = $this->_getSchemaFolder();
        $dumpFilePath = $dumpFileDir . $dumpFileName;
        if (!is_dir($dumpFileDir)) {
            mkdir($dumpFileDir, 0777);
            file_put_contents($dumpFilePath, '#Your default sql dump');
        }
        
        if (!file_exists($dumpFilePath)) {
            $console->writeLine('File don\'t exists: ' . $dumpFileName, Color::RED);
            return false;
        }
        
        $sql = 'DROP SCHEMA `' . $schemaName . '`';
        $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $console->writeLine('Droped current schema: ' . $schemaName, Color::GREEN);
        
        $sql = 'CREATE SCHEMA `' . $schemaName . '`';
        $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $console->writeLine('Created current schema: ' . $schemaName, Color::GREEN);

        $sql = 'USE `' . $schemaName . '`';
        $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        
        $sql = file_get_contents($dumpFilePath);
        $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $console->writeLine('Applied dump file: ' . $dumpFileName, Color::GREEN);
        
    }
    
    /**
     * Get migration folder from config file
     * @return type
     */
    protected function _getSchemaFolder()
    {
        if ($this->_fixtureFolder === null) {
            $config = $this->getServiceLocator()->get('config');
            if (isset($config['console-tools']['folders']['migrations'])) {
                $this->_fixtureFolder = getcwd() . $config['console-tools']['folders']['fixtures'];
            } else {
                $this->_fixtureFolder = getcwd() . '/config/schema/';
            }
        }
        return $this->_fixtureFolder;
    }
}

