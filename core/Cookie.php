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
    private $iv = "0123456789abcdef";
    // Legacy DES IV (8 bytes) for decrypting cookies set by older versions
    private $legacyIv = "01234567";

    public static function me(){
        if(! self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function key($key='', $iv='')
    {
        if($iv) {
            $this->iv = str_pad(substr($iv, 0, 16), 16, '0');
            $this->legacyIv = str_pad(substr($iv, 0, 8), 8, '0');
        }
        if($key) $this->key = str_pad($key, 32, '0');
        return self::me();
    }

    /**
     * Delete cookie
     *
     * @param array $params
     * @return boolean
     */
    public function drop($params)
    {
        $name = $params; $domain = null;
        if(is_array($params)) {
            $name = $params['name'];
            $domain = isset($params['domain']) ? $params['domain'] : null;
        }
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
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param array $options
     * @return boolean
     */
    public function set($name, $value, $expires=0, $options=array())
    {
        $value = $this->encrypt($value);
        $options['expires'] = $expires > 0 ? time() + $expires : 0;
        $options['path'] = isset($options['path']) ? $options['path'] : '/';
        $options['domain'] = isset($options['domain']) ? $options['domain'] : null;
        $options['secure'] = isset($options['secure']) ? $options['secure'] : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $options['httponly'] = isset($options['httponly']) ? $options['httponly'] : true;
        // Lax is safer default; None requires Secure and is often blocked
        $options['samesite'] = isset($options['samesite']) ? $options['samesite'] : 'Lax';
        return version_compare(PHP_VERSION, '7.3.0') >= 0
            ? setcookie($name, $value, $options)
            : setcookie($name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
    }

    /**
     * Encrypt cookie value (AES-256-CBC, IV prepended)
     *
     * @param string $data Unencrypted string
     * @return string
     */
    public function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($data, 'AES-256-CBC', $this->aesKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }
    
    /**
     * Decrypt cookie value (AES first, fallback to legacy DES-EDE3-CBC)
     *
     * @param string $data Encrypted
     * @return string|false
     */
    public function decrypt($data) {
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) < 17) {
            return $this->decryptLegacy($data);
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $this->aesKey(), OPENSSL_RAW_DATA, $iv);
        if ($plain !== false) {
            return $plain;
        }
        return $this->decryptLegacy($data);
    }

    /**
     * Decrypt cookies written by older framework versions (des-ede3-cbc)
     */
    private function decryptLegacy($data) {
        $key = str_pad(substr($this->key, 0, 24), 24, '0');
        return openssl_decrypt(base64_decode($data), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $this->legacyIv);
    }

    private function aesKey() {
        return hash('sha256', $this->key, true);
    }
}
