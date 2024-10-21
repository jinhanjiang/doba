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

class Session{
    private static $instance = null;
    private $sess_id = "";
    private $session_cache = array();
    private function __construct(){
        $this->setSession(function(){
            $this->sess_id = session_id();
            if (isset($_SESSION[$this->sess_id])){
                $this->session_cache = $_SESSION[$this->sess_id];
            }
        });
    }

    public static function me(){
        if(! self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function save(){
        $this->setSession(function(){
            $_SESSION[$this->sess_id] = $this->session_cache;
        });
    }

    public function clear(){
        $this->session_cache = array();
        $this->setSession(function(){
            unset($_SESSION[$this->sess_id]);
        });
    }

    public function assign($var, $val){
        $this->session_cache[$var] = serialize($val);
        $this->save();
    }

    public function get($var){
        return (isset($this->session_cache[$var])) ? unserialize($this->session_cache[$var]) : null;
    }
    
    public function drop(){
        if (!func_num_args())throw new \Exception('missing argument(s)');
        foreach (func_get_args() as $arg) { unset($this->session_cache[$arg]); }
        $this->save();
    }

    public function setSession($callback) {
        $resp = null;
        if(is_callable($callback)) {
            session_start(); $resp = $callback(); session_write_close();
        }
        return $resp;
    }

    public function getSessionId(){
        return $this->sess_id;
    }
}