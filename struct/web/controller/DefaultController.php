<?php
use Doba\Util;
use Doba\SQL;
use Doba\Session;
use Doba\Cookie;
use Doba\Dao\XxxDAO;

class DefaultController extends BaseController
{
    public function __construct() {
        parent::__construct();
    }

    public function index() {
        if(!$this->memberInfo) forward('login');

        // Set pagination
        $data['pagination'] = $GLOBALS['plugin']->call('pagination', 'start');
        $data['pagination']->perPage = 12;
        $plus = [];

        // 常量
        $query = array(
            'orderBy'=>count($plus) > 0 ? 'id DESC' : 'RAND()',
            'limit'=>$data['pagination']->limit()
        ) + $plus;
        if(Util::isId($_GET['pid']) && $_GET['pid'] > 0) $query['pid'] = $_GET['pid'];

        $data['renderings'] = XxxDAO::me()->finds($query); 
        $data['pagination']->setPageTotal(XxxDAO::me()->findCount($query));

        $data['imgPath'] = $GLOBALS['plugin']->call('constant', 'getConstants', array('key'=>'URL_PATH'));

        $this->assign($data);
    }

    public function error($errorMsg) {
        echo htmlspecialchars($errorMsg);
    }

    public function login() {
        $data['hi'] = langi18n('Hello World');
        $this->assign($data);
    }

    public function doLogin()
    {
        $memberInfo = array(
            // Save the relevant fields in the session after login
        );

        Session::me()->assign(\Doba\Constant::getConstant('LOGIN_SESSION_KEY'), $memberInfo);
        $this->json();
    }

    public function logout() {
        Session::me()->clear(); 
        forward('login');
    }

    public function lang() {
        $lang = in_array($_GET['l'], array('en', 'zh')) ? $_GET['l'] : 'en';// en, zh, ...， You must set it in the lang directory
        \Doba\Cookie::me()->key('MULTI_LANGUAGE')->set('MULTI_LANGUAGE_TYPE', $lang, 86400*365);
        $this->json();
    }

}