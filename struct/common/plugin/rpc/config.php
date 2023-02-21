<?php
use Doba\Util;

class RpcPlugin extends BasePlugin {
    
    const RPC_PATH = ROOT_PATH.'common/rpc/';

    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    public function response()
    {
        global $API_CALL_CONFIG, $ALLOW_ANONYMOUS_CALL_API;
        try{
            $_TEMP_FILES = array(); $contentJson = ''; $httpHeaders = $this->getHttpHeaders();
            $API_CALL_CONFIG = is_array($API_CALL_CONFIG) ? $API_CALL_CONFIG : array();
            // The API that does not require authentication permissions is written in the following array
            $ALLOW_ANONYMOUS_CALL_API = is_array($ALLOW_ANONYMOUS_CALL_API) ? $ALLOW_ANONYMOUS_CALL_API : array();
            // ---------------------- 一 Receiving request -----------------------------------------------
            // json request
            if(preg_match('/^application\/json/i', $_SERVER['CONTENT_TYPE'])) {
                $contentJson = file_get_contents('php://input');
                $_FILTER = json_decode($contentJson, true);
                $_REQUEST_DATA = $_FILTER['edatas'];
            } 
            // Upload file request
            else if(
                preg_match('/^multipart\/form\-data/i', $_SERVER['CONTENT_TYPE']) ||
                preg_match('/^application\/x\-www\-form\-urlencoded/i', $_SERVER['CONTENT_TYPE'])
            ) 
            {
                // 1 Encapsulate post request
                $_FILTER = $_POST;
                $_REQUEST_DATA = json_decode($_FILTER['edatas'], true);

                $contentJson = json_encode(
                    array(
                        'api'=>$_FILTER['api'],
                        'edatas'=>$_FILTER['edatas'],
                        'timestamp'=>$_FILTER['timestamp'],
                        'version'=>$_FILTER['version'],
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
                        $filename = TEMP_PATH.Util::uploadFile(TEMP_PATH, $filed);
                    }
                    $_REQUEST_DATA[$filed] = json_encode(array('filename'=>$filename) + $fileInfo, 256);
                    $_TEMP_FILES[] = $filename;
                }
            } 
            else {
                Util::echoJson(array());
            }

            // Submit request permission authentication, written here
            if(! in_array($_FILTER['api'], $ALLOW_ANONYMOUS_CALL_API)) 
            {
                $isAuthSuccess = false;
                $version = preg_replace('/[^(\d|\.)]/', '', $_FILTER['version']);
                if("2.0" == $version) {
                    if(is_callable('rpcRequestAuth')) $isAuthSuccess = call_user_func_array("rpcRequestAuth", array($httpHeaders, $contentJson));
                    else throw new \Exception('RPC authentication method not defined', 1008);
                }
                else
                {
                    if(isset($API_CALL_CONFIG[$httpHeaders['API_KEY']])
                        && strtoupper($httpHeaders['API_TOKEN']) == strtoupper(md5($contentJson.$API_CALL_CONFIG[$httpHeaders['API_KEY']]))
                    ) {
                        $isAuthSuccess = true;
                    }
                }
                if(! $isAuthSuccess) throw new \Exception('API authorization failed', 1002);
            }
            $GLOBALS['_rpc_private_params']['httpHeaders'] = $httpHeaders;

            preg_match('/(\w+)\.(\w+)\.(\w+)$/i', $_FILTER['api'], $p);
            $app = strtolower($p[1]); $action = $p[2]; $method = $p[3];

            $pagination = false;
            // Here you need to encapsulate a paging class

            $objectName = "\\Doba\\Rpc\\".('api' != $app ? ucfirst($app)."\\" : ''). $action.'Rpc';
            $theAction = new $objectName();
            if(! method_exists($theAction, $p[3])) throw new \Exception('Call to undefined method: '.$action.'->'.$p[3], 1008);
            $Data['Results'] = $theAction->{$p[3]}($_REQUEST_DATA, $pagination);
            $this->ccc($_TEMP_FILES); Util::echoJson(array('ErrorCode' => '9999', 'Message' => 'SUCCESS', 'Data' => $Data));
        } catch(\Exception $ex) {
            $this->ccc($_TEMP_FILES); Util::echoJson(array('ErrorCode' => strval($ex->getCode()), 'Message'=>$ex->getMessage()));
        }
    }
    /**
     * Methods are called before data is returned to the client
     */
    private function ccc($_TEMP_FILES=array()) {// clear call cache
        // 1 If there is an uploaded file, the temporary file will be deleted after the request
        if(is_array($_TEMP_FILES)) foreach($_TEMP_FILES as $tfile) {
            if(Util::isFile($tfile)) @unlink($tfile);
        }
    }
    /**
     * Gets Header request parameters
     */
    private function getHttpHeaders() {
        $httpHeaders = array();
        foreach($_SERVER as $key => $val) {
            if(preg_match('/^HTTP_X_/', $key)) $httpHeaders[preg_replace('/HTTP_X_/', '', $key)] = $val;
        }
        return $httpHeaders;
    }

}