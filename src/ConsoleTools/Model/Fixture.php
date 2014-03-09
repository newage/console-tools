<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

/**
 * Apply fixture to database
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class Fixture
{
    
    /**
     * Current db adapter
     * 
     * @var Adapter
     */
    protected $adapter = null;
    
    /**
     * Constructor
     * Create migration table
     * Set current db adapter
     * 
     * @param \Zend\Db\Adapter\Adapter $adapter
     */
    public function __construct($adapter = null)
    {
        $this->adapter = $adapter;
    }
    
    /**
     * 
     * @param string $tableName
     * @param array $data
     * @return bool
     */
    public function insert($tableName, Array $data)
    {
        $sql = new Sql($this->adapter);
        $insert = $sql->insert($tableName);
        $insert->values($data);
        
        $sqlString = $sql->getSqlStringForSqlObject($insert);
        $results = $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        return $results;
    }
}
