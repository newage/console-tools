<?php

namespace ConsoleTools\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Exception\RuntimeException;
use ConsoleTools\Model\Migration;
use Zend\Console\Prompt\Confirm;

/**
 * Controller for console operations as create, upgrate and current migrations
 * 
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class MigrationController extends AbstractActionController
{
    
    /**
     * Destination to folder with migration files
     * 
     * @var string
     */
    protected $migrationFolder = null;
    
    const UPGRADE_KEY = 'up';
    const DOWNGRADE_KEY = 'down';

    /**
     * Execute one migration
     * 
     */
    public function executeAction()
    {
        /* @var $console Console */
        $console   = $this->getServiceLocator()->get('console');
        $request   = $this->getRequest();
        $migration = $request->getParam('number');
        
        $migrationPath = $this->getMigrationFolder();
        $filePath = $migrationPath . $migration . '.php';
$this->applyMigration(self::UPGRADE_KEY, $migration, array('up'=>'SELECT * FROM users;'));
        if (!file_exists($filePath)) {
            $console->writeLine('Migration does not exists: ' . $migration, Color::RED);
        } else {
            $migrationArray = include $filePath;

            if ($request->getParam('up')) {
                $this->applyMigration(self::UPGRADE_KEY, $migration, $migrationArray);
            } elseif ($request->getParam('down')) {
                $this->applyMigration(self::DOWNGRADE_KEY, $migration, $migrationArray);
            }
        }
    }

    /**
     * Show confirm for migration
     *
     * @param string $action
     * @param string $migration
     * @param array $migrationArray
     * @return bool
     */
    protected function applyMigration($action, $migration, $migrationArray)
    {
        /* @var $adapter \Zend\Db\Adapter\Adapter */
        /* @var $console Console */
        $adapter    = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $console    = $this->getServiceLocator()->get('console');
        $model      = new Migration($adapter);
        $methodName = $action == self::UPGRADE_KEY ? 'upgrade' : 'downgrade';

        $console->writeLine();
        $console->write('Current migration: ');
        $console->writeLine($migration, Color::GREEN);
        $console->writeLine($migrationArray[$action], Color::BLUE);

        if (Confirm::prompt('Need apply this migration? [y/n]', 'y', 'n')) {
            $model->$methodName($migration, $migrationArray);
            if ($action == self::UPGRADE_KEY) {
                $console->writeLine('This migration successful upgraded', Color::GREEN);
            } else {
                $console->writeLine('This migration successful downgraded', Color::GREEN);
            }
        } else {
            $console->writeLine('This migration discarded', Color::RED);
            return false;
        }

        return true;
    }

    /**
     * Create new migration file
     * 
     * @throws RuntimeException
     */
    public function createAction()
    {
        $console = $this->getServiceLocator()->get('console');
        
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        
        $migrationPath = $this->getMigrationFolder();
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0777);
        }
        
        $timeZone = new \DateTimeZone('Europe/Kiev');
        $date = new \DateTime('NOW');
        $date->setTimezone($timeZone);
        
        $migrationName = $date->format('YmdHis') . '.php';
        
        $migrationContent = <<<EOD
<?php
        
return array(
    'up' => '',
    'down' => ''
);
                
EOD;
        
        file_put_contents($migrationPath . $migrationName, $migrationContent);
        
        $console->writeLine('Created new migration file: ' . $migrationName, Color::GREEN);
    }
    
    /**
     * Upgrade/downgrade to migration
     * 
     * @throws RuntimeException
     */
    public function upgradeAction()
    {
        $console = $this->getServiceLocator()->get('console');
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        
        $adapter             = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model               = new Migration($adapter);
        $request             = $this->getRequest();
        $toMigration         = $request->getParam('number', 'all');
        $migrationsFromBase  = $model->applied();
        $migrationFolderPath = $this->getMigrationFolder();
        $files               = array();
        
        if ($toMigration == 'all') {
            $filesDirty = scandir($migrationFolderPath);
            for ($i=0; $i<count($filesDirty); $i++) {
                if (substr($filesDirty[$i], 0, 1) != '.') {
                    array_push($files, substr($filesDirty[$i], 0, -4));
                }
            }
            $filesFromDrive = array_diff($files, $migrationsFromBase);
            asort($files, SORT_NUMERIC);
        
            $files = array_diff($filesFromDrive, $migrationsFromBase);
            asort($files, SORT_NUMERIC);
            $upgradeAction = self::UPGRADE_KEY;
        } elseif (in_array($toMigration, $migrationsFromBase)) {
            $key = array_search($toMigration, $migrationsFromBase);
            $files = array_slice($migrationsFromBase, $key);
            rsort($files, SORT_NUMERIC);
            $upgradeAction = self::DOWNGRADE_KEY;
        } else {
            $console->writeLine('Did not apply the migration: ' . $toMigration, Color::RED);
            return;
        }
        
        if (!count($files)) {
            $console->writeLine('You have last version database\'s', Color::GREEN);
            return;
        }
        
        foreach ($files as $migration) {
            $migrationPath = $migrationFolderPath .
                DIRECTORY_SEPARATOR . $migration . '.php';
            
            $migrationArray = include $migrationPath;

            try {
                switch ($upgradeAction) {
                    case self::DOWNGRADE_KEY:
                        //downgrade action
                        $this->applyMigration(self::DOWNGRADE_KEY, $migration, $migrationArray);
                        break;
                    case self::UPGRADE_KEY:
                        //upgrade action
                        $this->applyMigration(self::UPGRADE_KEY, $migration, $migrationArray);
                        break;
                    default:
                        throw new \Exception('Not set action');
                        break;
                }
                continue;
            } catch (\Exception $err) {
                $console->writeLine('Commit failed of the migration: ' . $migration, Color::RED);
                $console->writeLine($err->getMessage(), Color::RED);
                return;
            }
        }
    }
    
    /**
     * Show last applied migration number
     * 
     */
    public function lastAction()
    {
        $console = $this->getServiceLocator()->get('console');
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        
        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model = new Migration($adapter);
        $lastMigration = $model->last();
        
        $console->writeLine('Last applied the migration: ' . $lastMigration, Color::GREEN);
    }
    
    /**
     * Get migration folder from config file
     *
     * @return string
     */
    protected function getMigrationFolder()
    {
        if ($this->migrationFolder === null) {
            $config = $this->getServiceLocator()->get('config');
            if (isset($config['console-tools']['folders']['migrations'])) {
                $this->migrationFolder = getcwd() . $config['console-tools']['folders']['migrations'];
            } else {
                $this->migrationFolder = getcwd() . '/config/migrations/';
            }
        }
        return $this->migrationFolder;
    }
}
