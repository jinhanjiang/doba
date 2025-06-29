<?php

use Doba\Util;

class RpcPlugin extends BasePlugin {

    private $anonymousApis = [];

    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    /**
     * api without authentication
     * @param string|array $api
     */
    public function addAnonymousApis($api) {
        $anonymousApis = $this->anonymousApis;
        if(is_array($api)) {
            $anonymousApis = array_merge($api, $anonymousApis);
        } else {
            $anonymousApis[] = $api;
        }
        $this->anonymousApis = array_unique($anonymousApis); 
    }

    /**
     * $rpc->response(function($httpHeaders, $post){})
     * @param  [type] $rpcRequestAuth Request Authenticator
     * @return [type]
     */
    public function response($rpcRequestAuth = null)
    {
        try{
            list($_REQUEST_DATA, $_RAW_POST, $_TEMP_FILES) = $this->getRequestParams();
            if(empty($_RAW_POST)) {
                Util::echoJson(array());
            }
            $httpHeaders = $this->getHttpHeaders();
            if(! in_array($_RAW_POST['api'], $this->anonymousApis))
            {
                $isAuthSuccess = false;
                if($rpcRequestAuth && is_callable($rpcRequestAuth)) {
                    $isAuthSuccess = call_user_func_array($rpcRequestAuth, array($httpHeaders, $_RAW_POST));
                } else {
                    throw new \Exception('RPC authentication method not defined', 1002);
                }
                if(! $isAuthSuccess) throw new \Exception('API authorization failed', 1001);
            }
            $GLOBALS['_rpc_private_params']['httpHeaders'] = $httpHeaders;
            preg_match('/(\w+)\.(\w+)\.(\w+)$/i', $_RAW_POST['api'], $p);
            $app = strtolower($p[1]); $action = $p[2]; $method = $p[3];
            $objectName = "\\Doba\\Rpc\\".('api' != $app ? ucfirst($app)."\\" : '')."\\". $action.'Rpc';
            $theAction = new $objectName();
            if(! method_exists($theAction, $p[3])) throw new \Exception('Call to undefined method: '.$action.'->'.$method, 1003);
            $data = $theAction->{$method}($_REQUEST_DATA);
            $this->ccc($_TEMP_FILES); Util::echoJson(array('code' => '200', 'message' => 'SUCCESS', 'data' => $data));
        } catch(\Exception $ex) {
            $this->ccc($_TEMP_FILES); Util::echoJson(array('code' => strval($ex->getCode()), 'message'=>$ex->getMessage()));
        }
    }

    /**
     * 获取请求参数
     * @return [type] [description]
     */
    public function getRequestParams() 
    {
        $_REQUEST_DATA = $_RAW_POST = $_TEMP_FILES = [];
        // json request
        if(preg_match('/^application\/json/i', $_SERVER['CONTENT_TYPE'])) {
            $contentJson = file_get_contents('php://input');
            $_RAW_POST = json_decode($contentJson, true);
            $_REQUEST_DATA = $_RAW_POST['edatas'];
        } 
        // Upload file request
        else if(
            preg_match('/^multipart\/form\-data/i', $_SERVER['CONTENT_TYPE']) ||
            preg_match('/^application\/x\-www\-form\-urlencoded/i', $_SERVER['CONTENT_TYPE'])
        ) 
        {
            // 1 Encapsulate post request
            $_RAW_POST = $_POST;
            $_REQUEST_DATA = json_decode($_RAW_POST['edatas'], true);

            $contentJson = json_encode(
                array(
                    'api'=>$_RAW_POST['api'],
                    'edatas'=>$_RAW_POST['edatas'],
                )
            );

            // 2 Handle file upload requests
            if(is_array($_FILES)) foreach($_FILES as $filed=>$fileInfo)
            {
                $filename = '';
                if($fileInfo['size'] > 0)
                {
                    if($fileInfo['size'] > 1024*1024*15) {//Files cannot exceed 15M
                        throw new \Exception('The file size is over 15M', 1005);
                    }
                    $filename = \Doba\Constant::getConstant('TEMP_PATH').Util::uploadFile(\Doba\Constant::getConstant('TEMP_PATH'), $filed);
                }
                $_REQUEST_DATA[$filed] = json_encode(array('filename'=>$filename) + $fileInfo, 256);
                $_TEMP_FILES[] = $filename;
            }
        }

        return [
            $_REQUEST_DATA,
            $_RAW_POST,
            $_TEMP_FILES
        ];
    }

    /**
     * Methods are called before data is returned to the client
     */
    public function ccc($_TEMP_FILES=array()) {// clear call cache
        // 1 If there is an uploaded file, the temporary file will be deleted after the request
        if(is_array($_TEMP_FILES)) foreach($_TEMP_FILES as $tfile) {
            if(Util::isFile($tfile)) @unlink($tfile);
        }
    }

    /**
     * Gets Header request parameters
     */
    public function getHttpHeaders() {
        $httpHeaders = array();
        foreach($_SERVER as $key => $val) {
            if(preg_match('/^HTTP_X_/', $key)) $httpHeaders[preg_replace('/HTTP_X_/', '', $key)] = $val;
        }
        return $httpHeaders;
    }

}