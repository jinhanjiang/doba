<?php
/**
 * This file is part of doba.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    jinhanjiang<jinhanjiang@foxmail.com>
 * @copyright jinhanjiang<jinhanjiang@foxmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
class Autoloader 
{
    // Automatic registration framework class method
    public static function autoload() 
    {
        // Take the original loading method
        $oldFunctions = spl_autoload_functions();
        // Unload one by one
        if ($oldFunctions){
            foreach ($oldFunctions as $f) {
                spl_autoload_unregister($f);
            }
        }
        // Register this framework for autoloading
        spl_autoload_register(function($class){
            if(isset($GLOBALS['CLASS_MAPS'][$class])) return true;
            else
            {
                $classInfo = explode('\\', $class);
                $className = array_pop($classInfo);
                $classInfo = array_map('strtolower', $classInfo);

                $deep = count($classInfo); $classFile = '';

                if(1 == $deep && 'doba' == $classInfo[0]) {
                    $classFile = ROOT_PATH."doba/core/{$className}.php";
                } 
                else
                {
                    if('doba' == $classInfo[0] && 'rpc' == $classInfo[1]) {
                        $classFile = ROOT_PATH."common/rpc/{$className}.php";
                    }
                    else if('doba' == $classInfo[0] && 'dao' == $classInfo[1]) {
                        $classFile = ROOT_PATH."common/libs/dao/{$className}.php";
                        if(! is_file($classFile)) {
                            $classFile = ROOT_PATH."common/libs/dao/{$classInfo[2]}/{$className}.php";    
                        }
                    }
                    else if('doba' == $classInfo[0] && 'map' == $classInfo[1]) {
                        $classFile = ROOT_PATH."common/libs/map/{$className}.php";
                        if(! is_file($classFile)) {
                            $classFile = ROOT_PATH."common/libs/map/{$classInfo[2]}/{$className}.php";    
                        }
                    }
                }
                if($classFile) {
                    include_once($classFile);
                    $GLOBALS['CLASS_MAPS'][$class] = $classFile;
                }
                return true;
            }
        });
        // Put the original autoload function back
        if ($oldFunctions){
            foreach ($oldFunctions as $f) {
                spl_autoload_register($f);
            }
        }
    }
}