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
    private $link = 'default';
    private $tbpk = 'id';
    private $transactions = 0;

    protected function __construct($tbname, $options=array())  {
        $this->tbname = $tbname;
        $this->originaltbname = $tbname;
        $this->link = isset($options['link']) && $options['link'] ? $options['link'] : 'default';
        $this->tbpk = isset($options['tbpk']) && $options['tbpk'] ? $options['tbpk'] : 'id';
        $this->sp = isset($options['sp']) && $options['sp'] ? $options['sp'] : '';
        $this->tbinfo = isset($options['tbinfo']) && $options['tbinfo'] ? $options['tbinfo'] : $this->getTableInfo();
        $this->db = \Config::me()->getDb($this->link);
    }

    public static function me(){
        $class = get_called_class();
        if(! self::$instance[$class]) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    public function resetdb() { 
        $db = \Config::me()->getDb($this->link, array('reconnect'=>true)); 
        if(is_array(self::$instance))
            foreach(self::$instance as $classobj) {
            $classobj->setdb($db);
        }
        return true;
    }
    public function getdb() { return $this->db; }
    public function setdb($db=NULL) { 
        $this->db = $db; return $this; 
    }

    public function table($table) {
        $this->tbname = preg_match('/^\d+$/', $table) ? $this->originaltbname.$this->sp.$table : $table;
        return $this;
    }

    public function getTableName() {
       return $this->tbname;
    }

    public function query($sql) {
        $sql = $this->formatSQL($sql);
        $this->lastQuerySql = $sql;
        $result = $this->db->query($sql);
        return $result;
    }

    public function sql() {
        return $this->lastQuerySql;
    }

    /**
     * get table field name
     */
    public function getTableInfo() {
        $fields = array();
        if('sqlite' == $this->db->dbname) {
            $results = $this->db->query("PRAGMA TABLE_INFO(`{$this->tbname}`)");
            if(is_array($results)) foreach($results as $result) {  
                if(preg_match('/^int/i', $result->type)) $type = 'int';  
                else if('REAL' == strtoupper($result->type)) $type = 'float';
                else $type = 'string';
                $fields[] = array(
                    'field'=>$result->name,
                    'type'=>$type,
                    'notnull'=>$result->notnull ? true : false,
                    'default'=>$result->dflt_value,
                    'pk'=>$result->pk,
                    'autoincremnt'=>$result->pk,
                );
                if($result->pk) $this->tbpk = $result->name;
            }
        } else if('mysql' == $this->db->dbname) {
            $results = $this->db->query("DESC `{$this->tbname}`");
            if(is_array($results)) foreach($results as $result) {
                if(preg_match('/int/i', $result->type)) $type = 'int';  
                else if(preg_match('/(float|double|decimal)/i', $result->type)) $type = 'float';
                else $type = 'string';
                $pk = ('PRI' == strtoupper($result->Key)) ? true : false;
                $fields[] = array(
                    'field'=>$result->Field,
                    'type'=>$type,
                    'notnull'=>'NO' == strtoupper($result->Null) ? true : false,
                    'default'=>$result->Default,
                    'pk'=>$pk,
                    'autoincremnt'=>('AUTO_INCREMENT' == strtoupper($result->Extra)) ? true : false,
                );
                if($pk) $this->tbpk = $result->Field;
            }
        }
        return $fields;
    }

    /**
     * insert data to database， support batch insert
     * @param  array $params ，data to write to the database
     *         example1: ['title'=>'test1', 'status'=>1]
     *         example2: [['title','status'], ['test1', 1], ['test2', 1], ...]
     *         example3: [['title'=>'test1', 'status'=>1], ['title'=>'test2', 'status'=>1], ...]
     * @return [type]         [description]
     */
    public function insert($params) 
    {
        $field = $value = ""; $fieldlen = 0; $pkvalue = 0;
        if(! is_array($params[0])) { // match: example1: ['title'=>'test1', 'status'=>1]
            list($fieldstr, $valuestr, $fieldlen, $pkvalue) = $this->getFieldAndValueSqlPart($params);
            $field = "(".$fieldstr.")"; $value = "(".$valuestr.")";
        } 
        else 
        {
            if(isset($params[0][0])) { 
                // match: example2: [['title','status'], ['test1', 1], ['test2', 1], ...]
                $values = []; $fields = $params[0];
                for($i = 1, $plen = count($params); $i < $plen; $i++) {
                    if(empty($params[$i])) { continue; }
                    list($fieldstr, $valuestr, $fieldlen, $pkvalue) = $this->getFieldAndValueSqlPart(array_combine($fields, $params[$i]));
                    if(! $field) {
                        $field = "(".$fieldstr.")"; 
                    }
                    $values[] = "(".$valuestr.")";
                }
                $value = implode(",", $values);
            } 
            else 
            {
                // match: [['title'=>'test1', 'status'=>1], ['title'=>'test2', 'status'=>1], ...]
                $values = [];
                foreach($params as $key=>$param) {
                    if(! is_numeric($key)) { continue; }
                    list($fieldstr, $valuestr, $fieldlen, $pkvalue) = $this->getFieldAndValueSqlPart($param);
                    if(! $field) {
                        $field = "(".$fieldstr.")"; 
                    }
                    $values[] = "(".$valuestr.")";
                }
                $value = implode(",", $values);
            }
        }
        $field = $field ? $field : "()"; $value = $value ? $value : "()"; 

        $insertReplace = isset($params['_INSERT_REPLACE']) && true === $params['_INSERT_REPLACE'] ? true : false;
        if($insertReplace) {
            $lastInsertId = $this->query("REPLACE INTO `{$this->tbname}` {$field} VALUES {$value}");
        } else {
            $insertIgonre = isset($params['_INSERT_IGONRE']) && true === $params['_INSERT_IGONRE'] ? 'IGNORE ' : '';
            $lastInsertId = $this->query("INSERT {$insertIgonre}INTO `{$this->tbname}` {$field} VALUES {$value}");
        }
        if($pkvalue) return $pkvalue;
        else{
            return $lastInsertId ? $lastInsertId : 0;
        }
    }

    /**
     * return part of the insert sql by passing parameters
     * @param $prams ['title'=>'test1', 'status'=>1]
     * @return [type] [description]
     */
    private function getFieldAndValueSqlPart($params) {
        $fieldstr = $valuestr = ""; $fieldlen = 0; $pkvalue = 0;
        foreach($this->tbinfo as $tbinfo) 
        {
            if(isset($params[$tbinfo['field']])) 
            {
                if($tbinfo['autoincremnt']){
                    if('' == $params[$tbinfo['field']]) continue;
                } else if($tbinfo['pk']) {
                    if('' != $params[$tbinfo['field']]) $pkvalue = $params[$tbinfo['field']];
                }

                $value = $this->escape($params[$tbinfo['field']]);
                settype($value, $tbinfo['type']);

                $valuestr .= ($fieldlen > 0 ? ',' : '');
                if(is_null($value)) $valuestr .= 'NULL'; 
                else if('CURRENT_TIMESTAMP' == strtoupper($value)) $valuestr .= "'".date('Y-m-d H:i:s')."'"; 
                else if(in_array($tbinfo['type'], array('int', 'float'))) {
                    $valuestr .= ('' === $value) ? (is_null($tbinfo['default']) ? (int)$value : $tbinfo['default']): $value;
                }
                else $valuestr .= "'".$value."'";
                $fieldstr .= ($fieldlen > 0 ? ',' : '').'`'.$tbinfo['field'].'`';
                $fieldlen ++;
            }
        }
        return [$fieldstr, $valuestr, $fieldlen, $pkvalue];
    }

    /**
     * update database data 
     * @param  array $params
     * @return [type]         [description]
     */
    public function change($pk=0, $params) 
    {   
        $data = array(); $columns = array_column($this->tbinfo, 'field');
        foreach($params as $field=>$value) {
            if(! in_array($field, $columns)) continue;
            if(is_null($value)) $data[] = "`{$field}`=NULL";
            else if(is_array($value) && 2 == count($value) && in_array($value[0], ['+', '-'])
            ) {
                // for example: ['quantity'=>['+', 10]] => quantity = quantity + 10
                $data[] = "`{$field}`=`{$field}`{$value[0]}".$this->escape($value[1]);
            }
            else {
                $data[] = "`{$field}`='".$this->escape($value)."'";
            }
        }
        $setConds = implode(',', $data);

        $where = ''; $id = 0;
        if(is_array($pk)) {
            $sql = $this->where($pk);
            $where = $sql ? "WHERE ".$sql : '';
        } else {
            $where = "WHERE `{$this->tbpk}`='{$pk}'"; $id = $pk;
        }
        $this->query("UPDATE `{$this->tbname}` SET {$setConds} {$where}");
        return $id;
    }

    /**
     * Get a record through the id
     */
    public function get($pk=0) 
    {
        $datas = empty($pk) ? array() : $this->finds(array($this->tbpk=>$pk, 'limit'=>1));
        return isset($datas[0]) ? (object)$datas[0] : NULL;
    }

    /**
     * Delete record through the id
     */
    public function delete($pk=0) 
    {
        $where = '';
        if(is_array($pk)) {
            $sql = $this->where($pk);
            $where = $sql ? "WHERE ".$sql : '';
        } else {
            $where = "WHERE `{$this->tbpk}`='{$pk}'";
        }
        $this->query("DELETE FROM `{$this->tbname}` {$where}");
    }

    /**
     * To get the results
     * @param  array $params array('dateCode', 'dateCodeGeq', 'dateCodeLeq', 'pid', 'username')
     * @return [type]         [description]
     */
    public function finds($params) 
    {
        $groupByStr = '';
        if(! empty($params['groupBy'])) {
            $groupByStr = "GROUP BY {$params['groupBy']}";
        }
        $orderByStr = '';
        if(! empty($params['orderBy'])) {
            $orderByStr = "ORDER BY {$params['orderBy']}";
        }
        $limitStr = '';
        if(preg_match('/^\d+(,\d+)*$/', $params['limit'])) {
            $limitStr = "LIMIT {$params['limit']}";
        }
        $forceIndexStr = '';
        if(! empty($params['forceIndex'])) {
            $forceIndexStr = "FORCE INDEX({$params['forceIndex']})";
        }
        $selectCase = $params['selectCase'] ? $params['selectCase'] : '*';

        $joinConds = array();
        $params['joinConds'] = (array)$params['joinConds'];
        if(is_array($params['joinConds']))
            foreach($params['joinConds'] as $joinCond) {
            if(preg_match('/(left|inner|right)\s*join/i', $joinCond)) $joinConds[] = $joinCond;
        }
        $params['joinConds'] = $joinConds ? implode(' ', $joinConds) : '';
        $params['joinPrefix'] = isset($params['joinConds']) &&
            preg_match('/(left|inner|right)\s*join/i', $params['joinConds']) ? "`a`." : '';
        
        $where = $this->where($params);

        $tbname = "`{$this->tbname}`".($params['joinPrefix'] ? " AS `a`" : "");
        $sql = "SELECT {$selectCase} FROM {$tbname} {$forceIndexStr} {$params['joinConds']} ".($where ? "WHERE ".$where : "");
        
        $sqlWithOutLimit = $sql." {$groupByStr} {$orderByStr}";
        $sql .= " {$groupByStr} {$orderByStr} {$limitStr}";
        
        return $this->query($sql);
    }

    /**
     * Array parameters converted to the statement of sql part
     * for example: [
     *      'name'=>['and'=>false, op'=>'like', 'value'=>'xiaoming'], // and `name` like '%xiaoming%'
     *      'nameLike'=>'xiaoming', // and name='xiaoming'
     *      'joinConds'=>['left join `table1` as `t1` on t1.tid=a.id', 'left join `table2` as `t2` on t2.tid=a.id'],
     *      'joinWhere'=>[['p.name', 'xiaoming'], ['p.status', 'in', [1,2,3]]]
     *  ]
     */
    protected function where($params)
    {
        $prefix = isset($params['joinPrefix']) ? $params['joinPrefix'] : '';
        $sql = ''; $fields = array_column($this->tbinfo, 'field');
        foreach($fields as $field) 
        {
            if(isset($params[$field])) 
            {
                $value = $params[$field];
                if(is_array($value))
                {
                    if(isset($value['value']) && is_scalar($value['value']) && '' !== $value['value'])
                    {
                        // array('and'=>false, 'op'=>'=', 'value'=>'')
                        // op: [eq =], [geq, >=], [gt, >], [leq, <=], [lt, <], [<>, !=, neq], 
                        //     [in, nin(not in)] ,[like, llike, rlike], [custom, raw]
                        $nvalue = $value['value']; $op = $value['op'];
                        $andOr = isset($value['and']) && false === $value['and'] ? 'OR' : 'AND';
                        $sql .= ' '.$this->sqlPart($field, $op, $nvalue, $prefix, $andOr);
                    }
                }
                else if(is_scalar($value) && '' !== $value) {
                    $sql .= ' '.$this->sqlPart($field, 'eq', $value, $prefix);
                }
            } if(isset($params[$field.'Like']) && '' !== $params[$field.'Like']) {
                $sql .= ' '.$this->sqlPart($field, 'like', $params[$field.'Like'], $prefix);
            } if(isset($params[$field.'LLike']) && '' !== $params[$field.'LLike']) {
                $sql .= ' '.$this->sqlPart($field, 'llike', $params[$field.'LLike'], $prefix);
            } if(isset($params[$field.'RLike']) && '' !== $params[$field.'RLike']) {
                $sql .= ' '.$this->sqlPart($field, 'rlike', $params[$field.'RLike'], $prefix);
            } if(isset($params[$field.'Geq']) && '' !== $params[$field.'Geq']) {
                $sql .= ' '.$this->sqlPart($field, 'geq', $params[$field.'Geq'], $prefix);
            } if(isset($params[$field.'Gt']) && '' !== $params[$field.'Gt']) {
                $sql .= ' '.$this->sqlPart($field, 'gt', $params[$field.'Gt'], $prefix);
            } if(isset($params[$field.'Leq']) && '' !== $params[$field.'Leq']) {
                $sql .= ' '.$this->sqlPart($field, 'leq', $params[$field.'Leq'], $prefix);
            } if(isset($params[$field.'Lt']) && '' !== $params[$field.'Lt']) {
                $sql .= ' '.$this->sqlPart($field, 'lt', $params[$field.'Lt'], $prefix);
            } if(isset($params[$field.'Neq']) && '' !== $params[$field.'Neq']) {
                $sql .= ' '.$this->sqlPart($field, 'neq', $params[$field.'Neq'], $prefix);
            } if(isset($params[$field.'In']) && ! is_null($params[$field.'In'])) {
                $sql .= ' '.$this->sqlPart($field, 'in', $params[$field.'In'], $prefix);
            } if(isset($params[$field.'Nin']) && ! is_null($params[$field.'Nin'])) {
                $sql .= ' '.$this->sqlPart($field, 'nin', $params[$field.'Nin'], $prefix);
            }
        }
        if($prefix && $params['joinWhere']) {
            // for example: [['b.id', 1], ['b.status', 'in', [1,2,3]], ['b.name', 'like', 'xiaoming']]
            if(is_array($params['joinWhere'])) {
                foreach($params['joinWhere'] as $joinWhere) {
                    if(is_array($joinWhere)) {
                        $cntlen = count($joinWhere); $op = '=';
                        if(2 == $cntlen) list($field, $value) = $joinWhere;
                        else if(3 == $cntlen) list($field, $op, $value) = $joinWhere;
                        else continue;
                        // assemble sql
                        $prefix = ''; $field = preg_replace('/`/', '', $field);
                        if(false !== strpos($field, '.')) { // for example: `a`.`name`
                            list($prefix, $field) = explode('.', $field);
                            $prefix = "`{$prefix}`.";
                        }
                        $sql .= ' '.$this->sqlPart($field, $op, $value, $prefix);
                    } else if(is_string($joinWhere)) {
                        $sql .= " AND ".preg_replace('/^\s*(and|or)/i', '', $joinWhere);         
                    }
                }
            } else if(is_string($params['joinWhere'])) {
                $sql .= " AND ".preg_replace('/^\s*(and|or)/i', '', $params['joinWhere']); 
            }
        }
        return preg_replace('/^\s*(and|or)/i', '', $sql);
    }

    /**
     * Array parameters converted to the statement of sql part
     */
    protected function sqlPart($field, $op, $value, $prefix, $andOr='and') 
    {
        $andOr = strtoupper($andOr); $op = strtolower($op); $sql = '';
        $field = $andOr.' '.$prefix."`{$field}`";
        switch($op) {
            case 'custom': // custom sql statement
            case 'raw':
                $sql = "{$andOr} ({$value})";
                break;
            case '=':
            case 'eq':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}=".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}='".$this->escape($value)."'";
                }
                break;
            case '>':
            case 'gt':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}>".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}>'".$this->escape($value)."'";
                }
                break;
            case '>=':
            case 'geq':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}>=".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}>='".$this->escape($value)."'";
                }
                break;
            case '<':
            case 'lt':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}<".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}<'".$this->escape($value)."'";
                }
                break;
            case '<=':
            case 'leq':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}<=".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}<='".$this->escape($value)."'";
                }
                break;
            case '<>':
            case '!=':
            case 'neq':
                if(is_int($value) || is_float($value)) {
                    $sql = "{$field}!=".$this->removeNonNumeric($value);
                } else {
                    $sql = "{$field}!='".$this->escape($value)."'";
                }
                break; 
            case 'in':
                if(is_array($value)) {
                    $value = is_int($value[0]) 
                        ? implode(",", array_map(function($data){ return (int)$data; }, $value)) 
                        : "'".implode("','", $this->escape($value))."'";
                }
                $sql = "{$field} IN ($value)";
                break;
            case 'nin':
            case 'not in':
                if(is_array($value)) {
                    $value = is_int($value[0]) 
                        ? implode(",", array_map(function($data){ return (int)$data; }, $value)) 
                        : "'".implode("','", $this->escape($value))."'";
                }
                $sql = "{$field} NOT IN ($value)";
                break;
            case 'like':
                $sql = "{$field} LIKE '%".$this->escape($value)."%'";
                break;
            case 'llike':
                $sql = "{$field} LIKE '%".$this->escape($value)."'";
                break;
            case 'rlike':
                $sql = "{$field} LIKE '".$this->escape($value)."%'";
                break;
        }
        return $sql;
    }

    public function findCount($params)
    {
        $groupbyFirstField = false !== ($pos = stripos($params['groupBy'], ',')) 
            ? trim(substr($params['groupBy'], 0, $pos)) : $params['groupBy'];

        unset($params['groupBy'], $params['limit'], $params['orderBy']);
        $params['selectCase'] = empty($groupbyFirstField) ? 'COUNT(*) AS `cnt`' : 'COUNT(DISTINCT '.$groupbyFirstField.') AS `cnt`';
        
        $objs = $this->finds($params);
        return isset($objs[0]) ? $objs[0]->cnt : 0;
    }

    protected function formatSQL($sql) {
        return trim(preg_replace(array("/\n/", "/\s+/"), " ", $sql));
    }

    protected function begin() {
        ++$this->transactions;
        if($this->transactions == 1) $this->db->begin();
    }

    protected function rollback() {
        if($this->transactions == 1) {
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
        return is_null($value) ? NULL : (is_numeric($value) ? $value : str_replace(array("'", "\\"), array("''", "\\\\"), $value));
    }

    public function removeNonNumeric($value) {
        return preg_replace('/[^\d\.]/', '', $value);
    }

}