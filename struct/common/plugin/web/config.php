<?php
use Doba\Session;
use Doba\Cookie;
use Doba\Util;
use Doba\Constant;

class WebPlugin extends BasePlugin {
    
    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    /**
     * Check if you are logged in
     * @param  array $params array('currentUrl', 'userDataFile', 'defaultActionField')
     * @return [type]
     */
    public static function checkIsLogin($params=array())
    {
        $SESSION_INFO = Session::me()->get(Constant::getConstant('LOGIN_SESSION_KEY'));
        if(empty($SESSION_INFO)) {
            if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                Util::echoJson(array('success'=>false, 'message'=>langi18n('Not logged in'))); exit;
            } 
            header("Location:".Constant::getConstant('URL')."?a=login"); exit; 
        }
    }

    public static function initPath($params=array())
    {
        // define constants
        $project = isset($params['project']) && $params['project'] ? $params['project'] : 'web';
        Constant::setConstant('LOGIN_SESSION_KEY', strtoupper('LOGIN_SESSION_KEY_'.$project));
        Constant::setConstant('WEB_PATH', Constant::getConstant('ROOT_PATH').$project.'/');
        Constant::setConstant('CONTROL_PATH', Constant::getConstant('WEB_PATH').'controller/');
        Constant::setConstant('LANGUAGE_PATH', Constant::getConstant('WEB_PATH').'lang/');
        Constant::setConstant('PAGE_PATH', Constant::getConstant('WEB_PATH').'views/');

        // set default lang
        $lang = isset($params['lang']) && $params['lang'] ? $params['lang'] : 'en';
        define('DEFAULT_LANGUAGE', $lang);
    }

    private static function getAction($_REQ) {
        $control = $method = '';
        if(! empty($_REQ['a'])) {
            if(preg_match('/\./', $_REQ['a'])) list($control, $method) = explode('.', $_REQ['a']);
            else $method = $_REQ['a'];
        }
        empty($control) && $control = 'default'; empty($method) && $method = 'index';
        return array('control'=>$control, 'method'=>$method);
    }

    /**
     * The render the page
     */
    public static function response($params)
    {
        $_REQ = $_REQUEST;
        try{
            require(__DIR__.'/BaseController.php');
            $action = self::getAction($_REQ);
            $control = $action['control']; $method = $action['method'];
            $_REQ['a'] = "{$control}.{$method}";
            $objectName = ucfirst($control.'Controller');
            $controlPage = Constant::getConstant('CONTROL_PATH').$objectName.'.php';
            if (! Util::isFile($controlPage)) throw new Exception(langi18n('The call controller file does not exist'), __LINE__);
            require($controlPage);
            $theController = new $objectName();
            if(! method_exists($theController, $method)) throw new Exception(langi18n('The controller method [%1] does not exist', $objectName.'->'.$method), __LINE__);
            $theController->{$method}($_REQ);
        } catch(Exception $ex) {
            $responseOther = [];
            if($ex->getCode() > 0) $responseOther['code'] = $ex->getCode();
            if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                Util::echoJson(array('success'=>false, 'message'=>$ex->getMessage()) + $responseOther); 
            }
            else
            {
                $defaultControlPage = Constant::getConstant('CONTROL_PATH').'DefaultController.php';
                if(Util::isFile($defaultControlPage)) require_once($defaultControlPage);
                $defaultController = new DefaultController();
                $message = ($ex->getCode() > 0 ? "[{$ex->getCode()}]:" : '').$ex->getMessage();
                if(method_exists($defaultController, 'error')) $defaultController->error($message);
                else {
                    echo $message;
                }
            }
        }
        exit();
    } 

    /**
     * Gets the URL address that is currently being accessed
     */
    public static function getURL($params=array())
    {
        $rootFile = isset($params['rootFile']) && $params['rootFile'] ? $params['rootFile'] : 'index.php';
        if(! is_null(Constant::getConstant('DOMAIN_HOST'))) {
            $scriptName = Constant::getConstant('DOMAIN_PATH').$rootFile; $scriptUrl = Constant::getConstant('DOMAIN_HOST').$scriptName;
        } else {
            $_IS_HTTPS = isset($_SERVER) && ('on'==$_SERVER['HTTPS'] || 'https'==$_SERVER['HTTP_X_FORWARDED_PROTO']) ? true : false;
            $scriptName = dirname($_SERVER['SCRIPT_NAME']);
            $scriptName = (DIRECTORY_SEPARATOR==$scriptName ? '' : $scriptName)."/{$rootFile}";
            $scriptUrl = ($_IS_HTTPS ? 'https':'http').'://'.$_SERVER['SERVER_NAME'].$scriptName;
        }
        return $scriptUrl;
    }

    /**
     * Get the current access language
     */
    public static function getLANG($params=array())
    {
        // Get mulitilingual Setting
        if(isset($_SERVER['HTTP_X_LANGUAGE'])) $lang = $_SERVER['HTTP_X_LANGUAGE'];
        else
        {
            $lang = Cookie::me()->key('MULTI_LANGUAGE')->get('MULTI_LANGUAGE_TYPE');
            if(empty($lang)) {
                $l0 = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]); $l1 = explode('-', $l0[0]);
                $lang = strtolower($l1[0]);
            }
        }
        if(! is_file(Constant::getConstant('LANGUAGE_PATH').$lang.'.php')) $lang = Constant::getConstant('DEFAULT_LANGUAGE');

        // Common language
        $commonLangs = Util::isFile($commonlangfile = __DIR__.'/lang/'.$lang.'.php')
            ? require_once($commonlangfile) : array();
        $GLOBALS['COMMON_LANG_FILE'] = $commonLangs ? $commonlangfile : "";

        // Loads language
        $i18nlangs = Util::isFile($langfile = Constant::getConstant('LANGUAGE_PATH').$lang.'.php') ? require_once($langfile) : array();
        
        // control language
        $action = self::getAction($_REQUEST);
        $controlLang = Util::isFile($langfile = Constant::getConstant('LANGUAGE_PATH').$action['control'].'/'.$lang.'.php') ? require_once($langfile) : array();

        $GLOBALS['I18N_LANGS'] = $controlLang + $i18nlangs + $commonLangs;

        return $lang;
    }

    public static function getResPath() { return dirname(Constant::getConstant('URL')).'/static/'; }

}