<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

/**
 * Generate class and methods for new migration
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class Migration
{
    
    /**
     * Current db adapter
     * 
     * @var Adapter
     */
    protected $_adapter = null;
    
    /**
     * Migration table name of database
     * 
     * @var string
     */
    const TABLE = 'migrations';
    
    /**
     * Constructor
     * Create migration table
     * Set current db adapter
     * 
     * @param \Zend\Db\Adapter\Adapter $adapter
     */
    public function __construct($adapter = null)
    {
        $this->_adapter = $adapter;
        $this->createTable();
    }
    
    /**
     * Create a migration table
     * 
     * @return bool
     */
    public function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `".self::TABLE."`(
                `migration` int NOT NULL
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        ";

        $this->_adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }
    
    /**
     * Get a last migration
     * 
     * @return string
     */
    public function last()
    {
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from(self::TABLE);
        $select->columns(array('last' => new \Zend\Db\Sql\Expression('MAX(migration)')));
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
        return $results->current()->last;
    }
    
    /**
     * Get applied a migrations name
     * 
     * @return array
     */
    public function applied()
    {
        $migrationFiles = array();
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from(self::TABLE);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
        if ($results->count() > 0) {
            foreach ($results as $migration) {
                $migrationFiles[] = $migration->migration;
            }
        }
            
        return $migrationFiles;
    }
    
    /**
     * Apply up on the migration
     * And insert migration name to base
     * @TODO need use transaction
     * 
     * @param string $migrationName
     * @paran array $migrationArray
     * @return bool
     * @throw Exception
     */
    public function upgrade($migrationName, $migrationArray)
    {
        $query = $migrationArray['up'];
        $this->_adapter->query($query, Adapter::QUERY_MODE_EXECUTE);
        
        $query = 'INSERT `'.self::TABLE.'` VALUE("'.$migrationName.'")';
        $this->_adapter->query($query, Adapter::QUERY_MODE_EXECUTE);
    }
    
    public function downgrade($migration)
    {
        
    }
}
