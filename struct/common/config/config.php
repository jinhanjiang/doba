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
        $dbConfigs = isset($GLOBALS['CONSTANT_DB_CONFIGS']) ? $GLOBALS['CONSTANT_DB_CONFIGS'] : [
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
        ];
        \Doba\Constant::setConstant('DB_CONFIGS', $dbConfigs);
        parent::setDbConfigs();
    }

}
\Doba\Constant::setConstant('ROOT_PATH', ROOT_PATH);
require(ROOT_PATH.'doba/config.php');