<?php

namespace ConsoleTools\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSetInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Ddl;
use Zend\Console\Prompt\Confirm;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Console\ColorInterface as Color;

/**
 * Generate class and methods for new migration
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 7.1 or higher
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
     * @var int
     */
    protected $port = false;

    /**
     * @var bool
     */
    protected $silent;

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
     * @param \Zend\Db\Adapter\Adapter $adapter
     * @param ServiceLocatorInterface  $serviceLocator
     * @param                          $percona
     * @param                          $port
     * @param                          $silent
     */
    public function __construct($adapter = null, ServiceLocatorInterface $serviceLocator, $percona = false, $port = null, $silent = false)
    {
        $this->adapter = $adapter;
        $this->percona = $percona;
        $this->port = $port;
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
            $table->addColumn(new Ddl\Column\Integer('id'));
            $table->addColumn(new Ddl\Column\Char('migration', 255));
            $table->addColumn(new Ddl\Column\Text('up'));
            $table->addColumn(new Ddl\Column\Text('down'));
            $table->addColumn(new Ddl\Column\Integer('ignore', false, 0, ['length' => 1]));

            $table->addConstraint(new Ddl\Constraint\PrimaryKey('id'));
            $table->addConstraint(new Ddl\Constraint\UniqueKey(['migration'], 'unique_key'));

            $queryString = $sql->buildSqlString($table);
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
            'ignore'
        ));

        $selectString = $sql->buildSqlString($select);
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

        $selectString = $sql->buildSqlString($select);
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

        $selectString = $sql->buildSqlString($select);
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
            $queryString = $sql->buildSqlString($insert);
            $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
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
        if (!$ignore) {
            $this->executeQueriesOneByOne($migrationArray['down']);
        }

        $sql = new Sql($this->adapter);
        $delete = $sql->delete(self::TABLE);
        $delete->where(array('migration' => $migrationName));

        $queryString = $sql->buildSqlString($delete);
        $this->adapter->query($queryString, Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @param string $migration
     */
    protected function executeQueriesOneByOne($migration = '')
    {
        $console = $this->getServiceLocator()->get('console');
        $config = $this->getServiceLocator()->get('config');
        if (!isset($config['db'])) {
            throw new \RuntimeException('Does not exist `db` config!');
        }
        $dbConfig = $config['db'];

        $queries = explode(';', $migration);
        foreach ($queries as $query) {
            $query = trim($query, " \t\n\r\0");
            $result = (!$this->silent) ? Confirm::prompt($query . PHP_EOL . 'Run this query? [y/n]', 'y', 'n') : true;
            if (!empty($query) && $result) {
                if (!$this->percona) {
                    $request = $this->adapter->query($query, Adapter::QUERY_MODE_EXECUTE);

                    if ($request instanceof ResultSetInterface) {
                        $console->writeLine('Affected rows: ' . $request->count(), Color::BLUE);
                    }
                } else {
                    $this->executeInPerconaTool($query, $dbConfig);
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

            $port = $dbConfig['port'];
            if (!empty($this->port)) {
                $port = $this->port;
            }

            $perconaString = sprintf(
                'pt-online-schema-change --execute --alter-foreign-keys-method=auto --password=%1$s --user=%2$s --alter "%6$s" D=%3$s,t=%5$s,h=%4$s,P=%7$s',
                $dbConfig['password'],
                $dbConfig['username'],
                $dbConfig['database'],
                $dbConfig['hostname'],
                $matches[1],
                $matches[2],
                $port
            );
            $result = shell_exec($perconaString);
            $console->writeLine('Percona response:', Color::BLUE);
            $console->writeLine($result, Color::WHITE);
            return true;
        }
        return false;
    }
}
