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

error_reporting(0);
set_error_handler(array('Config', 'errorFunction'));
register_shutdown_function(array('Config', 'shutdownFunction'));
if(! function_exists('url')) {
    function url($a, $plus="") { return Config::url($a, $plus); }
}
if(! function_exists('forward')) {
    function forward($a, $plus="") { return Config::forward($a, $plus); }
}
if(! function_exists('langi18n')) {
    function langi18n($text) { return Config::langi18n($text); }
}
if(! function_exists('genI18nPage')) {
    function genI18nPage($page) { return Config::genI18nPage($page); }
}
// load plugins
$GLOBALS['plugin'] = new \Doba\Plugin(ROOT_PATH.'common/plugin');