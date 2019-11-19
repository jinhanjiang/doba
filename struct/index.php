<?
header("Content-Type:text/html; charset=utf-8");
header("Access-Control-Allow-Origin: *");
require(__DIR__.'/common/config/config.php');

$GLOBALS['plugin']->call('web', 'initPath');

// define('DOMAIN_HOST', 'http://xxx.xxx.com');
// define('DOMAIN_PATH', '/');
define("URL", $GLOBALS['plugin']->call('web', 'getURL', array()));

// Set website language
define('LANGUAGE', $GLOBALS['plugin']->call('web', 'getLANG'));

// Response to the request
$GLOBALS['plugin']->call('web', 'response');