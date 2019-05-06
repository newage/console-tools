<?php

namespace ConsoleTools\Model;

use Zend\Console\Prompt\Confirm;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\Metadata;
use Zend\Db\ResultSet\ResultSetInterface;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Ddl;
use Zend\Db\TableGateway\Feature\GlobalAdapterFeature;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Console\ColorInterface as Color;

/**
 * Apply fixture to database
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 7.1 or higher
 * @see        https://github.com/newage/console-tools
 */
class Fixture
{
    use ServiceLocatorAwareTrait;

    /**
     * Fixtures table name of database
     *
     * @var string
     */
    const TABLE = 'fixtures';

    /**
     * Current db adapter
     * 
     * @var Adapter
     */
    protected $adapter = null;

    /**
     * @var bool
     */
    protected $silent;

    /**
     * Constructor
     * Create migration table
     * Set current db adapter
     * @param \Zend\Db\Adapter\Adapter $adapter
     * @param ServiceLocatorInterface  $serviceLocator
     * @param bool                     $silent
     */
    public function __construct($adapter = null, ServiceLocatorInterface $serviceLocator, $silent = false)
    {
        $this->adapter = $adapter;
        $this->createTable();
        $this->setServiceLocator($serviceLocator);
        $this->silent = $silent;
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
            $queryString = $sql->buildSqlString($select);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
        } catch (\Exception $err) {
            $table = new Ddl\CreateTable(self::TABLE);
            $table->addColumn((new Ddl\Column\Integer('id', false))->setOption('autoincrement', true));
            $table->addColumn(new Ddl\Column\Char('fixture', 255));
            $table->addColumn(new Ddl\Column\Text('up', null, true));
            $table->addColumn(new Ddl\Column\Text('down', null, true));
            $table->addColumn(new Ddl\Column\Integer('ignore', false, 0));

            $table->addConstraint(new Ddl\Constraint\PrimaryKey('id'));
            $table->addConstraint(new Ddl\Constraint\UniqueKey('fixture', 'unique_key'));

            $queryString = $sql->buildSqlString($table);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
        }
    }

    /**
     * 
     * @param string $tableName
     * @param array $data
     * @return bool
     */
//    public function insert($tableName, Array $data)
//    {
//        $connection = $this->adapter->getDriver()->getConnection();
//        $connection->beginTransaction();
//
//        try {
//            $sql = new Sql($this->adapter);
//            $insert = $sql->insert($tableName);
//            $insert->values($data);
//
//            $sqlString = $sql->buildSqlString($insert);
//            $results = $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
//
//            $connection->commit();
//        } catch (\Exception $exception) {
//            $connection->rollback();
//            throw new \Exception($exception->getMessage());
//        }
//
//        return $results;
//    }

    /**
     * Apply up on the migration
     * And insert migration name to base
     * @param string $fixtureName
     * @param array  $fixtureArray
     * @param bool   $ignoreFixture
     * @param bool   $doNotSaveAsExecuted
     * @throws \Exception
     * @internal param bool $ig
     */
    public function upgrade($fixtureName, array $fixtureArray, $ignoreFixture = false, $doNotSaveAsExecuted = false)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if (!$ignoreFixture) {
                $this->executeQueriesOneByOne($fixtureArray['up']);
            }
            if (!$doNotSaveAsExecuted) {
                $sql = new Sql($this->adapter);
                $insert = $sql->insert(self::TABLE);
                $insert->values(array(
                    'fixture' => $fixtureName,
                    'up' => $fixtureArray['up'],
                    'down' => $fixtureArray['down'],
                    'ignore' => (int)$ignoreFixture,
                ));
                $queryString = $sql->buildSqlString($insert);
                $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
            }
            $connection->commit();
        } catch (\Exception $err) {
            $connection->rollback();
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * Apply down on the migration
     * And remove migration name from base
     * @param string $fixtureName
     * @param array  $fixtureArray
     * @param bool   $ignore
     * @throws \Exception
     */
    public function downgrade($fixtureName, array $fixtureArray, $ignore = false)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if (!$ignore) {
                $this->executeQueriesOneByOne($fixtureArray['down']);
            }

            $sql = new Sql($this->adapter);
            $delete = $sql->delete(self::TABLE);
            $delete->where(array('fixture' => $fixtureName));

            $queryString = $sql->buildSqlString($delete);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);

            $connection->commit();
        } catch (\Exception $err) {
            $connection->rollback();
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * @param string $fixture
     */
    protected function executeQueriesOneByOne($fixture = '')
    {
        $console = $this->getServiceLocator()->get('console');

        $queries = explode(';', $fixture);
        foreach ($queries as $query) {
            $query = trim($query, " \t\n\r\0");
            if (empty($query)) {
                continue;
            }

            $result = (!$this->silent) ? Confirm::prompt($query . PHP_EOL . 'Run this query? [y/n]', 'y', 'n') : true;
            if (!empty($query) && $result) {
                $request = $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);

                if ($request instanceof ResultSetInterface) {
                    $console->writeLine('Affected rows: ' . $request->count(), Color::BLUE);
                }
            }
        }
    }

    /**
     * Get applied fixture's names
     *
     * @return array
     */
    public function applied()
    {
        $fixtureFiles = array();
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->from(self::TABLE);

        $selectString = $sql->buildSqlString($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);

        if ($results->count() > 0) {
            foreach ($results as $fixture) {
                $fixtureFiles[] = $fixture->fixture;
            }
        }

        return $fixtureFiles;
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

        $selectString = $sql->buildSqlString($select);
        $results = $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE);

        return $results->current();
    }
}
