<?php
header("Content-Type:text/html; charset=utf-8");
header("Access-Control-Allow-Origin: *");
require(__DIR__.'/common/config/config.php');

// The default request is processed in the web directory
$GLOBALS['plugin']->call('web', 'initPath');

// \Doba\Constant::setConstant('DOMAIN_HOST', 'http://xxx.xxx.com');
// \Doba\Constant::setConstant('DOMAIN_PATH', '/');
\Doba\Constant::setConstant('URL', $GLOBALS['plugin']->call('web', 'getURL', array()));

// Set website language
\Doba\Constant::setConstant('LANGUAGE', $GLOBALS['plugin']->call('web', 'getLANG'));

// Response to the request
$GLOBALS['plugin']->call('web', 'response');

/*
// You can create a new file such as: mgr.php, which is used to process background management system data.
// Copy the code below, create a new mgr directory, the same structure and web structure , to handle the request passed through mgr.php

<?php
header("Content-Type:text/html; charset=utf-8");
header("Access-Control-Allow-Origin: *");
require(__DIR__.'/common/config/config.php');

$GLOBALS['plugin']->call('web', 'initPath', array('project'=>'mgr', 'lang'=>'en'));

\Doba\Constant::setConstant('URL', $GLOBALS['plugin']->call('web', 'getURL', array('rootFile'=>'mgr.php')));

// Set website language
\Doba\Constant::setConstant('LANGUAGE', $GLOBALS['plugin']->call('web', 'getLANG'));

// Response to the request
$GLOBALS['plugin']->call('web', 'response');

*/