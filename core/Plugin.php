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

/*
BasePlugin.php

namespace Doba\Plugin;

class BasePlugin {
    protected function _install(&$plugin, $object) {
        $ref = new \ReflectionClass($object);
        $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            if(preg_match('/^_/', $method->name)) { continue; }
            $plugin->install($ref->getShortName(), $object, $method->name);
        }
    }
}

WebPlugin.php

namespace Doba\Plugin;

class WebPlugin extends BasePlugin {
    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }
}

*/

class Plugin {
    private $_plugin_dir;
    private $_plugins = array();

    public function __construct($pluginDir) {
        $this->_plugin_dir = preg_replace('/\/$/', '', $pluginDir).'/';
    }

    /**
     * Load method provided by plugin
     */
    public function install($plugin, &$class, $method) {
        $key = get_class($class).'->'.$method;
        $plugin = preg_replace('/plugin$/', '', strtolower($plugin));
        $this->_plugins[$plugin][$key] = array(&$class, $method);
    }

    /**
     * Invoke the method provided by the plugin
     */
    public function call($plugin, $function, $data=array()) {
        $result = NULL; $classFileName = ucfirst($plugin).'Plugin'; $className = "\\Doba\\Plugin\\".$classFileName;
        $pluginKey = strtolower($plugin);
        if(isset($this->_plugins[$pluginKey])) {
            foreach($this->_plugins[$pluginKey] as $methodInfo) {
                $class =& $methodInfo[0];
                $method = $methodInfo[1];
                if(method_exists($class, $method) && $function == $method){
                    $result = $class->$method($data);
                }
            }
        }
        else if(is_file($this->_plugin_dir.$classFileName.'.php')) 
        {
            if(class_exists($className)) {
                new $className($this);
                return isset($this->_plugins[$pluginKey]) ? $this->call($pluginKey, $function, $data) : NULL;
            }
        }
        return $result; 
    }
}