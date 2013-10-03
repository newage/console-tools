<?php

namespace ConsoleTools\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Exception\RuntimeException;
use ConsoleTools\Model\Migration;

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
    protected $_migrationFolder = null;
    
    const UPGRADE_KEY = 'up';
    const DOWNGRADE_KEY = 'down';

    /**
     * Execute one migration
     * 
     */
    public function executeAction()
    {
        $adapter   = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model     = new Migration($adapter);
        $console   = $this->getServiceLocator()->get('console');
        $request   = $this->getRequest();
        $migration = $request->getParam('number');
        
        $migrationPath = $this->_getMigrationFolder();
        $filePath = $migrationPath . $migration . '.php';
        
        if (!file_exists($filePath)) {
            $console->writeLine('Migration doesn\'t exists: ' . $migration, Color::RED);
        } else {
            $migrationArray = include $filePath;

            if ($request->getParam('up')) {
                $model->upgrade($migration, $migrationArray);
                $console->writeLine('Applied the migration: ' . $migration, Color::GREEN);
            } elseif ($request->getParam('down')) {
                $model->downgrade($migration, $migrationArray);
                $console->writeLine('Downgraded of the migration: ' . $migration, Color::GREEN);
            }
        }
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
        
        $migrationPath = $this->_getMigrationFolder();
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
        
        $console->writeLine('Create new migration file: ' . $migrationName, Color::GREEN);
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
        $migrationFolderPath = $this->_getMigrationFolder();
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
        } else if (in_array($toMigration, $migrationsFromBase)) {
            $key = array_search($toMigration, $migrationsFromBase);
            $files = array_slice($migrationsFromBase, $key);
            rsort($files, SORT_NUMERIC);
            $upgradeAction = self::DOWNGRADE_KEY;
        } else {
            $console->writeLine('Didn\'t apply the migration: ' . $toMigration, Color::RED);
            return;
        }
        
        if (!count($files)) {
            $console->writeLine('You have the last version of database', Color::GREEN);
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
                        $model->downgrade($migration, $migrationArray);
                        $console->writeLine('Downgraded of the migration: ' . $migration, Color::GREEN);
                        break;
                    case self::UPGRADE_KEY:
                        //upgrade action
                        $model->upgrade($migration, $migrationArray);
                        $console->writeLine('Applied the migration: ' . $migration, Color::GREEN);
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
     * @return type
     */
    protected function _getMigrationFolder()
    {
        if ($this->_migrationFolder === null) {
            $config = $this->getServiceLocator()->get('config');
            if (isset($config['console-tools']['folders']['migrations'])) {
                $this->_migrationFolder = getcwd() . $config['console-tools']['folders']['migrations'];
            } else {
                $this->_migrationFolder = getcwd() . '/config/migrations/';
            }
        }
        return $this->_migrationFolder;
    }
}
