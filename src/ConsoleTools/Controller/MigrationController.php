<?php

namespace ConsoleTools\Controller;

use Zend\Db\Adapter\Adapter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Exception\RuntimeException;
use ConsoleTools\Model\Migration;
use Zend\Console\Prompt\Char;

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
    const UPGRADE_KEY = 'up';
    const DOWNGRADE_KEY = 'down';

    /**
     * Destination to folder with migration files
     * 
     * @var string
     */
    protected $migrationFolder = null;

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

        if (!file_exists($filePath)) {
            $console->writeLine('Migration does not exists: ' . $filePath, Color::RED);
        } else {
            if (!$this->isApplySchema($console)) {
                false;
            }

            $migrationArray = $this->includeMigration($filePath);

            if ($request->getParam(self::UPGRADE_KEY)) {
                $this->applyMigration(self::UPGRADE_KEY, $migration, $migrationArray);
            } elseif ($request->getParam(self::DOWNGRADE_KEY)) {
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
        $console->writeLine($this->getMigrationFolder() . $migration . '.php', Color::YELLOW);
        $console->writeLine($migrationArray[$action], Color::BLUE);
        $exist = $model->get(array('migration' => $migration));
        $doNotSaveAsExecuted = false;
        if (!empty($exist) && empty($exist['ignored'])) {
            $console->writeLine('This migration was already executed', Color::YELLOW);
            $doNotSaveAsExecuted = true;
        } elseif (!empty($exist) && !empty($exist['ignored'])) {
            $console->writeLine('This migration was already pseudo-executed (ignored)', Color::LIGHT_CYAN);
        }
        $answer = Char::prompt('Apply this migration (Yes / no / ignore forever)? [y/n/i]', 'yni');
        switch ($answer) {
            case 'y':
                if ($action == self::UPGRADE_KEY) {
                    $model->$methodName($migration, $migrationArray, $ignore = false, $doNotSaveAsExecuted);
                    $console->writeLine('This migration successful upgraded', Color::GREEN);
                } else {
                    $model->$methodName($migration, $migrationArray, $ignore = false);
                    $console->writeLine('This migration successful downgraded', Color::GREEN);
                }
            break;
            case 'i':
                $model->$methodName($migration, $migrationArray, $ignore = true);
                if ($action == self::UPGRADE_KEY) {
                    $console->writeLine('This migration pseudo-upgraded', Color::LIGHT_CYAN);
                } else {
                    $console->writeLine('This migration pseudo-downgraded', Color::LIGHT_CYAN);
                }
            break;
            case 'n':
            default:
                $console->writeLine('This migration discarded', Color::RED);
                return false;
            break;
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
        $request = $this->getRequest();
        $short_name = $request->getParam('short_name', '');

        $config = $this->getServiceLocator()->get('Config');
        if (isset($config['console-tools']['migration_template'])) {
            $dateTemplate = $config['console-tools']['migration_template'];
        } else {
            $dateTemplate = 'YmdHis';
        }

        $migrationPath = $this->getMigrationFolder();
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0777);
        }

        $date = new \DateTime();
        if ($short_name) {
            $short_name = '_' . $short_name;
        }
        
        $migrationName = $date->format($dateTemplate) . $short_name . '.php';
        
        $migrationContent = <<<EOD
<?php
        
return [
    'up' => "",
    'down' => ""
];

EOD;
        
        file_put_contents($migrationPath . $migrationName, $migrationContent);
        
        $console->writeLine('Created migration file: ' . $migrationPath . $migrationName, Color::GREEN);
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

        if (!$this->isApplySchema($console, $adapter)) {
            false;
        }

        if ($toMigration == 'all') {
            $filesDirty = scandir($migrationFolderPath);
            for ($i=0; $i<count($filesDirty); $i++) {
                if (substr($filesDirty[$i], 0, 1) != '.') {
                    array_push($files, substr($filesDirty[$i], 0, -4));
                }
            }
            $filesFromDrive = array_diff($files, $migrationsFromBase);
            asort($files, SORT_NATURAL);
            $files = array_diff($filesFromDrive, $migrationsFromBase);
            asort($files, SORT_NATURAL);
            $upgradeAction = self::UPGRADE_KEY;
        } elseif (in_array($toMigration, $migrationsFromBase)) {
            $key = array_search($toMigration, $migrationsFromBase);
            $files = array_slice($migrationsFromBase, $key);
            rsort($files, SORT_NATURAL);
            $upgradeAction = self::DOWNGRADE_KEY;
        } else {
            $console->writeLine('Did not apply the migration: ' . $toMigration, Color::RED);
            return false;
        }
        
        if (!count($files)) {
            $console->writeLine('You have last version of database', Color::GREEN);
            return false;
        }
        
        foreach ($files as $migration) {
            $migrationPath = $migrationFolderPath .
                DIRECTORY_SEPARATOR . $migration . '.php';

            $migrationArray = $this->includeMigration($migrationPath);

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
                $console->writeLine('Current migration failed commit', Color::RED);
                $console->writeLine($err->getMessage(), Color::RED);
                return false;
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

        $request = $this->getRequest();
        $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model = new Migration($adapter);
        $lastMigration = $model->last();

        $migrationName = $this->getMigrationFolder() . $lastMigration->last . '.php';
        if ($request->getParam('show')) {
            $console->writeLine('Last applied the migration: ' . $migrationName, Color::GREEN);
            $console->writeLine('=== Up SQL ===', Color::YELLOW);
            $console->writeLine($lastMigration->up, Color::GREEN);
            $console->writeLine('=== Down SQL ===', Color::YELLOW);
            $console->writeLine($lastMigration->down, Color::GREEN);
        } else {
            $console->writeLine('Last applied the migration: ' . $migrationName, Color::GREEN);
        }
    }

    /**
     * @param Console|null $console
     * @param Adapter|null $adapter
     * @return bool
     */
    protected function isApplySchema(Console $console = null, Adapter $adapter = null)
    {
        if ($console === null) {
            $console = $this->getServiceLocator()->get('console');
        }
        if ($adapter === null) {
            $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        }

        $console->writeLine('Current schema: '.$adapter->getCurrentSchema(), Color::GREEN);
        $answer = Char::prompt('The schema is correct (Yes/No)? [y/n]', 'yn');
        if ($answer == 'n') {
            return false;
        }
        return true;
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
            $this->migrationFolder = getcwd() . '/' . $config['console-tools']['migration_folder'] . '/';
        }

        return $this->migrationFolder;
    }

    /**
     * @param $migrationPath
     * @return mixed
     */
    protected function includeMigration($migrationPath)
    {
        $migrationArray = include $migrationPath;

        return $migrationArray;
    }
}
