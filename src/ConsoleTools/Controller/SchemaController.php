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
    const SQL_FOLDER = '/config/sql/';
    
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

        $dumpFilePath = getcwd() . self::SQL_FOLDER . $dumpFileName;
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
}

