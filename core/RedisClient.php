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

class RedisClient {
    
    private static $instance = array();
    protected $redisServer =  null;
    private $configs = array('host'=>'127.0.0.1', 'port'=>6379, 'pass'=>'', 'persistent'=>false);

    // queue key
    protected $qkey = '';

    private function __construct($key='default') {
        if(! extension_loaded('redis')) throw new \Exception('redis extension not loaded');
        $this->configs = getRedisConfig($key);
        $this->connect();
    }


    public static function me($key='default'){
        if(! self::$instance[$key]) {
            self::$instance[$key] = new self($key);
        }
        return self::$instance[$key];
    }

    /**
     * Close redis connect
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Connection does not close after a call to the close method, but only after the process has finished.
     *
     * pconnect(host, port, time_out, persistent_id, retry_interval)
     *  host: string. can be a host, or the path to a unix domain socket
     *  port: int, optional
     *  timeout: float, value in seconds (optional, default is 0 meaning unlimited)
     *  persistent_id: string. identity for the requested persistent connection
     *  retry_interval: int, value in milliseconds (optional)
     */
    public function connect() {
        $configs = $this->configs;
        $redisServer = new \Redis();
        if($configs['persistent']) {
            $redisServer->pconnect($configs['host'], $configs['port']);
        } else {
            $redisServer->connect($configs['host'], $configs['port']);
        }
        if($configs['pass']) $redisServer->auth($configs['pass']);
        $this->redisServer = $redisServer;
        return $this->redisServer;
    }

    public function close() {
        if($this->redisServer) {
            $this->redisServer->close(); $this->redisServer = null;
        }
    }

    public function getRedis() {
        return $this->redisServer;
    }

    /**
     * Match key to value, if the key already exists, it will be overridden, regardless of its type.
     * @param $key
     * @param $value
     * @param $exp Expiration time
     */
    public function set($key,$value,$exp=0) {
        $redis = $this->getRedis();
        $isOk = $redis->set($key, $value);
        if(! empty($exp)) $redis->expire($key, $exp);
        return $isOk;
    }
     
    /**
     * Set a key to corresponding to the string value, and set the key to expire the timeout after a given seconds
     * @param  $key
     * @param  $value
     * @param  $exp
     */
    public function setex($key, $value, $exp=0) {
        return $this->getRedis()->setex($key, $exp, $value);
    }

    /**
     * Set a key corresponding to the string value, and determine wheather the value is repeated
     * @param  $key
     * @param  $value
     */
    public function setnx($key, $value,$exp=0) {
        $redis = $this->getRedis();
        $isOk = $redis->setnx($key, $value);
        if($isOk && ! empty($exp)) $redis->expire($key, $exp);
        return $isOk;
    }
    
    /**
     * Set the expiration time for a key
     * 
     * @param  $key     
     * @param  $exp Expiration time
     */
    public function setExpire($key,$exp) {
        return $this->getRedis()->expire($key, $exp);
    }
    
    /**
     * Returns the value of the key, Returns the special value nil if the key does not exist, if the value of the key is not a string,
     * an error is returned, because GET only handles values of type string
     * @param $key
     */
    public function get($key){
        return $this->getRedis()->get($key);
    }
    
    /**
     * Returns the lifetime of the key, if key does not exist, return -2, if the value of the key is never invalidated return -1
     * @param $key
     */
    public function ttl($key){
        return $this->getRedis()->ttl($key);
    }

    /**
     * Delete one or more keys
     * @param $keys
     */
    public function delKey($keys) 
    {
        if(is_array($keys)) {
            foreach ($keys as $key) {
                $this->getRedis()->del($key);
            }
        } else {
            $this->getRedis()->del($keys);
        }
        return true;
    }

    public function setQkey($qkey) {
        $this->qkey = $qkey; return $this;
    }

    /**
     * Add data to the queue
     */
    public function putQueue($data) 
    {
        if(! $this->qkey) throw new \Exception('Queue key can not be empty');
        $nowTime = array('TimePutInQueue'=>date('Y-m-d H:i:s'));
        if(is_array($data)) {
            $data = $data + $nowTime;
            $data = json_encode($data);
        }
        else if(is_object($data)) {
            $data = (array)$data + $nowTime;  
            $data = json_encode($data);
        }
        else if(is_scalar($data)) {
            $data = (version_compare(PHP_VERSION, '5.4.0') >= 0) 
                ? json_encode(array("name"=>strval($data)) + $nowTime, JSON_UNESCAPED_UNICODE) 
                : json_encode(array("name"=>strval($data)) + $nowTime );
        }
        else {
            return false;
        }
        return $this->getRedis()->lPush($this->qkey, $data);
    }

    public function getQueue() {
        if(! $this->qkey) throw new \Exception('Queue key can not be empty');
        $data = $this->getRedis()->rPop($this->qkey);
        if($data) {
            $data = json_decode($data, true);
        }
        return $data;
    }

    /**
     * Get queue length
     */
    public function queueSize() {
        if(! $this->qkey) throw new \Exception('Queue key can not be empty');
        return $this->getRedis()->lSize($this->qkey);
    }

}