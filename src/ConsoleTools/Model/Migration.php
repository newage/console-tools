<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Ddl;
use Zend\Console\Prompt\Confirm;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\ServiceManager\ServiceLocatorInterface;

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
    use ServiceLocatorAwareTrait;

    /**
     * Current db adapter
     *
     * @var Adapter
     */
    protected $adapter = null;

    /**
     * @var bool
     */
    protected $percona = false;

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
    public function __construct($adapter = null, ServiceLocatorInterface $serviceLocator, $percona)
    {
        $this->adapter = $adapter;
        $this->percona = $percona;
        $this->createTable();
        $this->setServiceLocator($serviceLocator);
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
     * @param bool $ignoreMigration
     * @param bool $doNotSaveAsExecuted
     * @throws \Exception
     * @internal param bool $ig
     */
    public function upgrade($migrationName, array $migrationArray, $ignoreMigration = false, $doNotSaveAsExecuted = false)
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if (!$ignoreMigration) {
                $this->executeQueriesOneByOne($migrationArray['up']);
            }
            if (!$doNotSaveAsExecuted) {
                $sql = new Sql($this->adapter);
                $insert = $sql->insert(self::TABLE);
                $insert->values(array(
                    'migration' => $migrationName,
                    'up' => $migrationArray['up'],
                    'down' => $migrationArray['down'],
                    'ignore' => (int)$ignoreMigration,
                ));
                $queryString = $sql->getSqlStringForSqlObject($insert);
                $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
            }

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
        $config = $this->getServiceLocator()->get('config');
        if (!isset($config['db'])) {
            throw new \RuntimeException('Does nto exist `db` config!');
        }
        $dbConfig = $config['db'];

        $queries = explode(';' . PHP_EOL, $migration);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (Confirm::prompt($query . PHP_EOL . 'Run this query? [y/n]', 'y', 'n')) {
                    if ($this->executeInPerconaTool($query, $dbConfig) === false) {
                        $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);
                    }
                } elseif (Confirm::prompt('Break execution and ROLLBACK? [y/n]', 'y', 'n')) {
                    $connection = $this->adapter->getDriver()->getConnection();
                    $connection->rollback();
                    exit;
                }
            }
        }
    }

    protected function executeInPerconaTool($query, $dbConfig): bool
    {
        if (stristr($query, 'ALTER TABLE') && $this->percona) {
            $cleanQuery = str_replace(['`', '\''], '', $query);
            preg_match('~ALTER TABLE\s([\w\d\_]+)\s(.*);?~smi', $cleanQuery, $matches);
            if (!isset($matches[1]) || !isset($matches[2])) {
                return false;
            }
            $console = $this->getServiceLocator()->get('console');

            $perconaString = sprintf(
                'pt-online-schema-change --execute --alter-foreign-keys-method=auto --password=%1$s --user=%2$s --alter "%6$s" D=%3$s,t=%5$s,h=%4$s',
                $dbConfig['password'],
                $dbConfig['username'],
                $dbConfig['database'],
                $dbConfig['hostname'],
                $matches[1],
                $matches[2]
            );
            $result = shell_exec($perconaString);
            $console->writeLine('Percona response:', Color::BLUE);
            $console->writeLine($result, Color::WHITE);
            return true;
        }
        return false;
    }
}
