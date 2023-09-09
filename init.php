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
define('ROOT_PATH', dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
// The main role of the current file is to quickly generate the framework and some configurations.
try{
    switch($_REQUEST['a'])
    {
        case 'init': // Initialize the project structure
            if(! is_dir(ROOT_PATH.'common')) {
                copydirRecurse(ROOT_PATH.'doba/struct', ROOT_PATH);
                echo 'ok';
            } else {
                echo 'Project structure may already exist';
            }
            break;

        case 'lang':
            loadConfig();
            if(is_dir($viewPages = ROOT_PATH.'web/views/'))
            {
                $dirs = \Doba\Util::getDirs($viewPages);
            }
            echo 'To be developed';
            break;

        default: // Refresh table structure
            loadConfig();
            $dbConfigs = \Config::me()->getDbConfigs(); $dbcnt = 0;
            if(isset($dbConfigs['default']) && 'mysql' == $dbConfigs['default']['db']) {
                \Doba\RefreshDaoMap::initDaoMap('', $dbConfigs['default']); $dbcnt ++;
            }
            else
            {
                foreach($dbConfigs as $project=>$dbConfig) {
                    if('mysql' != $dbConfig['db'] 
                        || (isset($dbConfig['noDaoMap']) && $dbConfig['noDaoMap'] === true)) {
                        continue;
                    }
                    \Doba\RefreshDaoMap::initDaoMap($project, $dbConfig); $dbcnt ++;
                }
            }
            echo $dbcnt > 0 ? 'Refresh the table structure successfully' : 'Did not find mysql database connection';
            break;
    }
} catch(Exception $ex) {
    echo $ex->getMessage();
}
function loadConfig() {
    $configFile = ROOT_PATH.'common/config/config.php';
    if(! is_file($configFile)) {
        echo 'Please perform the initialization framework first.'; exit;
    }
    require($configFile);
    if(! \Config::me()->isDevEnvironment()) {
        echo 'Current operations can only be performed in a development environment'; exit;
    }
}
/**
 * Bulk copy directory (including all files in subdirectories)
 */
function copydirRecurse($source, $destination, $child=true) 
{
    if(! is_dir($source)){ 
        echo("Error:the $source is not a direction!");  return false; 
    } 
    if(! is_dir($destination))  mkdir($destination, 0777);  
    $handle = dir($source); 
    while($entry = $handle->read()) 
    { 
        if(($entry != ".")&&($entry != ".."))
        {
            if(is_dir($source."/".$entry)) {
                if($child) copydirRecurse($source."/".$entry, $destination."/".$entry,$child); 
            }  else {
                if(! is_file($destination."/".$entry)) { 
                    copy($source."/".$entry, $destination."/".$entry); 
                }
            } 
        } 
    } 
    return true; 
}