<?php

namespace ConsoleTools\Model;

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
     * Generate migration class
     * 
     * @param string $className
     * @return string
     */
    public function generate()
    {
        return <<<EOD
<?php
        
return array(
    'up' => '',
    'down' => ''
);
                
EOD;
    }
    
    public function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `".$this->getMigrationsSchemaTable()."`(
                `migration` int NOT NULL
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
        ";
    }
}
