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

class SQL{
    private $inTransaction = false;
    private $link = 0;
    
    private $sqlite3 = NULL;
    private $pdomysql = NULL;
    private $mysqli = NULL;

    private $configs = array();
    private $retry = 0;

    public $dbname = NULL;

    public function __construct($configs = array()) {
        $this->configs = $configs;
        $this->connect();
    }

    public function connect() {
        $configs = $this->configs;
        if('sqlite' == $configs['db']) {
            if(! isset($configs['dbfile']) || ! $configs['dbfile']) throw new \Exception('Sqlite database file address not set');
            $charset = isset($configs['charset']) ? $configs['charset'] : "UTF8";
            $journalMode = isset($configs['journalMode']) ? $configs['journalMode'] : "WAL";
            $init = isset($configs['init']) ? $configs['init'] : "";
            $initExec = ($init ? preg_replace('/;$/', '', $init).';' : '') 
                ."PRAGMA encoding = '{$charset}'; PRAGMA journal_mode = '{$journalMode}';";
            if (extension_loaded('sqlite3')) {
                $flags = isset($configs['flags']) ? $configs['flags'] : SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
                $dbpass = isset($configs['pass']) ? $configs['pass'] : "";
                $busyTimeout = isset($configs['busyTimeout']) ? $configs['busyTimeout'] : 5000;
                $this->link = 1;
                $this->sqlite3 = $this->sqlite3 ? $this->sqlite3 : new \SQLite3($configs['dbfile'], $flags, $dbpass);
                $this->sqlite3->busyTimeout($busyTimeout);
                $this->sqlite3->exec($initExec);
            } else {
                throw new \Exception('No extension for sqlite3 were found');
            }
            $this->dbname = $configs['db'];
        } else if('mysql' == $configs['db']) {
            $persistent = isset($configs['persistent']) && $configs['persistent'] === true ? TRUE : FALSE;
            $port = isset($configs['port']) ? $configs['port'] : 3306;
            $charset = isset($configs['charset']) ? $configs['charset'] : 'SET NAMES UTF8;';
            $init = isset($configs['init']) ? $configs['init'] : "";
            $initExec = ($init ? preg_replace('/;$/', '', $init).';' : '') .$charset.';';
            if (extension_loaded('pdo_mysql'))
            {
                $pdoConfigs = is_array($configs['pdoConfigs']) ? $configs['pdoConfigs'] : array();
                $this->link = 2;
                $this->pdomysql = $this->pdomysql ? $this->pdomysql : new \PDO(
                    "mysql:host={$configs['dbHost']};port={$port};dbname={$configs['dbName']}",
                    $configs['dbUser'],
                    $configs['dbPass'],
                    $pdoConfigs + array(
                        \PDO::ATTR_PERSISTENT => $persistent,
                        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    )
                );
                $this->pdomysql->query($initExec);
            }
            else if (extension_loaded('mysqli'))
            {
                $this->link = 3;
                \mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $dbHost = $persistent ? 'p:'.$configs['dbHost'] : $configs['dbHost'];
                $this->mysqli = $this->mysqli ? $this->mysqli 
                    : new \mysqli($dbHost, $configs['dbUser'], $configs['dbPass'], $configs['dbName'], $port);
                $this->mysqli->options(MYSQLI_INIT_COMMAND, $initExec);
            } else {
                throw new \Exception('No extension for pdo_mysql or mysqli were found');
            }
            $this->dbname = $configs['db'];
        } else {
            throw new \Exception('There are no database extension to load');
        }
    }

    /**
     * query($sql)
     * 
     * Execute SQL query
     * @param  string $sql SQL statement to execute
     * @return mix
     */
    public function query($sql, $options=array())
    {
        $result = NULL;
        try{
            $GLOBALS['QUERY_SQL'] = $sql;
            switch($this->link) {
                case 1:// sqlite3
                    if(preg_match('/^(SELECT|CALL|EXPLAIN|SHOW|PRAGMA)/i', $sql)) {
                        $stmt = $this->sqlite3->query($sql); $result = array();
                        if($stmt) while($row = $stmt->fetchArray(SQLITE3_ASSOC)){
                            $result[] = (object)$row;
                        }
                    } 
                    else 
                    {
                        $this->sqlite3->exec($sql);
                        if(preg_match('/^INSERT/i', $sql)) {
                            $result = $this->sqlite3->lastInsertRowID();
                        } else if(preg_match('/^(UPDATE|DELETE|CREATE)/i', $sql) || 1 == $options['noReturn']) {}
                        else {
                            $this->wlog($sql, 'Execute SQL exception. line: '.__LINE__);
                        }
                    }
                    break;

                case 2:// pdo_mysql
                    $stmt = $this->pdomysql->query($sql);
                    if(preg_match('/^INSERT/i', $sql)) $result = $this->pdomysql->lastinsertid();
                    else if(preg_match('/^(UPDATE|DELETE)/i', $sql) || 1 == $options['noReturn']){}
                    else if(preg_match('/^(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
                        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
                        $result = $stmt->fetchAll(\PDO::FETCH_CLASS);
                    }
                    else {
                        $this->wlog($sql, 'Execute SQL exception. line: '.__LINE__);
                    }
                    break;

                case 3:// mysqli
                    if($stmt = $this->mysqli->query($sql)){
                        if(preg_match('/^INSERT/i', $sql)) $result = $this->mysqli->insert_id;
                        else if(preg_match('/^(UPDATE|DELETE)/i', $sql) || 1 == $options['noReturn']){}
                        else if(preg_match('/^(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
                            $result = array();
                            while($obj = $stmt->fetch_object()) {
                                $result[] = $obj;
                            }
                        }
                        //$stmt->close(); //please do not set this call, it's will affect result return
                    }
                    else {
                        $this->wlog($sql, 'Execute SQL exception. line: '.__LINE__);
                    }
                    break;
            }
            $GLOBALS['QUERY_SQL'] = NULL;
            $this->retry = 0;
        } 
        catch(\Exception $ex) 
        {
            $emessage = $ex->getMessage();
            if('mysql' == $this->configs['db'] && $this->retry < 10 && 
                preg_match('/MySQL server has gone away/i', $ex->getMessage())) {
                $this->close(); $this->connect();
                $this->retry ++; 
                return $this->query($sql, $options);
            }
            else
            {
                $this->wlog($sql, $ex->getCode().':'.$ex->getMessage());
                $GLOBALS['QUERY_SQL'] = NULL;
                throw new \Exception($ex->getMessage(), $ex->getCode());
            }
        }
        return $result;
    }

    private function wlog($sql, $msg) {
        if (defined('TEMP_PATH') && is_dir(TEMP_PATH)) {
            $syslog = preg_replace('/\/$/', '', TEMP_PATH) . '/' . date('Ym') . '-doba.log';
            (!is_file($syslog)) && file_put_contents($syslog, '[' . date('Y-m') . ']SYSTEM LOG');
            file_put_contents($syslog, PHP_EOL . date('Y-m-d H:i:s') . '[2]['.$sql.']' . $msg, 8);
        }
        return true;
    }

    public function escape($value) {
        return is_null($value) ? NULL : (is_int($value) ? $value : str_replace(array("'"), array("''"), $value));
    }

    /**
     * begin()
     * 
     * Starting the transaction
     * @return 
     */
    public function begin()
    {
        $options = array('noReturn'=>1);
        $this->inTransaction = true;
        if(1 == $this->link) $this->query('BEGIN', $options);
        else if(in_array($this->link, array(2, 3)))
        {
            $this->query('SET AUTOCOMMIT=0', $options);
            $this->query('START TRANSACTION', $options);
        }
    }

    /**
     * rollback()
     *
     * Roll back the transaction
     * @return 
     */
    public function rollback()
    {
        $this->query('ROLLBACK', array('noReturn'=>1));
        $this->inTransaction = false;
    }

    /**
     * commit()
     * 
     * Commit the transaction
     * @return 
     */
    public function commit()
    {
        $options = array('noReturn'=>1);
        $this->query('COMMIT', $options);
        if(in_array($this->link, array(2, 3))) {
            $this->query('SET AUTOCOMMIT=1', $options);
        }
        $this->inTransaction = false;
    }

    /**
     * close()
     * 
     * Close links
     * @return
     */
    public function close()
    {
        switch($this->link)
        {
            case 1://sqlite3
                if($this->sqlite3) $this->sqlite3->close();
                break;
            case 3://mysqli
                if($this->mysqli) $this->mysqli->close();
                break;
        }
        $this->sqlite3 = NULL;
        $this->pdomysql = NULL;
        $this->mysqli = NULL;
        $this->link = 0;
    }

}
