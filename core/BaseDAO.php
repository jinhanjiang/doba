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

class BaseDAO {
    private static $instance = array();
    protected $db = NULL;
    protected $tbname;
    protected $originaltbname;
    protected $tbinfo = array();
    protected $sp = '';
    protected $lastQuerySql = '';
    protected $lastBind = array();
    private $link = 'default';
    private $tbpk = 'id';
    private $transactions = 0;

    protected function __construct($tbname, $options = array()) {
        $this->tbname = $tbname;
        $this->originaltbname = $tbname;
        $this->link = isset($options['link']) && $options['link'] ? $options['link'] : 'default';
        $this->tbpk = isset($options['tbpk']) && $options['tbpk'] ? $options['tbpk'] : 'id';
        $this->sp = isset($options['sp']) && $options['sp'] ? $options['sp'] : '';
        $this->tbinfo = isset($options['tbinfo']) && $options['tbinfo'] ? $options['tbinfo'] : $this->getTableInfo();
        $this->db = \Config::me()->getDb($this->link);
    }

    public static function me() {
        $class = get_called_class();
        if (!isset(self::$instance[$class])) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    public function resetdb() {
        $db = \Config::me()->getDb($this->link, array('reconnect' => true));
        if (is_array(self::$instance)) {
            foreach (self::$instance as $classobj) {
                $classobj->setdb($db);
            }
        }
        return true;
    }
    public function getdb() { return $this->db; }
    public function setdb($db = NULL) { $this->db = $db; return $this; }

    /**
     * Switch table. Table name is a structural identifier: must match a safe pattern.
     * Numeric suffix form (e.g. table_202401) is still supported via $this->sp.
     */
    public function table($table) {
        if (preg_match('/^\d+$/', $table)) {
            $this->tbname = $this->originaltbname . $this->sp . $table;
        } else {
            if (!$this->isSafeIdentifier($table)) {
                throw new \InvalidArgumentException("[BaseDAO] unsafe table name: {$table}");
            }
            $this->tbname = $table;
        }
        return $this;
    }

    public function getTableName() { return $this->tbname; }

    /**
     * Legacy entry: execute raw SQL without bindings. Kept for backward compatibility.
     * New code should call queryWithBind().
     */
    public function query($sql) {
        return $this->queryWithBind($sql, array());
    }

    /**
     * Parameterised query. $bind is a positional array: values map to '?' in order.
     */
    public function queryWithBind($sql, $bind = array()) {
        $this->assertBindArrayConsistency($bind);
        $sql = $this->formatSQL($sql);
        $this->lastQuerySql = $sql;
        $this->lastBind = $bind;
        return $this->db->query($sql, $bind);
    }

    /**
     * PDO/SQLite do not allow mixing ? and :name placeholders in one statement.
     */
    protected function assertBindArrayConsistency($bind) {
        if (empty($bind)) return;
        $hasNamed = false; $hasPositional = false;
        foreach ($bind as $key => $value) {
            if (is_int($key)) $hasPositional = true; else $hasNamed = true;
        }
        if ($hasNamed && $hasPositional) {
            throw new \InvalidArgumentException('[BaseDAO] mixed named and positional bindings are not supported');
        }
    }

    /**
     * Return the last SQL.
     *
     * The legacy contract is an executable SQL string (values filled in), so it
     * is kept as the default. Pass false to get the raw template with '?'.
     */
    public function sql($interpolated = true) {
        return $interpolated ? $this->sqlInterpolated() : $this->lastQuerySql;
    }

    /**
     * Return last SQL with placeholders interpolated, for logging / debugging only.
     * Do NOT execute the returned string.
     */
    public function sqlInterpolated() {
        if (!is_object($this->db) || empty($this->lastBind) || !method_exists($this->db, 'interpolate')) {
            return $this->lastQuerySql;
        }
        try {
            return $this->db->interpolate($this->lastQuerySql, $this->lastBind);
        } catch (\Exception $ex) {
            // Logging must never break a caller that only wants the SQL text.
            return $this->lastQuerySql;
        }
    }

    /* ============================== Table info ============================== */

    public function getTableInfo() {
        $fields = array();
        if ('sqlite' == $this->db->dbname) {
            $results = $this->db->query("PRAGMA TABLE_INFO(`{$this->tbname}`)");
            if (is_array($results)) foreach ($results as $result) {
                $colType = isset($result->type) ? $result->type : '';
                $type = $this->normalizeFieldType($colType);
                $fields[] = array(
                    'field' => $result->name,
                    'type' => $type,
                    'notnull' => $result->notnull ? true : false,
                    'default' => $result->dflt_value,
                    'pk' => $result->pk,
                    'autoincremnt' => $result->pk,
                );
                if ($result->pk) $this->tbpk = $result->name;
            }
        } else if ('mysql' == $this->db->dbname) {
            $results = $this->db->query("DESC `{$this->tbname}`");
            if (is_array($results)) foreach ($results as $result) {
                $row = is_object($result) ? get_object_vars($result) : (array)$result;
                $row = array_change_key_case($row, CASE_LOWER);
                $colType = isset($row['type']) ? $row['type'] : '';
                $type = $this->normalizeFieldType($colType);
                $pk = ('PRI' == strtoupper(isset($row['key']) ? $row['key'] : '')) ? true : false;
                $fields[] = array(
                    'field' => $row['field'],
                    'type' => $type,
                    'notnull' => 'NO' == strtoupper(isset($row['null']) ? $row['null'] : '') ? true : false,
                    'default' => array_key_exists('default', $row) ? $row['default'] : null,
                    'pk' => $pk,
                    'autoincremnt' => ('AUTO_INCREMENT' == strtoupper(isset($row['extra']) ? $row['extra'] : '')) ? true : false,
                );
                if ($pk) $this->tbpk = $row['field'];
            }
        }
        return $fields;
    }

    protected function normalizeFieldType($colType) {
        $colType = strval($colType);
        if (preg_match('/int/i', $colType)) return 'int';
        if (preg_match('/(float|double|decimal|real|numeric)/i', $colType)) return 'float';
        return 'string';
    }

    protected function castFieldValue($value, $type) {
        if (is_null($value)) {
            return null;
        }
        $type = in_array($type, array('int', 'float', 'string'), true) ? $type : 'string';
        if ($type === 'int') {
            if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
                return $value;
            }
            return (int)$value;
        }
        if ($type === 'float') {
            return (float)$value;
        }
        return (string)$value;
    }

    /* ============================== Insert ============================== */

    /**
     * insert data to database, support batch insert
     * @param array $params
     *   example1: ['title'=>'test1', 'status'=>1]
     *   example2: [['title','status'], ['test1', 1], ['test2', 1], ...]
     *   example3: [['title'=>'test1', 'status'=>1], ['title'=>'test2', 'status'=>1], ...]
     * @return int last insert id or pk value
     */
    public function insert($params) {
        if (empty($params)) return 0;

        $field = ''; $rows = array(); $bind = array(); $pkvalue = 0;

        if (!isset($params[0]) || !is_array($params[0])) {
            // example1: single associative row
            list($fieldstr, $holders, $rowBind, $pkvalue) = $this->getFieldAndValueSqlPart($params);
            $field = "(" . $fieldstr . ")";
            $rows[] = "(" . $holders . ")";
            $bind = array_merge($bind, $rowBind);
        } else if (isset($params[0][0])) {
            // example2: [fields, row1, row2, ...]
            $fields = $params[0];
            for ($i = 1, $plen = count($params); $i < $plen; $i++) {
                if (empty($params[$i])) continue;
                if (count($fields) !== count($params[$i])) continue;
                list($fieldstr, $holders, $rowBind, $rowPk) = $this->getFieldAndValueSqlPart(array_combine($fields, $params[$i]));
                if ($field === '') $field = "(" . $fieldstr . ")";
                $rows[] = "(" . $holders . ")";
                $bind = array_merge($bind, $rowBind);
                if ($rowPk) $pkvalue = $rowPk;
            }
        } else {
            // example3: list of associative rows
            foreach ($params as $key => $param) {
                if (!is_numeric($key) || !is_array($param)) continue;
                list($fieldstr, $holders, $rowBind, $rowPk) = $this->getFieldAndValueSqlPart($param);
                if ($field === '') $field = "(" . $fieldstr . ")";
                $rows[] = "(" . $holders . ")";
                $bind = array_merge($bind, $rowBind);
                if ($rowPk) $pkvalue = $rowPk;
            }
        }
        // No matching table fields: do not emit `INSERT INTO t () VALUES ()`,
        // which is invalid on SQLite (the old MySQL-only behaviour inserted a
        // row made of column defaults, which is almost never what the caller meant).
        if (empty($rows) || '()' === $field) return 0;
        $value = implode(",", $rows);

        $insertReplace = isset($params['_INSERT_REPLACE']) && true === $params['_INSERT_REPLACE'];
        if ($insertReplace) {
            $sql = "REPLACE INTO `{$this->tbname}` {$field} VALUES {$value}";
        } else {
            $insertIgnore = (isset($params['_INSERT_IGNORE']) && true === $params['_INSERT_IGNORE'])
                || (isset($params['_INSERT_IGONRE']) && true === $params['_INSERT_IGONRE']);
            $insertIgnore = $insertIgnore ? 'IGNORE ' : '';
            $sql = "INSERT {$insertIgnore}INTO `{$this->tbname}` {$field} VALUES {$value}";
        }
        $lastInsertId = $this->queryWithBind($sql, $bind);
        if ($pkvalue) return $pkvalue;
        return $lastInsertId ? $lastInsertId : 0;
    }

    /**
     * Build `field list` + `? placeholders` + `bind values` for one row.
     * @return array [fieldstr, holders, bind, pkvalue]
     */
    private function getFieldAndValueSqlPart($params) {
        $fieldstr = ''; $holders = ''; $bind = array(); $fieldlen = 0; $pkvalue = 0;
        foreach ($this->tbinfo as $tbinfo) {
            if (!isset($params[$tbinfo['field']])) continue;
            if ($tbinfo['autoincremnt']) {
                if ('' === $params[$tbinfo['field']]) continue;
            } else if ($tbinfo['pk']) {
                if ('' !== $params[$tbinfo['field']]) $pkvalue = $params[$tbinfo['field']];
            }

            $raw = $params[$tbinfo['field']];
            $fieldType = isset($tbinfo['type']) ? $tbinfo['type'] : 'string';
            if (!in_array($fieldType, array('int', 'float', 'string'), true)) {
                $fieldType = $this->normalizeFieldType($fieldType);
            }

            $fieldstr .= ($fieldlen > 0 ? ',' : '') . '`' . $tbinfo['field'] . '`';

            // Legacy behaviour: CURRENT_TIMESTAMP was replaced by the current PHP time.
            if (is_string($raw) && 'CURRENT_TIMESTAMP' == strtoupper($raw)) {
                $holders .= ($fieldlen > 0 ? ',' : '') . '?';
                $bind[] = date('Y-m-d H:i:s');
            } else {
                // Preserve the old insert rule: an empty value on an int/float column
                // with a column default uses that default instead of 0.
                $cast = (is_string($raw) && '' === $raw
                        && in_array($fieldType, array('int', 'float'), true)
                        && !is_null($tbinfo['default']))
                    ? $tbinfo['default']
                    : $this->castFieldValue($raw, $fieldType);
                $holders .= ($fieldlen > 0 ? ',' : '') . '?';
                $bind[] = is_null($cast) ? null : $cast;
            }
            $fieldlen++;
        }
        return array($fieldstr, $holders, $bind, $pkvalue);
    }

    /* ============================== Update ============================== */

    public function change($pk = 0, $params = array()) {
        $data = array(); $bind = array();
        $columns = array_column($this->tbinfo, 'field');
        foreach ($params as $field => $value) {
            if (!in_array($field, $columns, true)) continue;
            if (is_null($value)) {
                $data[] = "`{$field}`=NULL";
            } else if (is_array($value) && 2 == count($value) && in_array($value[0], array('+', '-'), true)) {
                // incremental update: ['quantity' => ['+', 10]]
                $op = $value[0] === '+' ? '+' : '-';
                $data[] = "`{$field}`=`{$field}`{$op}?";
                $bind[] = $this->normalizeBind($value[1]);
            } else {
                $data[] = "`{$field}`=?";
                $bind[] = $this->normalizeBind($value);
            }
        }
        if (empty($data)) {
            trigger_error("[BaseDAO] change() has no updatable fields, skipped. Table `{$this->tbname}`"
                . (is_array($pk) ? ' (condition array)' : ' (pk ' . var_export($pk, true) . ')'), E_USER_WARNING);
            return is_array($pk) ? 0 : $pk;
        }
        $setConds = implode(',', $data);

        $where = ''; $id = 0;
        if (is_array($pk)) {
            $condBind = array();
            $condSql = $this->where($pk, $condBind);
            $where = $condSql ? "WHERE " . $condSql : '';
            $bind = array_merge($bind, $condBind);
        } else {
            $where = "WHERE `{$this->tbpk}`=?";
            // The legacy code always quoted the primary key ('123'), so keep the
            // string comparison for char/varchar keys instead of binding an int.
            $bind[] = is_null($pk) ? '' : (string)$pk;
            $id = $pk;
        }
        $this->queryWithBind("UPDATE `{$this->tbname}` SET {$setConds} {$where}", $bind);
        return $id;
    }

    /* ============================== Select / Delete ============================== */

    public function get($pk = 0) {
        if (empty($pk)) return NULL;
        $datas = $this->finds(array($this->tbpk => $pk, 'limit' => 1));
        return isset($datas[0]) ? (object)$datas[0] : NULL;
    }

    public function delete($pk = 0) {
        if (is_array($pk)) {
            $bind = array();
            $condSql = $this->where($pk, $bind);
            $where = $condSql ? "WHERE " . $condSql : '';
        } else {
            // Same reasoning as change(): the legacy SQL compared the key as a string.
            $bind = array(is_null($pk) ? '' : (string)$pk);
            $where = "WHERE `{$this->tbpk}`=?";
        }
        $this->queryWithBind("DELETE FROM `{$this->tbname}` {$where}", $bind);
    }

    public function finds($params) {
        $bind = array();
        $sql = $this->buildFindSql($params, $bind);
        return $this->queryWithBind($sql, $bind);
    }

    /**
     * Build SELECT SQL.
     *
     * Backward-compatible call styles:
     *   $sql = $dao->findSql($params);
     *       Legacy: returns executable SQL with values interpolated.
     *   $sql = $dao->findSql($params, $bind);
     *       New: returns the SQL template (with ? placeholders) and fills $bind.
     *   list($sql, $bind) = $dao->findSqlWithBind($params);
     *       New: explicit [sql, bind] pair.
     */
    public function findSql($params, &$bind = null) {
        $paramsBind = array();
        $sql = $this->buildFindSql($params, $paramsBind);
        if (func_num_args() > 1) {
            $bind = $paramsBind;
            return $sql;
        }
        // Legacy one-argument call: keep returning a directly executable SQL string.
        return $this->interpolateSql($sql, $paramsBind);
    }

    /**
     * Same as findSql($params, $bind), always returns [sql, bind].
     */
    public function findSqlWithBind($params) {
        $bind = array();
        $sql = $this->buildFindSql($params, $bind);
        return array($sql, $bind);
    }

    /**
     * Interpolate bind values into a template, for the legacy findSql() contract.
     */
    protected function interpolateSql($sql, $bind) {
        if (empty($bind)) return $sql;
        if (is_object($this->db) && method_exists($this->db, 'interpolate')) {
            try {
                return $this->db->interpolate($sql, $bind);
            } catch (\Exception $ex) {
                return $sql;
            }
        }
        return $sql;
    }

    public function getLastBind() {
        return $this->lastBind;
    }

    protected function buildFindSql($params, &$bind) {
        $bind = array();
        $groupByStr = $this->safeGroupBy($params);
        $orderByStr = $this->safeOrderBy($params);
        $limitStr   = $this->safeLimit($params);
        $forceIndexStr = $this->safeForceIndex($params);

        // selectCase: developer-authored select list; placeholders + bind are supported.
        $selectCase = '*'; $selectBind = array();
        if (isset($params['selectCase']) && '' !== $params['selectCase'] && null !== $params['selectCase']) {
            $selectSpec = $params['selectCase'];
            if (is_array($selectSpec)) {
                $selectCase = isset($selectSpec['sql']) ? $selectSpec['sql'] : '';
                $selectBind = isset($selectSpec['bind']) ? (array)$selectSpec['bind'] : array();
            } else {
                $selectCase = $selectSpec;
                if (isset($params['selectBind'])) $selectBind = (array)$params['selectBind'];
                else if (isset($params['selectCaseBind'])) $selectBind = (array)$params['selectCaseBind'];
            }
            if (!$this->allowRawSql('selectCase', $selectCase, !empty($selectBind))) {
                $this->logIgnoredCondition('selectCase', 'raw', $selectCase);
                $selectCase = '*'; $selectBind = array();
            } else {
                $this->assertBindCount($selectCase, $selectBind);
            }
        }
        $this->appendBind($bind, $selectBind);

        // joinConds: string or ['sql'=>..., 'bind'=>...] fragments.
        $joinConds = array(); $joinBind = array();
        $params['joinConds'] = isset($params['joinConds']) ? (array)$params['joinConds'] : array();
        // A single ['sql'=>..., 'bind'=>...] spec is also accepted.
        $joinCondList = isset($params['joinConds']['sql']) ? array($params['joinConds']) : $params['joinConds'];
        foreach ($joinCondList as $jc) {
            $jcSql = $jc; $jcBind = array();
            if (is_array($jc)) {
                $jcSql = isset($jc['sql']) ? $jc['sql'] : '';
                $jcBind = isset($jc['bind']) ? (array)$jc['bind'] : array();
            }
            if (!is_string($jcSql) || !preg_match('/(left|inner|right)\s*join/i', $jcSql)) continue;
            if (!$this->allowRawSql('joinConds', $jcSql, !empty($jcBind))) {
                $this->logIgnoredCondition('joinConds', 'raw', $jcSql);
                continue;
            }
            $this->assertBindCount($jcSql, $jcBind);
            $joinConds[] = $jcSql;
            $this->appendBind($joinBind, $jcBind);
        }
        $params['joinConds'] = $joinConds ? implode(' ', $joinConds) : '';
        $params['joinPrefix'] = $params['joinConds'] ? "`a`." : '';
        $this->appendBind($bind, $joinBind);

        $where = $this->where($params, $bind);

        $tbname = "`{$this->tbname}`" . ($params['joinPrefix'] ? " AS `a`" : "");
        $sql = "SELECT {$selectCase} FROM {$tbname} {$forceIndexStr} {$params['joinConds']} "
             . ($where ? "WHERE " . $where : "");
        $sql .= " {$groupByStr} {$orderByStr} {$limitStr}";
        return $sql;
    }

    /* ============================== Where builder ============================== */

    protected function where($params, &$bind = array()) {
        $prefix = isset($params['joinPrefix']) ? $params['joinPrefix'] : '';
        $sql = ''; $fields = array_column($this->tbinfo, 'field');

        foreach ($fields as $field) {
            if (isset($params[$field])) {
                $value = $params[$field];
                if (is_array($value)) {
                    $op = isset($value['op']) ? $value['op'] : 'eq';
                    $nvalue = isset($value['value']) ? $value['value'] : null;
                    $isRaw = in_array(strtolower($op), array('custom', 'raw'), true);
                    $rawBind = $isRaw ? $this->extractBind($value) : array();

                    if ($isRaw) {
                        if (!$this->allowRawSql($field, $nvalue, !empty($rawBind))) {
                            $this->logIgnoredCondition($field, $op, $nvalue);
                            continue;
                        }
                        if (!$this->isAcceptableValue($op, $nvalue)) {
                            $this->logIgnoredCondition($field, $op, $nvalue);
                            continue;
                        }
                        $this->assertBindCount($nvalue, $rawBind);
                    } else if (!empty($rawBind)) {
                        trigger_error("[BaseDAO] 'bind' ignored on non-raw condition `{$field}`", E_USER_WARNING);
                        $rawBind = array();
                    }

                    if ($this->isAcceptableValue($op, $nvalue)) {
                        $andOr = isset($value['and']) && false === $value['and'] ? 'OR' : 'AND';
                        $sql .= ' ' . $this->sqlPart($field, $op, $nvalue, $prefix, $andOr, $bind, $rawBind);
                    } else {
                        $this->logIgnoredCondition($field, $op, $nvalue);
                    }
                } else if (is_scalar($value) && '' !== $value) {
                    $sql .= ' ' . $this->sqlPart($field, 'eq', $value, $prefix, 'AND', $bind);
                }
            }

            foreach (array('Like' => 'like', 'LLike' => 'llike', 'RLike' => 'rlike',
                       'Geq' => 'geq', 'Gt' => 'gt', 'Leq' => 'leq', 'Lt' => 'lt',
                       'Neq' => 'neq') as $suffix => $op) {
                $key = $field . $suffix;
                if (isset($params[$key]) && '' !== $params[$key]) {
                    $sql .= ' ' . $this->sqlPart($field, $op, $params[$key], $prefix, 'AND', $bind);
                }
            }

            $inKey = $field . 'In';
            if (isset($params[$inKey]) && !is_null($params[$inKey])) {
                if ($this->isEmptyInListValue($params[$inKey])) {
                    // An empty IN set means "match nothing" (an empty NOT IN set
                    // means "match everything", so it is skipped below).
                    $this->logIgnoredCondition($inKey, 'in', $params[$inKey]);
                    $sql .= ' AND 1=0';
                } else {
                    $sql .= ' ' . $this->sqlPart($field, 'in', $params[$inKey], $prefix, 'AND', $bind);
                }
            }

            $ninKey = $field . 'Nin';
            if (isset($params[$ninKey]) && !is_null($params[$ninKey])) {
                if ($this->isEmptyInListValue($params[$ninKey])) {
                    $this->logIgnoredCondition($ninKey, 'nin', $params[$ninKey]);
                } else {
                    $sql .= ' ' . $this->sqlPart($field, 'nin', $params[$ninKey], $prefix, 'AND', $bind);
                }
            }
        }

        if ($prefix && !empty($params['joinWhere'])) {
            if (is_array($params['joinWhere'])) {
                // A single ['sql'=>..., 'bind'=>...] spec is also accepted.
                $joinWhereList = isset($params['joinWhere']['sql']) ? array($params['joinWhere']) : $params['joinWhere'];
                foreach ($joinWhereList as $joinWhere) {
                    if (is_array($joinWhere)) {
                        // New form: ['sql'=>'b.name LIKE ?', 'bind'=>['%x%']]
                        if (isset($joinWhere['sql'])) {
                            $rawSql = $joinWhere['sql'];
                            $rawBind = isset($joinWhere['bind']) ? (array)$joinWhere['bind'] : array();
                            if (!$this->allowRawSql('joinWhere', $rawSql, !empty($rawBind))) {
                                $this->logIgnoredCondition('joinWhere', 'raw', $rawSql);
                                continue;
                            }
                            $this->assertBindCount($rawSql, $rawBind);
                            $sql .= ' ' . $this->sqlPart('', 'raw', $rawSql, '', 'AND', $bind, $rawBind);
                            continue;
                        }

                        $cntlen = count($joinWhere); $op = '='; $rawBind = array();
                        if (2 == $cntlen) list($field, $value) = $joinWhere;
                        else if (3 == $cntlen) list($field, $op, $value) = $joinWhere;
                        else if (4 == $cntlen) { list($field, $op, $value, $rawBind) = $joinWhere; $rawBind = (array)$rawBind; }
                        else continue;

                        $p = ''; $field = preg_replace('/`/', '', $field);
                        if (false !== strpos($field, '.')) {
                            list($p, $field) = explode('.', $field);
                            $p = "`{$p}`.";
                        }

                        $isRaw = in_array(strtolower($op), array('custom', 'raw'), true);
                        if ($isRaw) {
                            if (!$this->allowRawSql($field, $value, !empty($rawBind))) {
                                $this->logIgnoredCondition($field, $op, $value);
                                continue;
                            }
                            $this->assertBindCount($value, $rawBind);
                        } else if (!empty($rawBind)) {
                            trigger_error("[BaseDAO] 'bind' ignored on non-raw joinWhere condition `{$field}`", E_USER_WARNING);
                            $rawBind = array();
                        }

                        if ($this->isAcceptableValue($op, $value)) {
                            $sql .= ' ' . $this->sqlPart($field, $op, $value, $p, 'AND', $bind, $rawBind);
                        } else {
                            $this->logIgnoredCondition($field, $op, $value);
                        }
                    } else if (is_string($joinWhere)) {
                        if (!$this->allowRawSql('joinWhere', $joinWhere)) {
                            $this->logIgnoredCondition('joinWhere', 'raw', $joinWhere);
                            continue;
                        }
                        $sql .= ' AND ' . preg_replace('/^\s*(and|or)\s+/i', '', $joinWhere);
                    }
                }
            } else if (is_string($params['joinWhere'])) {
                $rawBind = isset($params['joinWhereBind']) ? (array)$params['joinWhereBind'] : array();
                if (!$this->allowRawSql('joinWhere', $params['joinWhere'], !empty($rawBind))) {
                    $this->logIgnoredCondition('joinWhere', 'raw', $params['joinWhere']);
                } else {
                    $this->assertBindCount($params['joinWhere'], $rawBind);
                    $sql .= ' AND ' . preg_replace('/^\s*(and|or)\s+/i', '', $params['joinWhere']);
                    $this->appendBind($bind, $rawBind);
                }
            }
        }

        // Top-level raw WHERE, e.g.:
        // ['where'=>'(`mallSpu` IS NULL OR `mallSpu`=?)', 'bind'=>[$spu]]
        if (array_key_exists('where', $params)) {
            $whereSpec = $params['where'];
            $whereSql = ''; $whereBind = array();
            if (is_array($whereSpec)) {
                $whereSql = isset($whereSpec['sql']) ? $whereSpec['sql'] : '';
                $whereBind = isset($whereSpec['bind']) ? (array)$whereSpec['bind'] : array();
            } else {
                $whereSql = $whereSpec;
            }
            if (empty($whereBind)) {
                if (isset($params['bind'])) $whereBind = (array)$params['bind'];
                else if (isset($params['whereBind'])) $whereBind = (array)$params['whereBind'];
            }
            if (is_string($whereSql) && '' !== trim($whereSql)) {
                if (!$this->allowRawSql('where', $whereSql, !empty($whereBind))) {
                    $this->logIgnoredCondition('where', 'raw', $whereSql);
                } else {
                    $this->assertBindCount($whereSql, $whereBind);
                    $whereSql = preg_replace('/^\s*(and|or)\s+/i', '', trim($whereSql));
                    $sql = ('' === $sql)
                        ? $whereSql
                        : '(' . preg_replace('/^\s*(and|or)/i', '', $sql) . ') AND (' . $whereSql . ')';
                    $this->appendBind($bind, $whereBind);
                }
            }
        }

        return preg_replace('/^\s*(and|or)/i', '', $sql);
    }

    /* ============================== Condition helpers ============================== */

    protected function sqlPart($field, $op, $value, $prefix, $andOr = 'and', &$bind = array(), $rawBind = array()) {
        $andOr = strtoupper($andOr); $op = strtolower($op); $sql = '';
        $field = $andOr . ' ' . $prefix . "`{$field}`";
        switch ($op) {
            case 'custom':
            case 'raw':
                // Keep placeholders in the SQL; append their values in SQL order.
                $value = preg_replace('/^\s*(and|or)\s+/i', '', (string)$value);
                $sql = "{$andOr} ({$value})";
                $this->appendBind($bind, $rawBind);
                break;

            case '=': case 'eq':
                $sql = "{$field}=?"; $bind[] = $this->normalizeBind($value); break;
            case '>': case 'gt':
                $sql = "{$field}>?"; $bind[] = $this->normalizeBind($value); break;
            case '>=': case 'geq':
                $sql = "{$field}>=?"; $bind[] = $this->normalizeBind($value); break;
            case '<': case 'lt':
                $sql = "{$field}<?";
                $bind[] = $this->normalizeBind($value);
                break;
            case '<=': case 'leq':
                $sql = "{$field}<=?"; $bind[] = $this->normalizeBind($value); break;
            case '<>': case '!=': case 'neq':
                $sql = "{$field}!=?"; $bind[] = $this->normalizeBind($value); break;

            case 'in':
            case 'nin':
            case 'not in':
                // The legacy API allowed a comma separated string here, e.g.
                // ['idIn'=>'1,2,3'] or ['id'=>['op'=>'in','value'=>"1,2,3"]].
                // Values are now bound one by one instead of being pasted raw.
                $values = $this->normalizeInValues($value);
                if (empty($values)) return '';
                $holders = implode(',', array_fill(0, count($values), '?'));
                $negation = ('in' === $op) ? '' : 'NOT ';
                $sql = "{$field} {$negation}IN ({$holders})";
                foreach ($values as $v) $bind[] = $this->normalizeBind($v);
                break;

            case 'like':
                $sql = "{$field} LIKE ?"; $bind[] = '%' . (string)$value . '%'; break;
            case 'llike':
                $sql = "{$field} LIKE ?"; $bind[] = '%' . (string)$value; break;
            case 'rlike':
                $sql = "{$field} LIKE ?"; $bind[] = (string)$value . '%'; break;
        }
        return $sql;
    }

    /**
     * Optional helper for callers that want '%' / '_' in a LIKE value treated
     * literally. The framework itself does NOT apply it: binding a LIKE value
     * already prevents injection, and legacy behaviour kept user wildcards.
     */
    protected function escapeLike($v) {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string)$v);
    }

    protected function normalizeBind($v) {
        if (is_null($v)) return null;
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_int($v) || is_float($v)) return $v;
        return (string)$v;
    }

    protected function inList($values) {
        return implode(',', array_map(function ($v) {
            if (is_int($v) || is_float($v)) return $v;
            if (is_null($v)) return 'NULL';
            if (is_bool($v)) return $v ? 1 : 0;
            return "'" . $this->escape($v) . "'";
        }, $values));
    }

    /**
     * Legacy IN / NOT IN operand support.
     *
     * Historically the value could be a comma separated string:
     *   ['idIn'=>'1,2,3']
     *   ['idIn'=>'"a","b"']
     *   ['id'=>['op'=>'in','value'=>"1,2,3"]]
     * Those strings were pasted into the SQL as-is. Keep them working, but bind
     * every element instead. A real array is still the preferred form.
     */
    protected function normalizeInValues($value) {
        if (is_array($value)) return array_values($value);
        if (!is_string($value)) return array($value);

        $value = trim($value);
        if ('' === $value) return array();

        $values = array();
        foreach ($this->splitInList($value) as $token) {
            $token = trim($token);
            if ('' === $token) continue;
            $values[] = $this->unquoteInToken($token);
        }
        return $values;
    }

    /**
     * Split a legacy comma separated list, honouring single/double quoted items.
     */
    protected function splitInList($value) {
        $tokens = array(); $buffer = ''; $quote = null; $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if (null !== $quote) {
                if ('\\' === $ch && $i + 1 < $len) { $buffer .= $ch . $value[$i + 1]; $i++; continue; }
                if ($ch === $quote) {
                    if ($i + 1 < $len && $value[$i + 1] === $quote) { // doubled quote
                        $buffer .= $ch . $value[$i + 1]; $i++; continue;
                    }
                    $quote = null; $buffer .= $ch; continue;
                }
                $buffer .= $ch; continue;
            }
            if ("'" === $ch || '"' === $ch) { $quote = $ch; $buffer .= $ch; continue; }
            if (',' === $ch) { $tokens[] = $buffer; $buffer = ''; continue; }
            $buffer .= $ch;
        }
        $tokens[] = $buffer;
        return $tokens;
    }

    /**
     * Remove the surrounding quotes of one legacy IN list item, if present.
     */
    protected function unquoteInToken($token) {
        $len = strlen($token);
        if ($len < 2) return $token;

        $first = $token[0]; $last = $token[$len - 1];
        if (!(($first === "'" && $last === "'") || ($first === '"' && $last === '"'))) {
            return $token;
        }
        $inner = substr($token, 1, $len - 2);
        $inner = str_replace(array('\\' . $first, $first . $first), $first, $inner);
        return $inner;
    }

    protected function isEmptyInListValue($value) {
        if (is_array($value)) return empty($value);
        if (is_string($value)) return '' === trim($value);
        return false;
    }

    protected function isAcceptableValue($op, $value) {
        if (in_array(strtolower($op), array('in', 'nin', 'not in'), true)) {
            // Arrays are preferred; the legacy comma separated string form is
            // still accepted (see normalizeInValues()).
            if (is_array($value)) return !empty($value);
            return is_scalar($value) && '' !== $value;
        }
        return is_scalar($value) && '' !== $value;
    }

    protected function logIgnoredCondition($field, $op, $value) {
        $type = is_array($value) ? 'array(' . count($value) . ')' : gettype($value);
        trigger_error(
            "[BaseDAO] condition on `{$field}` ignored: op={$op} does not accept a {$type} value",
            E_USER_WARNING
        );
    }

    /* ============================== Raw SQL gate ============================== */

    /**
     * Whitelist gate for raw SQL fragments.
     *
     * Compatibility rules:
     *   - selectCase / joinConds: structural fragments, always allowed when safe.
     *   - raw/custom/where/joinWhere with 'bind': always allowed (values are parameterised).
     *   - raw/custom/where/joinWhere without 'bind':
     *       DAO_ALLOW_RAW_SQL === null  -> allowed for backward compatibility (warns once)
     *       DAO_ALLOW_RAW_SQL === true  -> allowed
     *       DAO_ALLOW_RAW_SQL === false -> rejected
     */
    protected function allowRawSql($field, $raw, $hasBind = false) {
        if (!is_string($raw) || '' === trim($raw)) return false;
        if (!$this->isSafeRawFragment($raw)) return false;

        // Select lists and joins are developer-authored structural SQL; findCount()
        // depends on selectCase, so keep these working even when raw is disabled.
        if (in_array($field, array('selectCase', 'joinConds'), true)) return true;

        // Placeholder + bind path is the safe path and is always accepted.
        if ($hasBind) return true;

        $enabled = Constant::getConstant('DAO_ALLOW_RAW_SQL');
        if (null === $enabled) {
            $this->warnLegacyRaw($field, $raw);
            return true;
        }
        return (bool)$enabled;
    }

    /**
     * Block multi-statement, comments and DDL/DML. Quoted literals are removed
     * first so a keyword inside a string value does not reject a valid fragment.
     */
    protected function isSafeRawFragment($raw) {
        $stripped = $this->stripQuotedStrings((string)$raw);
        if (preg_match('/;|--|\/\*|\*\/|#/i', $stripped)) return false;
        if (preg_match('/\b(union|drop|delete|update|insert|alter|create|truncate|exec|grant)\b/i', $stripped)) return false;
        return true;
    }

    /**
     * Remove single/double quoted literals, keeping the SQL structure.
     */
    protected function stripQuotedStrings($sql) {
        $sql = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", "''", (string)$sql);
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.|"")*"/s', '""', $sql);
        return $sql;
    }

    /**
     * Read ['bind'=>...] / ['bindings'=>...] from a raw condition spec.
     */
    protected function extractBind($spec) {
        if (!is_array($spec)) return array();
        if (isset($spec['bind']) && is_array($spec['bind'])) return $spec['bind'];
        if (isset($spec['bindings']) && is_array($spec['bindings'])) return $spec['bindings'];
        return array();
    }

    /**
     * Append bindings, preserving named keys and normalizing positional order.
     */
    protected function appendBind(&$bind, $values) {
        foreach ((array)$values as $key => $value) {
            if (is_int($key)) {
                $bind[] = $this->normalizeBind($value);
            } else {
                $key = (string)$key;
                if ('' !== $key && ':' !== $key[0]) $key = ':' . $key;
                $bind[$key] = $this->normalizeBind($value);
            }
        }
    }

    /**
     * Validate that the bindings match the placeholders in a raw fragment.
     */
    protected function assertBindCount($sql, $bind) {
        if (empty($bind) || !is_string($sql)) return;

        $hasNamed = false; $hasPositional = false;
        foreach ($bind as $key => $value) {
            if (is_int($key)) $hasPositional = true; else $hasNamed = true;
        }
        if ($hasNamed && $hasPositional) {
            throw new \InvalidArgumentException('[BaseDAO] mixed named and positional bindings are not supported');
        }

        if ($hasPositional) {
            $count = $this->countPlaceholders($sql);
            if ($count !== count($bind)) {
                throw new \InvalidArgumentException("[BaseDAO] placeholder count ({$count}) does not match bind count (" . count($bind) . "): {$sql}");
            }
            return;
        }

        foreach ($bind as $key => $value) {
            $name = ltrim((string)$key, ':');
            if (!preg_match('/:' . preg_quote($name, '/') . '\b/', $sql)) {
                throw new \InvalidArgumentException("[BaseDAO] named placeholder :{$name} not found in SQL: {$sql}");
            }
        }
    }

    protected function countPlaceholders($sql) {
        return substr_count($this->stripQuotedStrings((string)$sql), '?');
    }

    private static $legacyRawWarnings = array();
    protected function warnLegacyRaw($field, $raw) {
        $key = $field . ':' . md5((string)$raw);
        if (isset(self::$legacyRawWarnings[$key])) return;
        self::$legacyRawWarnings[$key] = true;
        trigger_error("[BaseDAO] legacy raw SQL on `{$field}` (no bindings). Pass 'bind' with '?' placeholders to make it explicit.", E_USER_DEPRECATED);
    }

    /* ============================== Identifier guards ============================== */

    protected function isSafeIdentifier($name) {
        // Word chars, dots (schema-qualified names) and hyphens are accepted;
        // backticks/quotes/semicolons are not, so the name cannot break out of
        // the backticks the query builder wraps it in.
        return (bool)preg_match('/^[\w.\-]+$/', (string)$name);
    }

    protected function safeGroupBy($params) {
        if (empty($params['groupBy'])) return '';
        if (!is_string($params['groupBy']) || !$this->isSafeRawFragment($params['groupBy'])) {
            $this->logIgnoredCondition('groupBy', 'raw', $params['groupBy']);
            return '';
        }
        // Keywords/functions are developer supplied; only multi-statements and
        // comments are rejected, so expressions like DATE(createTime) keep working.
        return "GROUP BY {$params['groupBy']}";
    }

    protected function safeOrderBy($params) {
        if (empty($params['orderBy'])) return '';
        if (!is_string($params['orderBy']) || !$this->isSafeRawFragment($params['orderBy'])) {
            $this->logIgnoredCondition('orderBy', 'raw', $params['orderBy']);
            return '';
        }
        // RAND(), FIELD(id,3,1,2), IFNULL(a,0) ASC, systemCode+0 DESC ... are all
        // valid legacy order clauses, so only reject unsafe fragments here.
        return "ORDER BY {$params['orderBy']}";
    }

    protected function safeLimit($params) {
        if (empty($params['limit']) || !is_scalar($params['limit'])) return '';
        if (!preg_match('/^\d+(,\d+)?$/', (string)$params['limit'])) return '';
        return "LIMIT {$params['limit']}";
    }

    protected function safeForceIndex($params) {
        if (empty($params['forceIndex']) || !is_string($params['forceIndex'])) return '';
        if (!preg_match('/^[`\w\s,]+$/', $params['forceIndex'])) return '';
        return "FORCE INDEX({$params['forceIndex']})";
    }

    /* ============================== Count ============================== */

    public function findCount($params) {
        $groupbyFirstField = '';
        if (!empty($params['groupBy']) && is_string($params['groupBy'])) {
            $groupbyFirstField = false !== ($pos = stripos($params['groupBy'], ','))
                ? trim(substr($params['groupBy'], 0, $pos)) : $params['groupBy'];
        }
        unset($params['groupBy'], $params['limit'], $params['orderBy'], $params['selectBind'], $params['selectCaseBind']);
        $params['selectCase'] = empty($groupbyFirstField)
            ? 'COUNT(*) AS `cnt`'
            : 'COUNT(DISTINCT ' . $groupbyFirstField . ') AS `cnt`';

        $objs = $this->finds($params);
        return isset($objs[0]) ? $objs[0]->cnt : 0;
    }

    /* ============================== Misc ============================== */

    protected function formatSQL($sql) {
        return trim(preg_replace(array("/\n/", "/\s+/"), " ", $sql));
    }

    protected function begin() {
        ++$this->transactions;
        if ($this->transactions == 1) $this->db->begin();
    }

    protected function rollback() {
        if ($this->transactions == 1) {
            $this->transactions = 0; $this->db->rollback();
        } else {
            --$this->transactions;
        }
    }

    protected function commit() {
        if ($this->transactions == 1) $this->db->commit();
        --$this->transactions;
    }

    public function escape($value) {
        // Prefer driver-aware escaping from the SQL class; fall back to a safe default.
        if (is_object($this->db) && method_exists($this->db, 'escape')) {
            return $this->db->escape($value);
        }
        return is_null($value) ? null : str_replace(array("'", "\\"), array("''", "\\\\"), (string)$value);
    }

    public function removeNonNumeric($value) {
        return preg_replace('/[^\d\.\-]/', '', (string)$value);
    }
}