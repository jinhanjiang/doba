<?php
define('ROOT_PATH', dirname(dirname(__DIR__)).'/');

// varconfig.php may not exist. This file is usually used to redefine constants that are already in the program,
// such as svn paths, redis configuration.
is_file($varconfig = __DIR__.'/varconfig.php') && require($varconfig);
require(ROOT_PATH.'doba/autoload.php');
// Here you can override Autoloader::autoload()
Autoloader::autoload();

// Here you can override the parent class method
class Config extends \Doba\BaseConfig {

    // Rewrite the parent class to set the dabagase method
    public function setDbConfigs() {
        if(defined('DB_CONFIGS')) {
            $this->dbConfigs = json_decode(DB_CONFIGS, true);
        } else {
            $this->dbConfigs = array(
                'db1'=>array(
                    'dbHost'=>'192.168.0.1',
                    'dbName'=>'testdb1',
                    'dbUser'=>'root',
                    'dbPass'=>'123456',
                ),
                'db2'=>array(
                    'dbHost'=>'192.168.0.2',
                    'dbName'=>'testdb2',
                    'dbUser'=>'root',
                    'dbPass'=>'123456',
                ),
            );
        }
    }

}

require(ROOT_PATH.'doba/config.php');