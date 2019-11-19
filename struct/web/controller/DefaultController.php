<?
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

        // 常量
        $query = array(
            'orderBy'=>count($plus) > 0 ? 'id DESC' : 'RAND()',
            'limit'=>$data['pagination']->limit()
        ) + $plus;
        if(Util::isId($_GET['pid'])) $query['pid'] = $_GET['pid'];

        $data['renderings'] = XxxDAO::me()->finds($query); 
        $data['pagination']->setPageTotal(XxxDAO::me()->findCount($query));

        $data['imgPath'] = $GLOBALS['plugin']->call('constant', 'getConstants', array('key'=>'URL_PATH'));

        $this->assign($data);
    }

    public function error($errorMsg) {
        echo $errorMsg;
    }

    public function login() {
        $this->assign();
    }

    public function doLogin()
    {
        $memberInfo = array(
            // Save the relevant fields in the session after login
        );

        Session::me()->assign(LOGIN_SESSION_KEY, $memberInfo);
        $this->json();
    }

    public function logout() {
        Session::me()->clear(); 
        forward('login');
    }

}