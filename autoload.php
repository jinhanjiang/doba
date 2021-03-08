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
class Autoloader 
{
    /**
     * Singleton
     */
    private static $instance = array();

    /**
     * Associative array, the key name is the namespace prefix, and the key value is a basic directory array.
     *
     * @var array
     */
    protected $prefixs = array();

    /**
     * Loaded classes
     *
     * @var array
     */
    protected $hasLoadClasss = array();

    /**
     * Get the current singleton class
     */
    public static function me(){
        $class = get_called_class();
        if(! isset(self::$instance[$class])) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    /**
     * Automatic registration framework class method
     * 
     * @return void
     */
    public static function autoload() 
    {
        $loader = self::me();
        $loader->register();

        // Register doba namespace
        $loader->addNamespace('Doba', ROOT_PATH."doba/core");
        // Register doba/rpc namespace
        $loader->addNamespace('Doba\Rpc', ROOT_PATH."common/rpc");
        // Register doba/dao namespace
        $loader->addNamespace('Doba\Dao', ROOT_PATH."common/libs/dao");
        // Register doba/map namespace
        $loader->addNamespace('Doba\Map', ROOT_PATH."common/libs/map");
    }

    /**
     * Register the loader through the SPL autoloader stack
     *
     * @return void
     */
    public function register()
    {
        spl_autoload_register(array($this, 'loadClass'));
    }


    /**
     * Load the class file of the given class name
     *
     * @param string $class Legal class name
     * @return mixed The name of the mappend file on success, false on failure
     */
    public function loadClass($class)
    {
        // Check if the class file is loaded
        if(isset($this->hasLoadClasss[$class])) {
            return $this->hasLoadClasss[$class];
        }

        // Current namespace prefix
        $prefix = $class;

        // Reverse mapping of file names through full namespace class names
        while (false !== $pos = strrpos($prefix, '\\')) {

            // Keep the namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // The rest are related class names
            $relativeClass = substr($class, $pos + 1);

            // Try to laod the mapping file for the prefix and related classes
            $mapped = $this->loadMappedFile($class, $prefix, $relativeClass);
            if ($mapped) {
                return $mapped;
            }

            // Delete the trailing namespace separator in the next iteration of strrpos()
            $prefix = rtrim($prefix, '\\');
        }

        // The mapping file cannot be found
        return false;
    }

    /**
     * Load the mapping file for the namespace prefix and related classes
     *
     * @param string $class namespace class
     * @param string $prefix Namespace prefix
     * @param string $relativeClass Related class
     * @return mixed Boolean No mapping file is false, otherwish the mapping file is loaded
     */
    protected function loadMappedFile($class, $prefix, $relativeClass)
    {
        // Whether there are any base directories in the namespace prefix
        if (isset($this->prefixs[$prefix]) === false) {
            return false;
        }

        // Find the namespace prefix through the base directory
        foreach ($this->prefixs[$prefix] as $baseDir) {

            // Replace the namespace prefix with the base directory
            // Replace namespace separator with directory separator
            // Add .php suffix to related class name
            $file = $baseDir
                  . str_replace('\\', '/', $relativeClass)
                  . '.php';

            // 如果映射文件存在，则引入
            if ($this->requireFile($file, $class)) {
                // 搞定了
                return $file;
            }
        }

        // Not Found
        return false;
    }

    /**
     * Import from the system if the file exists
     *
     * @param string $file file
     * @param string $class namespace class
     * @return bool True if the file exists, otherwise false
     */
    protected function requireFile($file, $class)
    {
        if (file_exists($file)) {
            require $file;
            $this->hasLoadClasss[$class] = $file;
            return true;
        }
        return false;
    }

    /**
     * Add a base directory for the namespace prefix
     *
     * @param string $prefix namespace prefix
     * @param string $baseDir The base directory of the class file under the namespace
     * @param bool $prepend If true, put the base directory on the stack in advance instead of appending it later;
     * this will cause it to be searched first
     * @return void
     */
    public function addNamespace($prefix, $baseDir, $prepend = false)
    {
        // Normalized namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalized tail file separator
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        // Initialize the namespace prefix array
        if (isset($this->prefixs[$prefix]) === false) {
            $this->prefixs[$prefix] = array();
        }

        // The base directory that preserves the namespace prefix
        if ($prepend) {
            array_unshift($this->prefixs[$prefix], $baseDir);
        } else {
            array_push($this->prefixs[$prefix], $baseDir);
        }
    }
}