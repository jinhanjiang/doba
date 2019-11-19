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

use Doba\Util;

class AutoTask {

    /**
     * Process status: starting
     * @var int
     */
    const STARTING = 1;

    /**
     * Process status: running
     * @var int
     */
    const RUNNING = 2;

    /**
     * Process status: shutdown
     * @var int
     */
    const SHUTDOWN = 4;

    /**
     * Process status 1 starting, 2 running 4 shutdown
     * @var int
     */
    private $status = self::STARTING;

    /**
     * Service startup time
     */
    private $startTime = 0;

    /**
     * support linux signal
     * @var array
     */
    protected $signals = array(
        SIGTERM, 
        SIGHUP, 
        SIGINT, 
        SIGQUIT, 
        SIGILL, 
        SIGPIPE, 
        SIGALRM
    );

    /**
     * Marks whether the task is executed once or not
     */
    private $onlyRunOnce = true;

    /**
     * Residen loop, how many milliseconds eache cycle sleep
     */
    public $hangupLoopMicrotime = 100;

    public function __construct() {
        $this->registerSigHandler();
        register_shutdown_function(array($this, 'shutdownHandler'));
    }

    private $runClosure = null;

    private $logClosure = null;

    /**
     * The following methods can be overidden by the child method
     */
    public function run() {
        if($this->runClosure) call_user_func_array($this->runClosure, array($this));
        else exit(); 
    }

    public function log($log) {
        if($this->logClosure) call_user_func_array($this->logClosure, array($log, $this));
    }

    public function setRunClosure($closure) {
        $this->runClosure = $closure;
    }

    public function setLogClosure($closure) {
        $this->logClosure = $closure;
    }

    public function setRunOnce($once=true) {
        $this->onlyRunOnce = $once;
    }

    /**
     * Start task
     */
    public function start() {
        $this->status = self::RUNNING;
        $this->startTime = time();
        if($this->onlyRunOnce) {
            $this->runTask();
        }
        else
        {
            while(true) {
                $this->runTask();
                usleep($this->hangupLoopMicrotime);
            }
        }
        $this->stop();
    }

    private function runTask() {
        if(extension_loaded('pcntl')) pcntl_signal_dispatch();
        $this->run();
    }

    public function stop() { 
        $this->status = static::SHUTDOWN;
        exit();
    }

    /**
     * Check errors when current process exited.
     *
     * @return void
     */
    public function shutdownHandler()
    {
        $normal_exit = $this->status == self::SHUTDOWN ? true : false;
        $log = array(
            'signal'=>0,
            'normal_exit'=>$normal_exit,
            'run_time'=>time() - $this->startTime,
            'memory_usage'=>Util::fsize(memory_get_usage(true)),
            'process_no'=>posix_getpid(),
            'message'=>"normal exit: ".($normal_exit ? "[yes]" : "[no]"),
        );
        $errors = error_get_last();
        if ($errors && ($errors['type'] === E_ERROR ||
                $errors['type'] === E_WARNING ||
                $errors['type'] === E_PARSE ||
                $errors['type'] === E_CORE_WARNING ||
                $errors['type'] === E_COMPILE_ERROR ||
                $errors['type'] === E_COMPILE_WARNING ||
                $errors['type'] === E_USER_ERROR ||
                $errors['type'] === E_USER_WARNING ||
                $errors['type'] === E_RECOVERABLE_ERROR)
        ) {
            $log = array(
                'signal'=>0,
                'normal_exit'=>false,
                'error_type'=>self::getErrorType($errors['type']),
                'message'=>$errors['message'],
                'file'=>$errors['file'],
                'line'=>$errors['line']
            
            ) + $log;
        }
        $this->log($log);
    }

    /**
     * Get error message by error code.
     *
     * @param integer $type
     * @return string
     */
    protected static function getErrorType($type)
    {
        switch ($type) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }
        return "";
    }

    /**
     * register signal handler
     * @return void
     */
    private function registerSigHandler()
    {
        if(extension_loaded('pcntl')) 
            foreach($this->signals as $signal) {
            pcntl_signal($signal, array($this, 'defineSigHandler'), false);
        }
    }
    
    /**
     * define signal handler
     * @param integer $signal
     * @return void
     */
    public function defineSigHandler($signal = 0)
    {
        $this->log(
            array(
                'signal'=>$signal,
                'normal_exit'=>false,
                'run_time'=>time() - $this->startTime,
                'memory_usage'=>Util::fsize(memory_get_usage(true)),
                'process_no'=>posix_getpid(),
                'message'=>"Received signal: {$signal}",
            )
        );
        if($signal == SIGTERM) exit();
    }
}