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

class Plugin {
    private $_plugin_dir;
    private $_plugins = array();

    public function __construct($pluginDir) {
        $this->_plugin_dir = preg_replace('/\/$/', '', $pluginDir).'/';
        $basePluginFile = $this->_plugin_dir.'BasePlugin.php';
        if(is_file($basePluginFile)) require($basePluginFile);
    }

    /**
     * Load method provided by plugin
     */
    public function install($plugin, &$class, $method) {
        $key = get_class($class).'->'.$method;
        $this->_plugins[$plugin][$key] = array(&$class, $method);
    }

    /**
     * Invoke the method provided by the plugin
     */
    public function call($plugin, $function, $data=array()) {
        $result = NULL;
        if(isset($this->_plugins[$plugin]))
            foreach($this->_plugins[$plugin] as $methodInfo) {
            $class =& $methodInfo[0];
            $method = $methodInfo[1];
            if(method_exists($class, $method) && $function == $method){
                $result = $class->$method($data);
            }
        }
        else if(is_file(($pluginConfig = $this->_plugin_dir.$plugin.'/config.php'))) 
        {
            include_once($pluginConfig);
            $class = ucfirst($plugin).'Plugin';
            if(class_exists($class)) {
                new $class($this);
                return isset($this->_plugins[$plugin]) ? $this->call($plugin, $function, $data) : NULL;
            }
        }
        return $result; 
    }
}