<?php

namespace ConsoleTools\Controller;

use Zend\Console\Prompt\Char;
use Zend\Db\Adapter\Adapter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Exception\RuntimeException;
use ConsoleTools\Model\Fixture;

/**
 * Apply fixtures to database
 *
 * @author     V.Leontiev <vadim.leontiev@gmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 * @since      php 7.1 or higher
 * @see        https://github.com/newage/console-tools
 */
class FixtureController extends AbstractActionController
{
    const UPGRADE_KEY = 'up';
    const DOWNGRADE_KEY = 'down';

    /**
     * Folder to fixture files
     * 
     * @var string
     */
    protected $fixtureFolder = null;
    
    /**
     * Apply fixture
     * @TODO Need use transaction
     * 
     * @throws RuntimeException
     */
    public function applyAction()
    {
        /* @var $sm \Zend\ServiceManager\ServiceManager */
        $sm = $this->getServiceLocator();

        $console = $sm->get('console');
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }

        $fixturePath = $this->getFixtureFolder();
        if (!is_dir($fixturePath)) {
            mkdir($fixturePath, 0777);
            $console->writeLine('Don\'t exists folder of fixtures!', Color::RED);
            return;
        }
        
        $adapter     = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $request     = $this->getRequest();
        $silent      = $request->getParam('silent');
        $model       = new Fixture($adapter, $this->getServiceLocator(), $silent);
        $fixtureName = $request->getParam('name', 'all');
        $fixturesFromBase  = $model->applied();
        $fixtureFolderPath = $this->getFixtureFolder();
        $files             = [];

        if (!$silent && !$this->isApplySchema($console, $adapter)) {
            $console->writeLine('Fixture process has been brake', Color::RED);
            return false;
        }

        if ($fixtureName == 'all') {
            $filesDirty = scandir($fixtureFolderPath);
            for ($i=0; $i<count($filesDirty); $i++) {
                if (substr($filesDirty[$i], 0, 1) != '.') {
                    array_push($files, substr($filesDirty[$i], 0, -4));
                }
            }
            $filesFromDrive = array_diff($files, $fixturesFromBase);
            asort($files, SORT_NATURAL);
            $files = array_diff($filesFromDrive, $fixturesFromBase);
            asort($files, SORT_NATURAL);
            $action = self::UPGRADE_KEY;
        } elseif (in_array($fixtureName, $fixturesFromBase)) {
            $key = array_search($fixtureName, $fixturesFromBase);
            $files = array_slice($fixturesFromBase, $key);
            rsort($files, SORT_NATURAL);
            $action = self::DOWNGRADE_KEY;
        } else {
            $console->writeLine('Did not apply the fixture: ' . $fixtureName, Color::RED);
            return false;
        }

        if (!count($files)) {
            $console->writeLine('You have last version of database', Color::GREEN);
            return false;
        }

        foreach ($files as $fixture) {
            $fixturePath = $fixtureFolderPath . DIRECTORY_SEPARATOR . $fixture . '.php';
            $fixtureArray = $this->includeFixture($fixturePath);

            try {
                switch ($action) {
                    case self::DOWNGRADE_KEY:
                        //downgrade action
                        $this->applyFixture(self::DOWNGRADE_KEY, $fixture, $fixtureArray, $silent);
                        break;
                    case self::UPGRADE_KEY:
                        //upgrade action
                        $this->applyFixture(self::UPGRADE_KEY, $fixture, $fixtureArray, $silent);
                        break;
                    default:
                        throw new \Exception('Not set action');
                        break;
                }
                continue;
            } catch (\Exception $err) {
                $console->writeLine('Current fixture failed apply', Color::RED);
                $console->writeLine($err->getMessage(), Color::RED);
                return false;
            }
        }
    }

    /**
     * Show confirm for migration
     * @param string $action
     * @param string $fixture
     * @param array  $fixtureArray
     * @param        $percona
     * @param        $port
     * @param bool   $silent
     * @return bool
     */
    protected function applyFixture($action, $fixture, $fixtureArray, $silent = false)
    {
        /* @var $adapter \Zend\Db\Adapter\Adapter */
        /* @var $console Console */
        $adapter    = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $console    = $this->getServiceLocator()->get('console');
        $model      = new Fixture($adapter, $this->getServiceLocator(), $silent);
        $methodName = $action == self::UPGRADE_KEY ? 'upgrade' : 'downgrade';

        $console->writeLine();
        $console->write('Current fixture: ');
        $console->writeLine($this->getFixtureFolder() . $fixture . '.php', Color::YELLOW);
        $console->writeLine($fixtureArray[$action], Color::BLUE);
        $exist = $model->get(array('fixture' => $fixture));
        $doNotSaveAsExecuted = false;
        if (!empty($exist) && empty($exist['ignored'])) {
            $console->writeLine('This fixture was already executed', Color::YELLOW);
            $doNotSaveAsExecuted = true;
        } elseif (!empty($exist) && !empty($exist['ignored'])) {
            $console->writeLine('This fixture was already pseudo-executed (ignored)', Color::LIGHT_CYAN);
        }

        $answer = !$silent ? Char::prompt('Apply this fixture (Yes / no / ignore forever)? [y/n/i]', 'yni') : 'y';

        switch ($answer) {
            case 'y':
                if ($action == self::UPGRADE_KEY) {
                    $model->$methodName($fixture, $fixtureArray, $ignore = false, $doNotSaveAsExecuted);
                    $console->writeLine('This fixture successful upgraded', Color::GREEN);
                } else {
                    $model->$methodName($fixture, $fixtureArray, $ignore = false);
                    $console->writeLine('This fixture successful downgraded', Color::GREEN);
                }
                break;
            case 'i':
                $model->$methodName($fixture, $fixtureArray, $ignore = true);
                if ($action == self::UPGRADE_KEY) {
                    $console->writeLine('This fixture pseudo-upgraded', Color::LIGHT_CYAN);
                } else {
                    $console->writeLine('This fixture pseudo-downgraded', Color::LIGHT_CYAN);
                }
                break;
            case 'n':
            default:
                $console->writeLine('This fixture discarded', Color::RED);
                return false;
                break;
        }

        return true;
    }

    /**
     * Create new fixture file
     *
     * @throws RuntimeException
     */
    public function createAction()
    {
        $console = $this->getServiceLocator()->get('console');

        if (!$console instanceof Console) {
            throw new \RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        $request = $this->getRequest();
        $shortName = $request->getParam('short_name', '');

        $config = $this->getServiceLocator()->get('Config');
        if (isset($config['console-tools']['fixture_template'])) {
            $dateTemplate = $config['console-tools']['fixture_template'];
        } else {
            $dateTemplate = 'YmdHis';
        }

        $fixturePath = $this->getFixtureFolder();
        if (!is_dir($fixturePath)) {
            mkdir($fixturePath, 0777);
        }

        $date = new \DateTime();
        if ($shortName) {
            $shortName = '_' . $shortName;
        }

        $fixtureName = $date->format($dateTemplate) . $shortName . '.php';

        $fixtureContent = <<<EOD
<?php
        
return [
    'up' => "",
    'down' => ""
];

EOD;

        file_put_contents($fixturePath . $fixtureName, $fixtureContent);

        $console->writeLine('Created fixture file: ' . $fixturePath . $fixtureName, Color::GREEN);
    }

    /**
     * Get migration folder from config file
     *
     * @return string
     */
    protected function getFixtureFolder()
    {
        if ($this->fixtureFolder === null) {
            $config = $this->getServiceLocator()->get('config');
            $this->fixtureFolder = getcwd() . '/' . $config['console-tools']['fixture_folder'] . '/';
        }

        return $this->fixtureFolder;
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

        $console->writeLine('Current DSN: ' . $adapter->getDriver()->getConnection()->getDsn(), Color::GREEN);
        $answer = Char::prompt('The schema is correct (Yes/No)? [y/n]', 'yn');
        if ($answer == 'n') {
            return false;
        }
        return true;
    }

    /**
     * @param $fixturePath
     * @return mixed
     */
    protected function includeFixture($fixturePath)
    {
        $fixtureArray = include $fixturePath;
        return $fixtureArray;
    }
}
