<?php
use Doba\Util;
use Doba\Session;
use Doba\Constant;

class BaseController
{
    protected $memberInfo = array();
    public function __construct() {
        $this->memberInfo = Session::me()->get(Constant::getConstant('LOGIN_SESSION_KEY'));
    }

    protected function assign($data=array(), $controllConfig=array())
    {
        $backtrace = debug_backtrace();
        array_shift($backtrace);

        $class = preg_replace('/controller$/i', '', $backtrace[0]['class']);
        $method = (isset($controllConfig['page']) && $controllConfig['page']) 
            ? $controllConfig['page'] : Util::snakecase($backtrace[0]['function']);

        $viewPage = strtolower($class.'/'.str_replace('_', '-', $method).'.php');
        if(! Util::isFile(Constant::getConstant('PAGE_PATH').$viewPage)) {
            throw new Exception(langi18n('Display page does not exist'));
        }
        
        unset($controllConfig);
        $_REQ = $_REQUEST;
        $memberInfo = $this->memberInfo;
        require(genI18nPage($viewPage));
    }

    protected function json($data=array()) {
        Util::echoJson($data + array('success'=>true));
    }
}