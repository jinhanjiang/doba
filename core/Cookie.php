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

class Cookie
{   
    private static $instance = null;

    private $key = "defaultkeydefaultkey1234";
    private $iv = "01234567";

    public static function me(){
        if(! self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function key($key='', $iv='')
    {
        if($iv) $this->iv = str_pad($iv, 8,'0');
        if($key) $this->key = str_pad($key, 24, '0');
        return self::me();
    }

    /**
     * Delete cookie
     *
     * @param array $args
     * @return boolean
     */
    public function drop($args)
    {
        $name = $args['name'];
        $domain = isset($args['domain']) ? $args['domain'] : null;
        return isset($_COOKIE[$name]) ? setcookie($name, '', time() - 86400, '/', $domain) : true;
    }
    
    /**
     * Get the value of the cookie
     *
     * @param string $name
     */
    public function get($name) {
        return isset($_COOKIE[$name]) ? $this->decrypt($_COOKIE[$name]) : null;
    }
    
    /**
     * Set cookie
     *
     * @param array $args
     * @return boolean
     */
    public function set($name, $value, $expire=0, $options=array())
    {
        $value= $this->encrypt($value);
        $expire = $expire > 0 ? time() + $expire : 0;
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : null;
        $secure = isset($options['secure']) ? $options['secure'] : 0;
        return setcookie($name, $value, $expire, $path, $domain, $secure);
    }

    /**
     * decrypt
     *
     * @param string $data Encrypted
     * @return string
     */
    public function encrypt($data) {
        return base64_encode(openssl_encrypt($data, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv));
    }
    
    /**
     * encrypt
     *
     * @param string $data Unencrypted string
     */
    public function decrypt($data) {
        return openssl_decrypt(base64_decode($data), 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
    }
}
?>