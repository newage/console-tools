<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Ddl;

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
        $sql = new Sql($this->adapter);

        try {
            $select = $sql->select(self::TABLE);
            $queryString = $sql->getSqlStringForSqlObject($select);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
        } catch (\Exception $err) {
            $table = new Ddl\CreateTable(self::TABLE);
            $table->addColumn(new Ddl\Column\Integer('id'));
            $table->addColumn(new Ddl\Column\Char('migration', 255));
            $table->addColumn(new Ddl\Column\Text('up'));
            $table->addColumn(new Ddl\Column\Text('down'));
            $table->addColumn(new Ddl\Column\Integer('ignored', false, 0, array('length' => 1)));
            $table->addConstraint(new Ddl\Constraint\PrimaryKey(array('migration'), 'migration'));

            $table->addConstraint(new Ddl\Constraint\PrimaryKey('id'));
            $table->addConstraint(new Ddl\Constraint\UniqueKey(['migration'], 'unique_key'));

            $queryString = $sql->getSqlStringForSqlObject($table);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
        }
    }

    /**
     * Get a last migration
     *
     * @return string
     */
    public function last()
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select(self::TABLE);
        $select->columns(array(
            'last' => new Expression('MAX(id)'),
            'up',
            'down',
            'ignored'
        ));
        
        $selectString = $sql->getSqlStringForSqlObject($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);
        
        return $results->current();
    }
    /**
     * Get a last migration
     *
     * @param bool $isShow Show sql queries
     * @return string
     */
    public function get(array $where = [])
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select(self::TABLE);
        $select->columns(array('*'));
        $select->where($where);

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
     * @param bool $ignore
     * @throws \Exception
     * @internal param bool $ig
     */
    public function upgrade($migrationName, array $migrationArray, $ignore = false)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if (!$ignore) {
                $this->executeQueriesOneByOne($migrationArray['up']);
            }

            $sql = new Sql($this->adapter);
            $insert = $sql->insert(self::TABLE);
            $insert->values(array(
                'migration' => $migrationName,
                'up' => $migrationArray['up'],
                'down' => $migrationArray['down'],
                'ignore' => (int)$ignore,
            ));
            $queryString = $sql->getSqlStringForSqlObject($insert);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);

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
     * @param bool $ignore
     * @throws \Exception
     */
    public function downgrade($migrationName, array $migrationArray, $ignore = false)
    {
        $connection = $this->adapter->getDriver()->getConnection();

        try {
            $connection->beginTransaction();

            if (!$ignore) {
                $this->executeQueriesOneByOne($migrationArray['down']);
            }

            $sql = new Sql($this->adapter);
            $delete = $sql->delete(self::TABLE);
            $delete->where(array('migration' => $migrationName));

            $queryString = $sql->getSqlStringForSqlObject($delete);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);

            $connection->commit();
        } catch(\Exception $exception) {
            $connection->rollback();
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @param string $migration
     */
    protected function executeQueriesOneByOne($migration = '')
    {
        $queries = explode(';', $migration);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);
            }
        }
    }
}
