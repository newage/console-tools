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
     * Folder to schema
     * Folder must be there for use completion-bash
     */
    const FOLDER_SCHEMA = '/data/schema/';

    /**
     * Sql folder destination
     * 
     * @var string
     */
    protected $schemaFolder = null;
    
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

        $dumpFileDir = $this->getSchemaFolder();
        $dumpFilePath = $dumpFileDir . $dumpFileName;
        if (!is_dir($dumpFileDir)) {
            mkdir($dumpFileDir, 0777);
            file_put_contents($dumpFilePath, '#Your default sql dump');
            $console->writeLine('Created folder for clean schema: ' . $dumpFileName, Color::GREEN);
            return false;
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
     * @return string
     */
    protected function getSchemaFolder()
    {
        if ($this->schemaFolder === null) {
            $this->schemaFolder = getcwd() . self::FOLDER_SCHEMA;
        }
        return $this->schemaFolder;
    }
}

