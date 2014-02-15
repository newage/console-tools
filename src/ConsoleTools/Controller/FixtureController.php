<?php

namespace ConsoleTools\Controller;

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
 * @since      php 5.3 or higher
 * @see        https://github.com/newage/console-tools
 */
class FixtureController extends AbstractActionController
{
    
    /**
     * Folder to fixture files
     * 
     * @var string
     */
    protected $_fixtureFolder = null;
    
    /**
     * Apply fixture
     * @TODO Need use transaction
     * 
     * @throws RuntimeException
     */
    public function applyAction()
    {
        $console = $this->getServiceLocator()->get('console');
        if (!$console instanceof Console) {
            throw new RuntimeException('Cannot obtain console adapter. Are we running in a console?');
        }
        
        $fixturePath = $this->_getFixtureFolder();
        if (!is_dir($fixturePath)) {
            mkdir($fixturePath, 0777);
            $console->writeLine('Don\'t exists folder of fixtures!', Color::RED);
            return;
        }
        
        $adapter     = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $model       = new Fixture($adapter);
        $request     = $this->getRequest();
        $fixtureName = $request->getParam('name', 'all');
        
        if ($fixtureName == 'all') {
            $fixtureFiles = scandir($fixturePath);
            unset($fixtureFiles[0]);
            unset($fixtureFiles[1]);
        } else {
            $fixtureFiles = array($fixtureName . '.php');
        }
        
        foreach ($fixtureFiles as $fixtureFile) {
            $fixture = include $fixturePath . $fixtureFile;
            
            foreach ($fixture as $tableName => $rows) {
                $console->writeLine('Will apply fixture of the table "'.$tableName.'" from file: '.$fixtureFile, Color::GREEN);
                $values = isset($rows['values']) ? $rows['values'] : $rows;

                foreach ($values as $rowNumber => $row) {
                    try {
                        $row = isset($rows['keys']) ? array_combine($rows['keys'], $row) : $row;
                        
                        $model->insert($tableName, $row);
                    } catch (\Exception $err) {
                        $console->writeLine(' - error in row: ' . $rowNumber, Color::RED);
                        $console->writeLine($err->getMessage(), Color::RED);
                    }
                }
            }
        }
        
        $console->writeLine('Fixture files applied', Color::GREEN);
    }
    
    /**
     * Get migration folder from config file
     * @return type
     */
    protected function _getFixtureFolder()
    {
        if ($this->_fixtureFolder === null) {
            $config = $this->getServiceLocator()->get('config');
            if (isset($config['console-tools']['folders']['migrations'])) {
                $this->_fixtureFolder = getcwd() . $config['console-tools']['folders']['fixtures'];
            } else {
                $this->_fixtureFolder = getcwd() . '/config/fixtures/';
            }
        }
        return $this->_fixtureFolder;
    }
}
