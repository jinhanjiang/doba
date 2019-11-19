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
}

require(ROOT_PATH.'doba/config.php');