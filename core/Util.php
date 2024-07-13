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

class Util {
    
    /**
     * data encrypted to hex or decrypted (Default: encrypted)
     * @param $data Data to be encrypted/decrypted
     */
    public static function tohex($data, $bin2hex=true) {
        $data = (is_object($data) || is_array($data)) ? json_encode($data) : $data;
        if (! is_string($data)) return 0;

        if($bin2hex) return bin2hex(gzcompress($data));
        else
        {
            $len = strlen($data);
            if ($len % 2) return 0;
            else if (strspn($data, '0123456789abcdefABCDEF') != $len) return 0;
            $data = pack('H*', $data);
            return gzuncompress($data);
        }
    }

    /**
     * Use more cryptographically strong algorithm to generate pseudo-random bytes and format it as GUID v4 string
     */
    public static function guidv4()
    {
        if (function_exists('com_create_guid') === true)
            return trim(com_create_guid(), '{}');

        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Execute javascript
     */
    public static function scripts($scriptcode)
    {
        echo <<<HTML
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>SCRIPT</title>
        </head>
        <body>
            <script>{$scriptcode}</script>
        </body>
        </html>
HTML;
    }

    /**
     * Abnormal output
     */
    public static function echoException($ex) {
        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            self::echoJson(array('success'=>false, 'message'=>$ex->getMessage())); 
        }
        else {
            echo $ex->getMessage(); 
        }
        exit;
    }

    /**
     * inovke network request
     */
    public static function http($url, $data, $method = 'POST', $options = array())
    {
        $urlarr = parse_url($url);
        $timeout = isset($options['timeout']) && $options['timeout'] > 0 ? $options['timeout'] : 60;
        $timeout = (! isset($options['waitForResponse']) || $options['waitForResponse']) ? $timeout : 1;
        $outHeader = isset($options['outHeader']) && $options['outHeader'] ? true : false;
        $arrayReturn = isset($options['arrayReturn']) && $options['arrayReturn'] ? true : false;
        $header = is_array($options['header']) ? $options['header'] : array(); // for example: ['Content-Type: application/json']
        $opts = is_array($options['opts']) ? $options['opts'] : array();
        $debug = isset($options['debug']) ? $options['debug'] : false;

        $withAttach = isset($options['attach']) ? $options['attach'] : false;
        if($withAttach) {
            $method = 'POST'; $rt = self::buildHttpQueryMulti($data); $data = $rt['multipartbody'];
            $header[] = "Content-Type: multipart/form-data; boundary=" . $rt['boundary'];
        } else {
            $data = is_array($data) ? http_build_query($data) : $data;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        if(isset($options['proxyIp'])) {//Contains IP and Port
            curl_setopt($ch, CURLOPT_PROXY, $options['proxyIp']);
        }
        if(isset($options['encoding'])){
            curl_setopt($ch, CURLOPT_ENCODING, $options['encoding']);
        }
        if($options['userpwd']){
            curl_setopt($ch, CURLOPT_USERPWD, $options['userpwd']);
        }
        if($options['cookietext']){
            curl_setopt($ch, CURLOPT_COOKIE, $options['cookietext']);
        }
        if (strtolower($urlarr['scheme']) == 'https')
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);   
        }
        // httpvxx default CURL_HTTP_VERSION_NONE lets CURL decide which version to use
        if(isset($options['httpv10'])) { // forces HTTP/1.0
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        }
        if(isset($options['httpv11'])) { // forces HTTP/1.1
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        if(isset($options['httpv20'])) { // attempts HTTP 2 , CURL_HTTP_VERSION_2 alias of CURL_HTTP_VERSION_2_0
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        }
        if(isset($options['httpv2tls'])) { // attempts HTTP 2 over TLS (HTTPS) only
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        }
        if (isset($urlarr['port'])) {
            curl_setopt($ch, CURLOPT_PORT, $urlarr['port']);
        }
        if (strtoupper($method) == 'POST')
        {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        else if(strtoupper($method) == 'DELETE')
        {
            $header[] = "X-HTTP-Method-Override: DELETE";
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        else if(strtoupper($method) == 'PUT')
        {
            $header[] = "X-HTTP-Method-Override: PUT";
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        else // request GET
        {
            if ($data)
            {
                if (false===strpos($url, '?'))
                    $url .= '?'.$data;
                else
                    $url .= '&'.$data;
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        if(is_array($opts)) {
            foreach($opts as $opt) {
                curl_setopt($ch, $opt['key'], $opt['value']);
            }
        }
        $respHeader = []; $respBody = '';
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        $output = curl_exec($ch);
        // Obtain the header size in the response result
        $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $htotal = strlen($output);
        // Calculate the size of BODY content
        $bsize = $htotal - $hsize;
        // Retrieve header content based on header size
        $respHeader = substr($output, 0, $hsize);
        $respHeader = array_filter(explode("\r\n", $respHeader));
        $respBody = substr($output, $hsize, $bsize);
        // Parse response content compression method
        $respDecode = '';
        foreach((array)$respHeader as $rHeader) {
            if(false !== ($pos = stripos($rHeader, 'Content-Encoding:'))) {
                $respDecode = trim(substr($rHeader, $pos + 17));
            }
        }
        if($respDecode) {
            if('gzip' == $respDecode) {
                $respBody = gzdecode($respBody);
            } else if('deflate' == $respDecode) {
                $respBody = gzuncompress($respBody);
            } else if('deflate-raw' == $respDecode) {
                $respBody = gzinflate($respBody);
            }
        }
        if($debug)
        {
            $response = array(
                'info'=>curl_getinfo($ch),
                'error'=>curl_error($ch),
                'header'=>$respHeader, 
                'body'=>$respBody
            );
            curl_close($ch);
            return $response;
        }
        curl_close($ch);
        if($arrayReturn) {
            $respBody = self::isJson($respBody) ? self::dJson($respBody, true) : [];
        }
        if($outHeader) {   
            return ['header'=>$respHeader, 'body'=>$respBody];
        } else {
            return $respBody;
        }
    }

    private static function buildHttpQueryMulti($params)
    {
        if (!$params) return '';
        uksort($params, 'strcmp');

        $return['boundary'] = $boundary = uniqid('------------------');
        $MPboundary = '--'.$boundary;
        $endMPboundary = $MPboundary. '--';
        $multipartbody = '';

        foreach ($params as $parameter => $value)
        {
            $multipartbody .= $MPboundary . "\r\n";
            if(substr($value, 0, 1) == '@')
            {
                $url = ltrim($value, '@');
                $content = file_get_contents($url);
                $array = explode('?', basename($url) );
                $filename = $array[0];

                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
                if(in_array($parameter, array('pic', 'image'))) {
                    $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
                } else {
                    $multipartbody .= "Content-Type: application/octet-stream\r\n\r\n";
                }
                $multipartbody .= $content. "\r\n";
            }
            else
            {
                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
                $multipartbody .= $value."\r\n";
            }
        }
        $multipartbody .= $endMPboundary;
        $return['multipartbody'] = $multipartbody;
        return $return;
    }

    /**
     * Get ip
     */
    static public function getIp() 
    {   
        if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(current($ip));
        } else if ($_SERVER["HTTP_CLIENT_IP"]) 
            $ip = $_SERVER["HTTP_CLIENT_IP"]; 
        else if ($_SERVER["REMOTE_ADDR"]) 
            $ip = $_SERVER["REMOTE_ADDR"]; 
        else if (getenv("HTTP_X_FORWARDED_FOR")) 
            $ip = getenv("HTTP_X_FORWARDED_FOR"); 
        else if (getenv("HTTP_CLIENT_IP")) 
            $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("REMOTE_ADDR")) 
            $ip = getenv("REMOTE_ADDR"); 
        else 
            $ip = "Unknown"; 
        return $ip; 
    }

    /**
     *  Disk size formatting, for example: fsize(memory_get_usage());
     */ 
    public static function fsize($size){
        $unit = array('b','Kb','Mb','Gb','Tb','Pb');
        $prefix = $size < 0 ? '-' : ''; $size = abs($size);
        return $prefix.@round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    /**
     * Sort by array field
     */
    public static function multiArraySort($arrays, $field, $sort=SORT_DESC)
    {
        if(is_array($arrays) && count($arrays) > 0)
        {
            $arraySorts = array();
            foreach($arrays as $uniqid => $array) {
                foreach($array as $k=>$v) {
                    $arraySorts[$k][$uniqid] = $v;
                }
            }
            array_multisort($arraySorts[$field], $sort, $arrays);
        }
        return $arrays;
    }

    /**
     * Get Dirs
     */
    public static function getDirs($dir, $returnAllFile=false)
    {
        if(! is_dir($dir)) return array();
        $dirArray = array();
        if (false != ($handle = opendir ( $dir )))
        {
            while ( false !== ($file = readdir ( $handle )) ) {
                //Remove "".", ".. "And files with the suffix ".xxx"
                if (! preg_match('/^\./', $file)) {
                    //if(2 == $type && ! is_dir($dir.$file)) continue;
                    //if(3 == $type && ! is_file($dir.$file)) continue;
                    //type:1unknown 2dir 3file
                    $fullpath = preg_replace('/\/$/', '', $dir).'/'.$file; $ftype = is_dir($fullpath) ? 2 : (is_file($fullpath) ? 3 : 1);
                    $dirArray[] = array('fullpath'=>$fullpath, 'filename'=>$file, 'type'=>$ftype);
                    if($returnAllFile && is_dir($fullpath)) {
                        $dirArray = array_merge($dirArray, self::getDirs($fullpath, true));
                    }
                }
            }
            closedir($handle);
        }
        sort($dirArray);
        return $dirArray;
    }

    /**
     * Upload file
     */
    public static function uploadFile($default_path='', $field_name='file')
    {
        ($default_path != '' && (substr($default_path, -1) != '/')) && $default_path .= '/'; 
        self::mkdir($default_path);
        
        $source_filename = $_FILES[$field_name]['name'];
        $fileInfo = pathinfo($source_filename);

        $extension = '';
        if(isset($fileInfo['extension']))
        {
            $deniedFileExts = array('php', 'exe', 'bat');
            if (in_array(strtolower($fileInfo['extension']), $deniedFileExts)) {
                throw new \Exception('The file is not allowed to be uploaded');
            }
            $extension = $fileInfo['extension'];
        }
        else if('image/jpeg' == $_FILES[$field_name]['type'])  $extension = 'jpg';
        else if('image/png' == $_FILES[$field_name]['type']) $extension = 'png';
        else if('image/gif' == $_FILES[$field_name]['type']) $extension = 'gif';
        
        if(! $extension) throw new \Exception('Unknown file type');
        $new_filename = self::getRandomValue().'.'.$extension;
        move_uploaded_file($_FILES[$field_name]['tmp_name'], $default_path.$new_filename);
        return $new_filename;
    }

    /**
     * Convert array to object
     */
    public static function array2object($array = array()) {
        return json_decode(json_encode($array));
    }
     
    /**
     * Convert object to array
     */
    public static function object2array($object) {
        return json_decode(json_encode($object), true);
    }

    /**
     * Gets random parameter values
     */
    public static function getRandomValue()
    {
        preg_match('/0.([0-9]+) ([0-9]+)/', microtime(), $regs);
        return $regs[2].$regs[1].sprintf('%03d', rand(0, 999));
    }

    /**
     * Determine whether it is a file
     */
    public static function isFile($file) { 
        $file = strval(str_replace("\0", "", $file)); return is_file($file); 
    }
    
    /**
     * Recursively create directories
     */
    public static function mkdir($dir, $mode=0777) {
        return is_dir($dir) ? true : mkdir($dir, $mode, true);
    }
    
    /**
     * Delete directories
     */
    public static function deldir($dir)
    {
        if (! $handle = @opendir($dir)) return false;
        while (false !== ($file = readdir($handle))) {
            if ($file !== "." && $file !== "..") {
                $file = $dir . '/' . $file;
                is_dir($file) ? self::deldir($file) : @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * Check if the incoming parameter is a number
     */
    public static function isId($id) {
        return preg_match('/^\d+$/', $id) ? true : false;
    }

    /**
     * Convert ab_bc_def to abBcDef
     */
    public static function camelcase($str) {
        return preg_replace_callback('/_(\w)/',function($match) {
            return strtoupper($match[1]);
        }, $str);
    }

    /**
     * Convert abBcDef to ab_bc_def
     */
    public static function snakecase($str) {
        return strtolower(preg_replace('/[[:upper:]]/','_\0',$str));
    }

    /**
     * To determine whether it is json
     */
    public static function isJson($text) { 
        if($text && is_string($text) && 'null' != $text) {
            $text = trim($text);
            $start = substr($text, 0, 1); $end = substr($text, -1);
            if(('{' == $start && '}' == $end) 
            || ('[' == $start && ']' == $end)) {}
            else {
                return false;
            }
            @json_decode($text); return (json_last_error() === JSON_ERROR_NONE);
        }
        return false;
    }
    
    /** 
     * Output json string
     */
    public static function echoJson($array=array()) { 
        echo self::eJson($array); exit;
    }
    
    /**
     * Convert array to json, If it contains bigint, it is automatically converted to a string
     */
    public static function eJson($array=array()) { 
        return json_encode($array, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE); 
    }

    /**
     * Convert json string to array, 
     */
    public static function dJson($json="", 
        $assoc=false, $depth = 512, $options = JSON_BIGINT_AS_STRING) { 
        return json_decode($json, $assoc, $depth, $options); 
    }
}