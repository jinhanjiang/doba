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
    private $oci8 = NULL;

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
            $charset = isset($configs['charset']) ? $configs['charset'] : 'UTF8';
            $init = isset($configs['init']) ? $configs['init'] : "";
            $initExec = ($init ? preg_replace('/;$/', '', $init).';' : '') .'SET NAMES '.$charset.';';
            if (extension_loaded('pdo_mysql'))
            {
                $pdoConfigs = isset($configs['pdoConfigs']) && is_array($configs['pdoConfigs']) ? $configs['pdoConfigs'] : array();
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
        } else if('oracle' == $configs['db']) {
            $persistent = isset($configs['persistent']) && $configs['persistent'] === true ? TRUE : FALSE;
            $charset = isset($configs['charset']) ? $configs['charset'] : 'AL32UTF8';
            if (extension_loaded('oci8')) {
                $this->link = 4;
                $this->oci8 = $this->oci8 ? $this->oci8 : (
                    $persistent ? oci_pconnect(
                        $configs['dbUser'], 
                        $configs['dbPass'], 
                        "{$configs['dbHost']}/{$configs['dbName']}",
                        $charset
                    ) : oci_connect(
                        $configs['dbUser'], 
                        $configs['dbPass'], 
                        "{$configs['dbHost']}/{$configs['dbName']}",
                        $charset
                    )
                );
            } else {
                throw new \Exception('No extension for oracle were found');
            }
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
    public function query($sql, $params = array(), $options=array())
    {
        // Backward compatibility: the old signature was query($sql, $options).
        // Only two well-known option keys are accepted, so a named-binding array
        // such as [':name'=>'x'] is still treated as bindings.
        if (is_array($params) && empty($options) && $this->isLegacyQueryOptions($params)) {
            $options = $params;
            $params = array();
        }
        if (!is_array($params)) $params = array();
        if (!is_array($options)) $options = array();

        // Rebuild the version with values filled in for logging. Execution always
        // uses $sql + $params; interpolation is only cosmetic and must never make
        // the real query fail.
        $logSql = $sql;
        if (!empty($params)) {
            try {
                $logSql = $this->interpolate($sql, $params);
            } catch (\Exception $logEx) {
                $logSql = $sql;
            }
        }

        $result = NULL;
        try{
            $GLOBALS['QUERY_SQL'] = $logSql;
            switch($this->link) {
                case 1:// sqlite3
                    $result = $this->querySqlite($sql, $params, $options);
                    break;

                case 2:// pdo_mysql
                    $result = $this->queryPdo($sql, $params, $options);
                    break;

                case 3:// mysqli
                    $result = $this->queryMysqli($sql, $params, $options);
                    break;

                case 4:// oci8
                    $result = $this->queryOci($sql, $params, $options);
                    break;
            }
            $GLOBALS['QUERY_SQL'] = NULL;
            $this->retry = 0;
        } 
        catch(\Exception $ex) 
        {
            if('mysql' == $this->configs['db'] && $this->retry < 10 && 
                preg_match('/MySQL server has gone away/i', $ex->getMessage())) {
                $this->close(); $this->connect();
                $this->retry ++; 
                return $this->query($sql, $params, $options);
            }
            else
            {
                $this->wlog($logSql, $ex->getCode().':'.$ex->getMessage());
                $GLOBALS['QUERY_SQL'] = NULL;
                throw new \Exception($ex->getMessage(), (int)$ex->getCode());
            }
        }
        return $result;
    }

    private function querySqlite($sql, $params, $options)
    {
        $isRead = preg_match('/^\s*(SELECT|CALL|EXPLAIN|SHOW|PRAGMA)/i', $sql);

        if (empty($params)) {
            // The original logic remains unchanged
            if ($isRead) {
                $stmt = $this->sqlite3->query($sql); $result = array();
                if ($stmt) while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) $result[] = (object)$row;
                return $result;
            }
            $this->sqlite3->exec($sql);
            if (preg_match('/^\s*INSERT/i', $sql)) return $this->sqlite3->lastInsertRowID();
            return null; // UPDATE / DELETE / CREATE return null
        }

        $stmt = $this->sqlite3->prepare($sql);
        if (!$stmt) throw new \Exception('SQLite prepare failed: ' . $this->sqlite3->lastErrorMsg());
        $pos = 1;
        foreach ($params as $k => $v) {
            // SQLite3 positional parameters start from 1; do not rely on the
            // array keys being 0-based sequential (raw binds may be re-indexed).
            // Named parameters are accepted with or without the leading ':'.
            $key = is_int($k) ? $pos++ : (strpos($k, ':') === 0 ? $k : ':' . $k);
            $type = is_int($v) ? SQLITE3_INTEGER
                : (is_float($v) ? SQLITE3_FLOAT
                : (is_null($v) ? SQLITE3_NULL : SQLITE3_TEXT));
            $stmt->bindValue($key, $v, $type);
        }
        $res = $stmt->execute();
        if (!$res) throw new \Exception('SQLite execute failed: ' . $this->sqlite3->lastErrorMsg());

        if ($isRead) {
            $result = array();
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) $result[] = (object)$row;
            $res->finalize();
            return $result;
        }
        $res->finalize();
        if (preg_match('/^\s*INSERT/i', $sql)) return $this->sqlite3->lastInsertRowID();
        return null;
    }

    private function queryPdo($sql, $params, $options)
    {
        if (empty($params)) {
            $stmt = $this->pdomysql->query($sql);
            if (preg_match('/^\s*INSERT/i', $sql)) return $this->pdomysql->lastInsertId();
            if (preg_match('/^\s*(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
                return $stmt->fetchAll(\PDO::FETCH_CLASS);
            }
            return null;
        }

        $stmt = $this->pdomysql->prepare($sql);
        $pos = 1;
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $pos++ : (strpos($k, ':') === 0 ? $k : ':' . $k);
            $stmt->bindValue($key, $v, $this->pdoType($v));
        }
        $stmt->execute();

        if (preg_match('/^\s*INSERT/i', $sql)) return $this->pdomysql->lastInsertId();
        if (preg_match('/^\s*(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
            return $stmt->fetchAll(\PDO::FETCH_CLASS);
        }
        return null;
    }

    private function queryMysqli($sql, $params, $options)
    {
        if (empty($params)) {
            if ($stmt = $this->mysqli->query($sql)) {
                if (preg_match('/^\s*INSERT/i', $sql)) return $this->mysqli->insert_id;
                if (preg_match('/^\s*(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
                    $result = array();
                    while ($obj = $stmt->fetch_object()) $result[] = $obj;
                    return $result;
                }
                return null;
            }
            return null;
        }

        foreach ($params as $k => $v) {
            if (!is_int($k)) {
                throw new \Exception('MySQLi does not support named placeholders; use ? placeholders.');
            }
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) throw new \Exception('MySQLi prepare failed: ' . $this->mysqli->error);

        $types = '';
        $bind  = array($types);
        foreach ($params as $k => $v) {
            if (is_int($v))        $types .= 'i';
            elseif (is_float($v))  $types .= 'd';
            elseif (is_null($v))   $types .= 's'; // mysqli can also bind NULL with s
            else                   $types .= 's';
            $bind[] = &$params[$k];
        }
        $bind[0] = $types;
        call_user_func_array(array($stmt, 'bind_param'), $bind);

        $stmt->execute();

        if (preg_match('/^\s*INSERT/i', $sql)) {
            $id = $this->mysqli->insert_id;
            $stmt->close();
            return $id;
        }
        if (preg_match('/^\s*(SELECT|CALL|EXPLAIN|SHOW|DESC)/i', $sql)) {
            // get_result() needs mysqlnd; fall back to result_metadata() when the
            // extension is not available (common on older PHP builds).
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                $result = array();
                if ($res) while ($obj = $res->fetch_object()) $result[] = $obj;
                $stmt->close();
                return $result;
            }

            $meta = $stmt->result_metadata();
            if (!$meta) { $stmt->close(); return array(); }
            $row = array(); $refs = array();
            while ($field = $meta->fetch_field()) $refs[] = &$row[$field->name];
            call_user_func_array(array($stmt, 'bind_result'), $refs);
            $result = array();
            while ($stmt->fetch()) {
                $obj = new \stdClass();
                foreach ($row as $name => $value) $obj->$name = $value;
                $result[] = $obj;
            }
            $meta->free();
            $stmt->close();
            return $result;
        }
        $stmt->close();
        return null;
    }

    private function queryOci($sql, $params, $options)
    {
        if (empty($params)) {
            // The original logic remains unchanged
            $stmt = oci_parse($this->oci8, $sql);
            if ($stmt && oci_execute($stmt)) {
                if (preg_match('/^\s*INSERT/i', $sql) && !empty($options['sequence'])) {
                    return $this->currval($options['sequence']);
                }
                if (preg_match('/^\s*SELECT/i', $sql)) {
                    $result = array();
                    while (($row = oci_fetch_assoc($stmt)) !== false) $result[] = (object)$row;
                    return $result;
                }
            }
            return null;
        }

        $stmt = oci_parse($this->oci8, $sql);
        if (!$stmt) throw new \Exception('OCI parse failed');
        $pos = 1;
        foreach ($params as $k => $v) {
            $name = is_int($k) ? ':' . $pos++ : (strpos($k, ':') === 0 ? $k : ':' . $k);
            oci_bind_by_name($stmt, $name, $params[$k], 4000);
        }
        if (!oci_execute($stmt)) {
            $e = oci_error($stmt);
            throw new \Exception($e['message']);
        }
        if (preg_match('/^\s*INSERT/i', $sql) && !empty($options['sequence'])) {
            return $this->currval($options['sequence']);
        }
        if (preg_match('/^\s*SELECT/i', $sql)) {
            $result = array();
            while (($row = oci_fetch_assoc($stmt)) !== false) $result[] = (object)$row;
            return $result;
        }
        return null;
    }

    /**
     * Read the current value of an Oracle sequence after an INSERT.
     */
    private function currval($sequence) {
        $objs = $this->query("SELECT {$sequence}.CURRVAL from dual");
        if (is_array($objs)) {
            if (isset($objs[0]) && isset($objs[0]->CURRVAL)) return (int)$objs[0]->CURRVAL;
            if (isset($objs['CURRVAL'])) return (int)$objs['CURRVAL'];
        }
        return 0;
    }

    /**
     * Detect the legacy query($sql, $options) call style.
     * Only the two option keys used by the original SQL class qualify.
     */
    private function isLegacyQueryOptions($params) {
        if (!is_array($params) || empty($params)) return false;
        $known = array('noReturn', 'sequence');
        foreach (array_keys($params) as $key) {
            if (!is_string($key) || !in_array($key, $known, true)) return false;
        }
        return true;
    }

    private function pdoType($v) {
        if (is_int($v))   return \PDO::PARAM_INT;
        if (is_bool($v))  return \PDO::PARAM_BOOL;
        if (is_null($v))  return \PDO::PARAM_NULL;
        return \PDO::PARAM_STR;
    }

    /**
     *  Convert PHP values to SQL literals (for log display only)
     */
    private function toLiteral($v)
    {
        if (is_null($v))  return 'NULL';
        if (is_bool($v))  return $v ? '1' : '0';
        if (is_int($v) || is_float($v)) return (string)$v;
        // String: driver-aware quoted literal
        return $this->quote($v);
    }

    private function wlog($sql, $msg) {
        if (Constant::getConstant('TEMP_PATH') && is_dir(Constant::getConstant('TEMP_PATH'))) {
            $syslog = preg_replace('/\/$/', '', Constant::getConstant('TEMP_PATH')) . '/' . date('Ym') . '-doba.log';
            (!is_file($syslog)) && file_put_contents($syslog, '[' . date('Y-m') . ']SYSTEM LOG');
            file_put_contents($syslog, PHP_EOL . date('Y-m-d H:i:s') . '[2]['.$sql.']' . $msg, 8);
        }
        return true;
    }

    /**
     * Escape a value WITHOUT surrounding quotes (legacy semantics).
     * Callers wrap it themselves, e.g. "`name`='".$dao->escape($v)."'".
     * Use quote() when a ready-to-use SQL literal is needed.
     */
    public function escape($value) {
        if (is_null($value)) return NULL;
        if (is_numeric($value)) return $value;
        return str_replace(array("'", "\\"), array("''", "\\\\"), (string)$value);
    }

    /**
     * Quote a value as a complete SQL literal (driver-aware), for interpolation/logging.
     */
    public function quote($value) {
        if (is_null($value)) return 'NULL';
        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_bool($value)) return $value ? '1' : '0';

        switch ($this->link) {
            case 1: return "'" . \SQLite3::escapeString((string)$value) . "'";
            case 2: return $this->pdomysql->quote((string)$value);
            case 3: return "'" . $this->mysqli->real_escape_string((string)$value) . "'";
            case 4: return "'" . str_replace("'", "''", (string)$value) . "'"; // Oracle
        }
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }

    /**
     * Replace placeholders with literal values, only for logging / the legacy
     * findSql() one-argument contract. Never execute the returned string.
     *
     * Supports positional "?" and named ":name" placeholders. Placeholders
     * inside quoted strings and backtick identifiers are left untouched.
     */
    public function interpolate($sql, $params)
    {
        if (empty($params)) return $sql;

        $named = array(); $positional = array();
        foreach ($params as $k => $v) {
            if (is_int($k)) {
                $positional[] = $this->toLiteral($v);
            } else {
                $named[ltrim((string)$k, ':')] = $this->toLiteral($v);
            }
        }

        $sql = (string)$sql;
        $out = ''; $len = strlen($sql); $posIndex = 0;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            // Copy quoted literals and backtick identifiers without touching them.
            if ("'" === $ch || '"' === $ch || '`' === $ch) {
                $quote = $ch; $out .= $ch; $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    if ('\\' === $c && $i + 1 < $len) { $out .= $c . $sql[$i + 1]; $i += 2; continue; }
                    if ($c === $quote) {
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) { // doubled quote escape
                            $out .= $c . $sql[$i + 1]; $i += 2; continue;
                        }
                        $out .= $c;
                        break;
                    }
                    $out .= $c; $i++;
                }
                continue;
            }

            if ('?' === $ch) {
                $out .= array_key_exists($posIndex, $positional) ? $positional[$posIndex] : '?';
                $posIndex++;
                continue;
            }

            if (':' === $ch && preg_match('/^:([a-zA-Z_][a-zA-Z0-9_]*)/', substr($sql, $i), $m)) {
                if (array_key_exists($m[1], $named)) {
                    $out .= $named[$m[1]];
                    $i += strlen($m[0]) - 1;
                    continue;
                }
            }

            $out .= $ch;
        }
        return $out;
    }

    /**
     * begin()
     * 
     * Starting the transaction
     * @return 
     */
    public function begin()
    {
        $this->inTransaction = true;
        if(1 == $this->link) $this->query('BEGIN');
        else if(in_array($this->link, array(2, 3)))
        {
            $this->query('SET AUTOCOMMIT=0');
            $this->query('START TRANSACTION');
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
        $this->query('ROLLBACK');
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
        $this->query('COMMIT');
        if(in_array($this->link, array(2, 3))) {
            $this->query('SET AUTOCOMMIT=1');
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
        $this->oci8 = NULL;
        $this->link = 0;
    }

}
