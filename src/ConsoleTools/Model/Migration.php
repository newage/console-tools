<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
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
    protected $adapter = null;
    
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
        $this->adapter = $adapter;
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
            CREATE TABLE IF NOT EXISTS `" . self::TABLE . "`(
                `migration` VARCHAR(20) NOT NULL,
                `up` text NOT NULL,
                `down` text NOT NULL
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        ";

        $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * Get a last migration
     *
     * @param bool $isShow Show sql queries
     * @return string
     */
    public function last($isShow = false)
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->from(self::TABLE);
        $select->columns(array(
            'last' => new \Zend\Db\Sql\Expression('MAX(migration)'),
            'up',
            'down'
        ));
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
        return $results->current();
    }
    
    /**
     * Get applied a migrations name
     * 
     * @return array
     */
    public function applied()
    {
        $migrationFiles = array();
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->from(self::TABLE);
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
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
     *
     * @param string $migrationName
     * @param array $migrationArray
     * @throws \Exception
     */
    public function upgrade($migrationName, array $migrationArray)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        try {
            $connection->beginTransaction();

            $queries = explode(';', $migrationArray['up']);
            foreach ($queries as $query) {
                if (trim($query) == '') {
                    continue;
                }
                $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);
            }

            $sql = new Sql($this->adapter);
            $insert = $sql->insert(self::TABLE);
            $insert->values(array(
                'migration' => $migrationName,
                'up' => $migrationArray['up'],
                'down' => $migrationArray['down']
            ));
            $selectString = $sql->getSqlStringForSqlObject($insert);
            $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);

            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollback();
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * Apply down on the migration
     * And remove migration name from base
     *
     * @param string $migrationName
     * @param array $migrationArray
     * @throws \Exception
     */
    public function downgrade($migrationName, array $migrationArray)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        try {
            $connection->beginTransaction();

            $query = $migrationArray['down'];
            $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);

            $query = 'DELETE FROM `'.self::TABLE.'` WHERE migration = "'.$migrationName.'"';
            $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);

            $connection->commit();
        } catch(\Exception $exception) {
            $connection->rollback();
            throw new \Exception($exception->getMessage());
        }
    }
}
