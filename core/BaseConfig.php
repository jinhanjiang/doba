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
    // recode system log
    public static function recordSysLog($code, $message, $file, $line, $from) 
    {
        if(defined('TEMP_PATH') && defined('DEBUG_ERROR')) 
        {
            $codes = array(); $err_type_map = array(1=>'ERROR', 2=>'WARNING', 4=>'PARSE', 8=>'NOTICE', 16=>'CORE_ERROR', 32=>'CORE_WARNING', 64=>'COMPILE_ERROR', 128=>'COMPILE_WARNING', 256=>'USER_ERROR', 512=>'USER_WARNING', 1024=>'USER_NOTICE', 2047=>'ALL', 2048=>'STRICT', 4069=>'RECOVERABLE_ERROR'); 
            switch(DEBUG_ERROR) {
                case 'warning': $codes = array(2, 512); break;
                case 'error': $codes = array(1, 2, 4, 256, 512); break;
                case 'all': $codes = array_keys($err_type_map); break;
            }
            if(in_array($code, $codes) &&  is_dir(TEMP_PATH))
            {
                $syslog = preg_replace('/\/$/', '', TEMP_PATH).'/'.date('Ym').'-doba.log'; 
                if(! is_file($syslog)) {
                    file_put_contents($syslog, '['.date('Y-m').']System Log');
                    chmod($syslog, 0777);
                }

                $logInfo = array(
                    'code_num' => $code, 
                    'code' => $err_type_map[$code],
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'query_sql' => preg_match('/\/core\/SQL\.php/', $file) ? $GLOBALS['QUERY_SQL'] : '',
                );

                $isRecord = true;
                if(is_callable('filterSysLog') && false === call_user_func_array("filterSysLog", array($logInfo))) $isRecord = false;
                if(true === $GLOBALS['STOP_SYS_LOG']) $isRecord = false;

                if($logInfo['query_sql']) $message .= '('.$logInfo['query_sql'].')';

                $isRecord && file_put_contents($syslog, PHP_EOL.date('Y-m-d H:i:s').
                    '['.$from.']['.$err_type_map[$code].']['.$message.']['.$file.']['.$line.']', 8);
            }
        }   
    }

    public static function errorFunction($errno, $errstr, $errfile, $errline, $errcontext) {
        self::recordSysLog($errno, $errstr, $errfile, $errline, 0);
    }

    public static function shutdownFunction() {
        $e = error_get_last();
        self::recordSysLog($e['type'], $e['message'], $e['file'], $e['line'], 1);
    }
    
    // Write some custom methods
    public static function getRedisConfig($key='default') {
        $redisConfig = array('ip'=>'127.0.0.1', 'port'=>'6379', 'pass'=>'');
        if(defined('REDIS_CONFIGS')) {
            $redisConfigs = json_decode(REDIS_CONFIGS, true);
            if(isset($redisConfigs[$key])) $redisConfig = $redisConfigs[$key];
        }
        return $redisConfig;
    }

    public static function getDb($key='default') {
        if(! $GLOBALS[$key.'db']) {  
            $dbConfig = array('dbHost'=>'127.0.0.1', 'dbName'=>'root', 'dbPass'=>'');
            if(defined('MYSQL_CONFIGS')) {
                $dbConfigs = json_decode(MYSQL_CONFIGS, true);
                if(isset($dbConfigs[$key])) $dbConfig = $dbConfigs[$key];
            }
            $GLOBALS[$key.'db'] = new \Doba\SQL(array('db'=>'mysql') + $dbConfig);
        }
        return $GLOBALS[$key.'db'];
    }

    public static function apiCall($api, $edatas=array(), $options=array())
    {
        $content = json_encode(
            array(
                'api'=>$api,
                'edatas'=>$edatas,
                'timestamp'=>time(),
                'version'=>'v1.0'
            )
        );
        $rpcHost = defined('RPC_HOST') ? RPC_HOST : '';
        if(! $rpcHost) return false;
        $result = file_get_contents("http://{$rpcHost}/rpc.php", 
            false, 
            stream_context_create(
                array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => implode("\r\n", array(
                            "Content-type: application/json",
                            "X-Requested-With: XMLHttpRequest",
                            "X-Api-Key: " . API_KEY,
                            "X-Api-Token: " . md5($content.API_SECURE),
                            "X-Language: " . defined('LANGUAGE') ? LANGUAGE : 'en',
                            "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
                        ))."\r\n",
                        'timeout' => 60,
                        'content' => $content
                    )
                )
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

    public static function url($a, $plus="") {
        $plus = is_array($plus) ? http_build_query($plus) : $plus;
        return URL.'?a='.$a.($plus ? "&".preg_replace('/^[?|&]/', '', $plus) : '');
    }

    public static function forward($a, $plus="") {
        header("location:".url($a, $plus)); exit;
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
    public static function langi18n($text)
    {
        $text = trim($text);
        $text = isset($GLOBALS['I18N_LANGS'][$text]) ? $GLOBALS['I18N_LANGS'][$text] : $text;

        // if there is a string include %1, %2 ... to replace real value
        preg_match_all('/(\d)/', $text, $out); $args = func_get_args();
        if(isset($out[1]) && count($args) > 0) 
        {
            $keys = $vals = array();
            foreach (func_get_args() as $num=>$arg) { 
                if(0 == $num) continue;  
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
    public static function genI18nPage($page)
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