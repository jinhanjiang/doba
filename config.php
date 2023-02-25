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
define('LEGAL_PAGE', 1);
(! defined('CACHE_PATH')) && define('CACHE_PATH', ROOT_PATH.'cache/');
(! defined('TEMP_PATH')) && define('TEMP_PATH', CACHE_PATH.'temp/');
(! defined('PLUGIN_PATH')) && define('PLUGIN_PATH', ROOT_PATH.'common/plugin');
(! defined('DEBUG_ERROR')) && define('DEBUG_ERROR', 'error');

if(! function_exists('url')) {
    function url($a, $plus="") { return \Config::me()->url($a, $plus); }
}
if(! function_exists('forward')) {
    function forward($a, $plus="") { return \Config::me()->forward($a, $plus); }
}
if(! function_exists('location')) {
    function location($url) { return \Config::me()->location($url); }
}
if(! function_exists('langi18n')) {
    function langi18n($text, ...$args) { return \Config::me()->langi18n($text, $args); }
}
if(! function_exists('genI18nPage')) {
    function genI18nPage($page) { return \Config::me()->genI18nPage($page); }
}
if(! function_exists('errorFunction')) {
    function errorFunction($errno, $errstr, $errfile, $errline, $errcontext) { 
        \Config::me()->recordSysLog($errno, $errstr, $errfile, $errline, 0);
    }
}
error_reporting(0);
set_error_handler('errorFunction');
register_shutdown_function(function() {\Config::me()->shutdownFunction();});
// define raw request
$_RAW_POST = file_get_contents("php://input");
$_POST = ! empty($_RAW_POST) && \Doba\Util::isJson($_RAW_POST) ? json_decode($_RAW_POST, true) : $_POST;
// load plugins
$_PLUGIN_PATH = ROOT_PATH.'common/plugin';
$GLOBALS['plugin'] = new \Doba\Plugin($_PLUGIN_PATH);
foreach(\Doba\Util::getDirs($_PLUGIN_PATH) as $pluginPathInfo) {
    if(2 != $pluginPathInfo['type']) { continue; }
    $pluginName = $pluginPathInfo['filename'];
    $_PLUGIN_HELPER_PATH = $_PLUGIN_PATH.'/'.$pluginName.'/helper';
    if(is_dir($_PLUGIN_HELPER_PATH)) {
        Autoloader::me()->addNamespace('Doba\Plugin\\'.ucfirst($pluginName).'\Helper', $_PLUGIN_HELPER_PATH);
    }
}
$_NAMESPACE_FILE = $_PLUGIN_PATH.'/namespace.php';
if(\Doba\Util::isFile($_NAMESPACE_FILE)) {
    $_NAMESPACE_MAP = require_once($_NAMESPACE_FILE);
    if(is_array($_NAMESPACE_MAP)) {
        foreach($_NAMESPACE_MAP as $namespace=>$path) {
            Autoloader::me()->addNamespace($namespace, $path);
        }
    }
}