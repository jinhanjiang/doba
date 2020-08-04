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
        $this->db = \Config::me()->getDb($this->link, array('reconnect'=>true)); 
    }
    public function getdb() { return $this->db; }
    public function setdb($db=NULL) { 
        $this->db = $db; return $this; 
    }

    public function table($table) {
        $this->tbname = preg_match('/^\d+$/', $table) ? $this->originaltbname.$this->sp.$table : $table;
        return $this;
    }

    public function query($sql) {
        $this->lastQuerySql = $sql;
        $result = $this->db->query($sql);
        $this->tbname = $this->originaltbname;
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
     * log
     * @param  array $params ， not contins (pid, dateCode, timeCreated)
     * @return [type]         [description]
     */
    public function insert($params) 
    {
        $fieldstr = $valuestr = ""; $k=0;
        foreach($this->tbinfo as $tbinfo) 
        {
            if(isset($params[$tbinfo['field']])) 
            {
                if($tbinfo['autoincremnt'] && '' == $params[$tbinfo['field']]) continue;

                $value = $this->escape($params[$tbinfo['field']]);
                settype($value, $tbinfo['type']);

                $valuestr .= ($k > 0 ? ',' : '');
                if(is_null($value)) $valuestr .= 'NULL'; 
                else if('CURRENT_TIMESTAMP' == strtoupper($value)) $valuestr .= "'".date('Y-m-d H:i:s')."'"; 
                else if(in_array($tbinfo['type'], array('int', 'float'))) {
                    $valuestr .= ('' === $value) ? (is_null($tbinfo['default']) ? (int)$value : $tbinfo['default']): $value;
                }
                else $valuestr .= "'".$value."'";
                $fieldstr .= ($k > 0 ? ',' : '').'`'.$tbinfo['field'].'`';
                $k ++;
            }
        }
        $insertIgonre = isset($params['_INSERT_IGONRE']) && true === $params['_INSERT_IGONRE'] ? 'IGNORE ' : '';
        $field = "(".$fieldstr.")"; $value = "(".$valuestr.")";
        return $k > 0 ? $this->query("INSERT {$insertIgonre}INTO `{$this->tbname}` {$field} VALUES {$value}") : 0;
    }

    /**
     * log
     * @param  array $params
     * @return [type]         [description]
     */
    public function change($pk=0, $params) 
    {   
        $data = array(); $columns = array_column($this->tbinfo, 'field');
        foreach($params as $field=>$value) {
            if(! in_array($field, $columns)) continue;
            if(is_null($value)) $data[] = "`{$field}`=NULL";
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
    public function get($id=0) 
    {
        $datas = empty($id) ? array() : $this->finds(array($this->tbpk=>$id, 'limit'=>1));
        return isset($datas[0]) ? (object)$datas[0] : NULL;
    }

    /**
     * Delete record through the id
     */
    public function delete($id=0) 
    {
        $where = '';
        if(is_array($id)) {
            $sql = $this->where($id);
            $where = $sql ? "WHERE ".$sql : '';
        } else {
            $where = "WHERE `{$this->tbpk}`='{$id}'";
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
        $selectCase = $params['selectCase'] ? $params['selectCase'] : '*';

        $where = $this->where($params);
        $sql = "SELECT {$selectCase} FROM `{$this->tbname}` ".($where ? "WHERE ".$where : "");
        
        $sqlWithOutLimit = $sql." {$groupByStr} {$orderByStr}";
        $sql .= " {$groupByStr} {$orderByStr} {$limitStr}";
        
        return $this->query($sql);
    }

    protected function where($params)
    {
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
                        // op: [eq =], [geq, >=], [gt, >], [leq, <=], [lt, <], [<>, !=, neq], in, nin(not in) ,like, [custom]
                        $valueText = $value['value']; $vescape = false;
                        $and = isset($value['and']) && false === $value['and'] ? 'OR' : 'AND';
                        $op = strtolower($value['op']);
                        if('>=' == $op || 'geq'== $op) { 
                            $op = '>='; $vescape = true; 
                        } else if('>' == $op || 'gt'== $op) { 
                            $op = '>'; $vescape = true; 
                        } else if('<=' == $op || 'leq'== $op) {
                            $op = '<='; $vescape = true; 
                        } else if('<' == $op || 'lt'== $op) {
                            $op = '<'; $vescape = true; 
                        } else if('<>' == $op || '!='== $op || 'neq'== $op) {
                            $op = '!='; $vescape = true; 
                        } else if('in' == $op) {
                            $op = 'IN'; $valueText = "({$valueText})";
                        } else if('nin' == $op) {
                            $op = 'NOT IN'; $valueText = "({$valueText})";
                        } 
                        else if('like' == $op) 
                        {
                            $op = 'LIKE'; $vescape = true;
                            $valueText = preg_match('/^%/', $valueText) || preg_match('/%$/', $valueText) 
                                ? $valueText : "%{$valueText}%";
                        } 
                        else if('custom' == $op) {
                            $op = $field = ''; $valueText = "({$valueText})";
                        } else {
                            $op = '='; $vescape = true; 
                        }
                        if($vescape) $valueText = "'".$this->escape($valueText)."'"; 
                        if($field) $field = "`{$field}`";
                        $sql .= " {$and} {$field} {$op} {$valueText}";
                    }
                }
                else if(is_scalar($value) && '' !== $value) {
                    $sql .= " AND `{$field}`='".$this->escape($value)."'";
                }
            } if(isset($params[$field.'Like']) && '' !== $params[$field.'Like']) {
                $sql .= " AND `{$field}` LIKE '%".$this->escape($params[$field.'Like'])."%'";
            } if(isset($params[$field.'Geq']) && '' !== $params[$field.'Geq']) {
                $sql .= " AND `{$field}`>='".$this->escape($params[$field.'Geq'])."'";
            } if(isset($params[$field.'Gt']) && '' !== $params[$field.'Gt']) {
                $sql .= " AND `{$field}`>'".$this->escape($params[$field.'Gt'])."'";
            } if(isset($params[$field.'Leq']) && '' !== $params[$field.'Leq']) {
                $sql .= " AND `{$field}`<='".$this->escape($params[$field.'Leq'])."'";
            } if(isset($params[$field.'Lt']) && '' !== $params[$field.'Lt']) {
                $sql .= " AND `{$field}`<'".$this->escape($params[$field.'Lt'])."'";
            } if(isset($params[$field.'Neq']) && '' !== $params[$field.'Neq']) {
                $sql .= " AND `{$field}`!='".$this->escape($params[$field.'Neq'])."'";
            } if(isset($params[$field.'In']) && ! is_null($params[$field.'In'])) {
                if(is_array($params[$field.'In'])) $params[$field.'In'] = $params[$field.'In'] ? "'".implode("','", $params[$field.'In'])."'" : '';
                if('' != $params[$field.'In']) {
                    $sql .= " AND `{$field}` IN (".$params[$field.'In'].")";
                }
            }
        }
        return preg_replace('/^\s*(and|or)/i', '', $sql);
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

}