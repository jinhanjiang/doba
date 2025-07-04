<?php

namespace Doba\Plugin;

class BasePlugin {
    /**
     * Load all public methods in the class
     */
    protected function _install(&$plugin, $object) {
        $ref = new \ReflectionClass($object);
        $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            if(preg_match('/^_/', $method->name)) { continue; }
            $plugin->install($ref->getShortName(), $object, $method->name);
        }
    }

}