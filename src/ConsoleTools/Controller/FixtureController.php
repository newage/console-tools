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
    const FIXTURE_FOLDER = '/config/fixtures/';
    
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
        
        $fixturePath = getcwd() . self::FIXTURE_FOLDER;
        if (!is_dir($fixturePath)) {
            mkdir($fixturePath, 0777);
            $console->writeLine('Don\'t exists folder fixtures!', Color::RED);
            $console->writeLine('Already create fixtures folder: ' . self::FIXTURE_FOLDER, Color::GREEN);
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
                foreach ($rows as $rowNumber => $row) {
                    try {
                        $model->insert($tableName, $row);
                        $console->writeLine(' - applied row: ' . $rowNumber, Color::GREEN);
                    } catch (\Exception $err) {
                        $console->writeLine(' - error in row: ' . $rowNumber, Color::RED);
                        $console->writeLine($err->getMessage(), Color::RED);
                    }
                }
            }
        }
        
        $console->writeLine('Fixture files applied', Color::GREEN);
    }
}
