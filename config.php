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
\Doba\Constant::setConstant('ROOT_PATH', dirname(__DIR__).'/');
\Doba\Constant::setConstant('CACHE_PATH', \Doba\Constant::getConstant('ROOT_PATH').'cache/');
\Doba\Constant::setConstant('TEMP_PATH', \Doba\Constant::getConstant('CACHE_PATH').'temp/');
\Doba\Constant::setConstant('PLUGIN_PATH', \Doba\Constant::getConstant('ROOT_PATH').'common/plugin/');
\Doba\Constant::setConstant('NAMESPACE_FILE', \Doba\Constant::getConstant('ROOT_PATH').'common/config/namespace.php');
\Doba\Constant::setConstant('DEBUG_ERROR', 'error');
// custom function
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
$requestJsonContent = file_get_contents("php://input");
$_POST = ! empty($requestJsonContent) && \Doba\Util::isJson($requestJsonContent) ? \Doba\Util::dJson($requestJsonContent, true) : $_POST;
$GLOBALS['REQUEST_JSON_CONTENT'] = $requestJsonContent;
// load plugins
$_PLUGIN_PATH = \Doba\Constant::getConstant('PLUGIN_PATH');
\Autoloader::me()->addNamespace('Doba\Plugin', $_PLUGIN_PATH);
$GLOBALS['plugin'] = new \Doba\Plugin($_PLUGIN_PATH);
$_NAMESPACE_FILE = \Doba\Constant::getConstant('NAMESPACE_FILE');
if(\Doba\Util::isFile($_NAMESPACE_FILE)) {
    $_NAMESPACE_MAP = require_once($_NAMESPACE_FILE);
    if(is_array($_NAMESPACE_MAP)) {
        foreach($_NAMESPACE_MAP as $namespace=>$path) {
            \Autoloader::me()->addNamespace($namespace, $path);
        }
    }
}