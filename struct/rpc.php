<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST");
require(__DIR__.'/common/config/config.php');

$GLOBALS['plugin']->call('web', 'initPath');
// Set website language
define('LANGUAGE', $GLOBALS['plugin']->call('web', 'getLANG'));

// function rpcRequestAuth($authInfo, $contentJson)
// {
//     $account = \Doba\Dao\AccountDAO::me()->getBy(array('id'=>$authInfo['API_KEY']));
//     return (empty($account) || strtoupper(md5($contentJson.$account->apiSecret)) != strtoupper($authInfo['API_TOKEN'])) ? false : true;
// }
$GLOBALS['plugin']->call('rpc', 'response');