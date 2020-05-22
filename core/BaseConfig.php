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
namespace Doba;

class BaseConfig
{
    private static $instance = array();
    protected $dbConfigs = array();
    protected $redisConfigs = array();
    
    protected function __construct() {}
    public static function me(){
        $class = get_called_class();
        if(! self::$instance[$class]) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    // recode system log
    public function recordSysLog($code, $message, $file, $line, $from) 
    {
        if(defined('TEMP_PATH') && defined('DEBUG_ERROR')) 
        {
            $codes = array(); $errTypeMap = array(1=>'ERROR', 2=>'WARNING', 4=>'PARSE', 8=>'NOTICE', 16=>'CORE_ERROR', 32=>'CORE_WARNING', 64=>'COMPILE_ERROR', 128=>'COMPILE_WARNING', 256=>'USER_ERROR', 512=>'USER_WARNING', 1024=>'USER_NOTICE', 2047=>'ALL', 2048=>'STRICT', 4069=>'RECOVERABLE_ERROR'); 
            switch(DEBUG_ERROR) {
                case 'warning': $codes = array(2, 512); break;
                case 'error': $codes = array(1, 2, 4, 256, 512); break;
                case 'all': $codes = array_keys($errTypeMap); break;
            }
            if(in_array($code, $codes) &&  is_dir(TEMP_PATH))
            {
                $syslog = preg_replace('/\/$/', '', TEMP_PATH).'/'.date('Ym').'-doba.log'; 
                if(! is_file($syslog)) {
                    file_put_contents($syslog, '['.date('Y-m').']System Log');
                    chmod($syslog, 0777);
                }

                $logInfo = array(
                    'code' => $errTypeMap[$code],
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'query_sql' => preg_match('/doba\/core\/SQL\.php/', $file) ? $GLOBALS['QUERY_SQL'] : '',
                );

                $isRecord = true;
                if(is_callable(array($this, 'filterSysLog')) && 
                    false === call_user_func_array(array($this, "filterSysLog"), array($logInfo))) $isRecord = false;
                if(true === $GLOBALS['STOP_SYS_LOG']) $isRecord = false;

                if($logInfo['query_sql']) $message .= '('.$logInfo['query_sql'].')';

                $isRecord && file_put_contents($syslog, PHP_EOL.date('Y-m-d H:i:s').
                    '['.$from.']['.$errTypeMap[$code].']['.$message.']['.$file.']['.$line.']', 8);
            }
        }   
    }

    /**
     * Here you can ignore processed log messages
     * For example: 
     * 
     * mysql warning: MySQL server has gone away
     * The above error is caused by the database link timeout and has been processed in the current framework code.
     * When the above situation occurs, the database will be reconnected once, and the unsuccessfull SQL is executed
     *
     * Although the framework has dealt with the problem, Warning will still be captured by the system and recorded in the log 
     * by the above method, which is unnecessary. So here you can define processed warning without logging
     * 
     * @param $logInfo array('code', 'message', 'file', 'line', 'query_sql')
     */
    public function filterSysLog($logInfo = array()) 
    {
        if(preg_match('/doba\/core\/SQL\.php/i', $logInfo['file']))
        {
            if('WARNING' == $logInfo['code']) {
                if(preg_match('/MySQL server has gone away/i', $logInfo['message'])) return false;
                if(preg_match('/Error reading result set\'s header/i', $logInfo['message'])) return false;
            }
        }
        return true;
    }

    public function shutdownFunction() {
        $e = error_get_last();
        $this->recordSysLog($e['type'], $e['message'], $e['file'], $e['line'], 1);
    }
    
    public function getRedisConfig($key='default') {
        if(! $this->redisConfigs) $this->setRedisConfigs();
        $redisConfig = isset($this->redisConfigs[$key]) ? $this->redisConfigs[$key] : array();
        if(! $redisConfig) throw new \Exception('['.$key.'] redis connection configuration not found');
        return $redisConfig;
    }

    public function setRedisConfigs() {
        if(defined('REDIS_CONFIGS')) {
            $this->redisConfigs = json_decode(REDIS_CONFIGS, true);
        } else {
            $this->redisConfigs = array('default'=>array('host'=>'127.0.0.1', 'port'=>'6379', 'pass'=>'', 'persistent'=>false));
        }
    }

    public function getDb($key='default', $option=array()) {
        $reconnect = isset($option['reconnect']) && true === $option['reconnect'] ? true : false;
        $ckey = (PHP_SAPI === 'cli') ? $key.getmypid() : $key;
        if(! $GLOBALS[$ckey.'db'] || $reconnect) {
            $dbConfigs = $this->getDbConfigs();
            $dbConfig = isset($dbConfigs[$key]) ? $dbConfigs[$key] : array();
            if(! $dbConfig) throw new \Exception('['.$key.'] database connection configuration not found');
            $GLOBALS[$ckey.'db'] = new \Doba\SQL($dbConfig);
        }
        return $GLOBALS[$ckey.'db'];
    }

    // Database link not set, default is mysql
    public function getDbConfigs() {
        if(! $this->dbConfigs) $this->setDbConfigs();
        if(is_array($this->dbConfigs)) foreach($this->dbConfigs as $key=>$dbConfig) {
            $this->dbConfigs[$key] = $dbConfig + array('db'=>'mysql');
        }
        return $this->dbConfigs;
    }

    public function setDbConfigs() {
        if(defined('DB_CONFIGS')) {
            $this->dbConfigs = json_decode(DB_CONFIGS, true);
        } else {
            $this->dbConfigs = array('default'=>array('dbHost'=>'127.0.0.1', 'dbName'=>'test', 'dbUser'=>'root', 'dbPass'=>''));
        }
    }

    // configuration when initializing dao and map
    public function initDaoMapConfig() {
        return array(
            // Tables ignored when generating tables dao and map
            'IGNORED_TABLES'=>array('/^\w+_\d+$/i', '/^\w+\d+$/i'),
            'IGNORED_TABLES_PREFIX'=>array(),
        );
    }

    // Determine whether the development environment
    public function isDevEnvironment()
    {
        $clientIp = \Doba\Util::getIP();
        return ((defined('SANDBOX') && SANDBOX) || 
                preg_match('/^192\.168/', $clientIp) || 
                preg_match('/^127\.0/', $clientIp)) ? true : false;
    }

    /**
     * Call the rpc method of the current system. Of course, if other systems are also doba structures, 
     * we can also be called by passing in related paramseters.
     */
    public function apiCall($api, $edatas=array(), $options=array())
    {
        $url = $_API_KEY = $_API_SECURE = $_LANGUAGE = '';
        if(isset($options['_private_configs']))
        {
            $rpcHost = $options['_private_configs']['rpcHost'];
            if(! $rpcHost) return false;
            $url = preg_match('/^https?:\/\//', $rpcHost) ? $rpcHost : "http://{$rpcHost}/rpc.php";

            $_API_KEY = $options['_private_configs']['apiKey']; $_API_SECURE = $options['_private_configs']['apiSecure']; 
            $_LANGUAGE = $options['_private_configs']['language'] ? $options['_private_configs']['language'] : 'en';
        }
        else
        {
            $rpcHost = defined('RPC_HOST') ? RPC_HOST : '';
            if(! $rpcHost) return false;
            $url = preg_match('/^https?:\/\//', $rpcHost) ? $rpcHost : "http://{$rpcHost}/rpc.php";    

            $_API_KEY = API_KEY; $_API_SECURE = API_SECURE; $_LANGUAGE = defined('LANGUAGE') ? LANGUAGE : 'en';
        }
        $plus = $files = array();
        // Upload attachments if needed
        if(isset($options['attach']) && is_array($options['attach'])) {
            $filecnt = 0;
            foreach($options['attach'] as $filepath) {
                if(\Doba\Util::isFile($filepath)) {
                    $files['file'.$filecnt] = '@'.$filepath; $filecnt ++;
                }
            }
            if($filecnt > 0) $plus['filecnt'] = $filecnt;
        }
        $version = isset($options['version']) ? $options['version'] : '1.0';
        $params = array(
            'api'=>strval($api),
            'edatas'=>json_encode($plus + $edatas),
            'timestamp'=>strval(time()),
            'version'=>$version
        );
        $content = json_encode($params);
        $params += $files;
        $result = \Doba\Util::http($url, $params, 'POST', 
            array(
                'header'=>array(
                    "X-Api-Key: " . $_API_KEY,
                    "X-Api-Token: " . md5($content.$_API_SECURE),
                    "X-Language: " . $_LANGUAGE,
                ), 
                'attach'=>true,
            )
        );
        $result = @json_decode($result, true);
        // Determine whether to return all data or just results
        $returnAll = isset($options['all']) && false === $options['all'] ? false : true;
        $result = $returnAll ? $result : (isset($result['Data']) ? $result['Data']['Results'] : array());

        // Detemine whether the returned data type is an array or an object
        $returnObject = isset($options['object']) && true === $options['object'] ? true : false;
        $result = $returnObject ? \Doba\Util::array2object($result) : $result;

        return $result;
    }

    public function url($a, $plus="") {
        $plus = is_array($plus) ? http_build_query($plus) : $plus;
        return URL.'?a='.$a.($plus ? "&".preg_replace('/^[?|&]/', '', $plus) : '');
    }

    public function forward($a, $plus="") {
        header("location:".$this->url($a, $plus)); exit;
    }
    /**
     * Multilanguage translation
     *
     * When encountering uncertain characters in translated content, which may appear on the page or in the PHP code.
     * Tow parsing methods are provided here
     *
     * Method 1：Use str_replace in PHP
      echo str_replace(
        array('[E1]', '[E2]'), 
        array('A', 'B'), 
        'There is the character [E1] with an uncertainty, And then there's the uncertain [E2]'
        );
     *
     * Mehtod 2: Use the following encapsulated approach
       echo langi18n('There is the character %1 with an uncertainty, And then there's the uncertain %2', 'A', 'B');
     * 
     * Method 3：When using JS output on a page
     * 
        <script type="text/javascript">
        echo(
            langi18n(
            "There is the character %1 with an uncertainty, And then there's the uncertain %2", 
            "A", "B")
        );
        function langi18n(s)  
        { 
            var args = arguments;  var pattern = new RegExp("%([1-" + arguments.length + "])","g"); 
            return String(s).replace(pattern,function(word,index){ return args[index]; }); 
        }
        function echo(s) { document.write(s); }
        </script>
     */
    public function langi18n($text, $args=array())
    {
        $text = trim($text);
        $text = isset($GLOBALS['I18N_LANGS'][$text]) ? $GLOBALS['I18N_LANGS'][$text] : $text;

        // if there is a string include %1, %2 ... to replace real value
        preg_match_all('/(\d)/', $text, $out); //$args = func_get_args();
        if(isset($out[1]) && count($args) > 0) 
        {
            $keys = $vals = array();
            foreach ($args as $num=>$arg) { 
                $num ++;
                if(in_array($num, $out[1])) {
                    $keys[] = '%'.$num; $vals[] = $arg;
                } 
            }
            $text = count($keys) > 0 ? str_replace($keys, $vals, $text) : $text;
        }
        return $text;
    }

    /**
     * Generate mulitilanguage template
     */
    public function genI18nPage($page)
    {
        $cachePage = CACHE_PATH.'page/'.basename(WEB_PATH).'/'.LANGUAGE.'/'.$page;
        if( 
            (is_array($GLOBALS['I18N_LANGS']) && count($GLOBALS['I18N_LANGS']) > 0)
            &&
            (
                ! is_file($cachePage) 
                || filemtime($cachePage) < filemtime(PAGE_PATH.$page)
                || filemtime($cachePage) < filemtime(LANGUAGE_PATH.LANGUAGE.'.php')
                || ($GLOBALS['COMMON_LANG_FILE'] && filemtime($cachePage) < filemtime($GLOBALS['COMMON_LANG_FILE']))
            )
        )
        {
            $sourceContent = file_get_contents(PAGE_PATH.$page);
            preg_match_all("/{{\s*@(.[^}]+)}}/", $sourceContent, $out);
            $toArray = array_map(function($text){
                $text = trim($text);
                return isset($GLOBALS['I18N_LANGS'][$text]) ? $GLOBALS['I18N_LANGS'][$text] : $text;
            }, $out[1]);
            if(!is_dir($cacheDir = dirname($cachePage))) \Doba\Util::mkdir($cacheDir);
            file_put_contents($cachePage, str_replace($out[0], $toArray, $sourceContent));
        }
        return is_file($cachePage) ? $cachePage : PAGE_PATH.$page;
    }
}