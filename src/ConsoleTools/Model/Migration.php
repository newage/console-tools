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
    public function __construct(Adapter $adapter)
    {
        $this->_adapter = $adapter;
        $this->createTable();
    }
    
    /**
     * Generate migration class
     * 
     * @param string $className
     * @return string
     */
    public function generate()
    {
        return <<<EOD
<?php
        
return array(
    'up' => '',
    'down' => ''
);
                
EOD;
    }
    
    /**
     * Create migration table
     * 
     * @return bool
     */
    public function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `".self::TABLE."`(
                `migration` int NOT NULL
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
            INSERT INTO `".self::TABLE."` VALUES(0)
        ";
        
        return $this->_adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }
    
    /**
     * Get current migration
     * 
     * @return int
     */
    public function current()
    {
        $sql = new Sql($this->_adapter);
        $select = $sql->select();
        $select->from(self::TABLE);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->_adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
        return (int)$results->current()->migration;
    }
}
