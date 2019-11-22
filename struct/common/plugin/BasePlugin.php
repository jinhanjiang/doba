<?php
class BasePlugin {
    /**
     * Load all public methods in the class
     */
    protected function _install(&$plugin, $object) {
        $ref = new ReflectionClass($object);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $className = preg_replace('/plugin$/i', '', strtolower($ref->getName()));
        foreach($methods as $method) {
            if(preg_match('/^_/', $method->name)) continue;
            $plugin->install($className, $object, $method->name);             
        }
    }

}