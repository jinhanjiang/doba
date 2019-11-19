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

class Des3 {
    private $key = "defaultkeydefaultkey1234";
    private $iv = "01234567";
 
    public function __construct($iv="", $key="") {
        if($iv) $this->iv = str_pad($iv, 8,'0');
        if($key) $this->key = str_pad($key, 24,'0');
    }

    /**
     * encrypt
     */
    public function encrypt($data) {
        return base64_encode(openssl_encrypt($data, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv));
    }
 
    /**
     * decrypt
     */
    public function decrypt($data){
        return openssl_decrypt(base64_decode($data), 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
    }
}