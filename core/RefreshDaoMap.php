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

class RefreshDaoMap {

    /**
     * Initialize Dao and Map
     */
    public static function initDaoMap($project, $dbConfig)
    {
        $project = strtolower($project);
        $daoNamespace = 'Doba\Dao';
        $mapNamespace = 'Doba\Map';
        $daoPath = ROOT_PATH."common/libs/dao/";
        $mapPath = ROOT_PATH."common/libs/map/";
        if($project) {
            $projectNamespace = ucfirst(Util::camelcase($project));
            $daoNamespace = "Doba\Dao\\".$projectNamespace;
            $mapNamespace = "Doba\Map\\".$projectNamespace;
            $daoPath = ROOT_PATH."common/libs/dao/{$projectNamespace}/";
            $mapPath = ROOT_PATH."common/libs/map/{$projectNamespace}/";    
        }
        $db = new SQL(array('db'=>'mysql') + $dbConfig);
        $datas = (array)$db->query('SHOW TABLES'); $tables = array();
        $initConfig = \Config::me()->initDaoMapConfig();
        for($i = 0, $ct = count($datas); $i < $ct; $i ++)
        {
            $tableName = current($datas[$i]); $daoName = $tableName; 
            $invalidTablename = false;
            if(is_array($initConfig['MATCHED_TABLES'])) 
                foreach($initConfig['MATCHED_TABLES'] as $regular) {
                if(! preg_match($regular, $tableName)) continue 2;
            }
            if(is_array($initConfig['IGNORED_TABLES'])) 
                foreach($initConfig['IGNORED_TABLES'] as $regular) {
                if(preg_match($regular, $tableName)) {
                    $invalidTablename = true; break;
                }
            }
            if(is_array($initConfig['IGNORED_TABLES_PREFIX'])) 
                foreach($initConfig['IGNORED_TABLES_PREFIX'] as $regular) {
                    $daoName = preg_replace($regular, '', $tableName);
            }
            $daoName = ucfirst(Util::camelcase($daoName));
            if(! $invalidTablename) $tables[] = array('daoName'=> $daoName, 'tableName'=>$tableName);
        }
        self::initDao($tables, $project ? $project : 'default', 
            $daoPath, $daoNamespace, $mapNamespace);
        self::initMap($db, $tables, $mapPath, $mapNamespace);
    }

    /**
     * Initialize Dao
     */
    public static function initDao($tables, $link, $path, $daoNamespace, $mapNamespace)
    {
        $template = <<<TP
<?php
namespace {{ @dao namespace }};

use Doba\BaseDAO;

class {{ @dao name }}DAO extends BaseDAO {

    protected function __construct() {
        parent::__construct('{{ @table name }}', 
            array(
                'link'=>'{{ @link }}',
                'tbpk'=>\{{ @map namespace}}\{{ @dao name }}::getTablePk(),
                'tbinfo'=>\{{ @map namespace}}\{{ @dao name }}::getTableInfo(),
            )
        ); 
    }
}
TP;
        foreach($tables as $table) 
        {
            Util::mkdir($path);
            if(! is_file($daofile = $path.$table['daoName'].'DAO.php')) 
            {
                $GLOBALS['variables'] = array(
                    'dao namespace'=>$daoNamespace,
                    'map namespace'=>$mapNamespace,
                    'dao name'=>$table['daoName'],
                    'table name'=>$table['tableName'],
                    'link'=>$link,
                );
                preg_match_all("/{{\s*@(.[^}]+)}}/", $template, $out);
                $toArray = array_map(function($text){
                    $text = trim($text);
                    return isset($GLOBALS['variables'][$text]) ? $GLOBALS['variables'][$text] : $text;
                }, $out[1]);
                file_put_contents($daofile, str_replace($out[0], $toArray, $template));
            }
        }
    }

    /**
     * initialize Map file
     */
    public static function initMap($db, $tables, $path, $mapNamespace)
    {
        $template = <<<TP
<?php
namespace {{ @map namespace }};

class {{ @class name }} {

    public static function getTablePk() {
        return '{{ @table pk }}';
    }

    public static function getTableInfo() {
        return array(
            {{ @table info }}
        );
    }

    public static function getSQL() {
        \$sql = <<<SQL
{{ @create sql }}
SQL;
        return \$sql;
    }
}
TP;
        foreach($tables as $table)
        {
            Util::mkdir($path);
            $tableInfo = ""; $tbpk = "";
            $results = $db->query("DESC `{$table['tableName']}`");
            if(is_array($results)) foreach($results as $i=>$result) {
                if(preg_match('/int/i', $result->Type)) $type = 'int';  
                else if(preg_match('/(float|double|decimal)/i', $result->Type)) $type = 'float';
                else $type = 'string';
                $pk = ('PRI' == strtoupper($result->Key)) ? 1 : 0;
                $notnull = 'NO' == strtoupper($result->Null) ? 1 : 0;
                $autoincremnt = ('AUTO_INCREMENT' == strtoupper($result->Extra)) ? 1 : 0;
                $default = is_null($result->Default) ? 'NULL' : ('' == $result->Default ? "''" : "'".addslashes($result->Default)."'");

                $tableInfo .= ($i>0?"\n".str_repeat(" ", 12):"")."array('field'=>'{$result->Field}', 'type'=>'{$type}', 'notnull'=>{$notnull}, 'default'=>{$default}, 'pk'=>{$pk}, 'autoincremnt'=>{$autoincremnt}),";
                if($pk) $tbpk = $result->Field;
            }

            $results = $db->query("SHOW CREATE TABLE `{$table['tableName']}`");
            $results = array_values((array)$results[0]);

            $className = $table['daoName'];
            $mapfile = $path.$className.'.php';

            $GLOBALS['variables'] = array(
                'map namespace'=>$mapNamespace,
                'class name'=>$className,
                'table pk'=>$tbpk,
                'table info'=>$tableInfo,
                'create sql'=>preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $results[1]),
            );
            preg_match_all("/{{\s*@(.[^}]+)}}/", $template, $out);
            $toArray = array_map(function($text){
                $text = trim($text);
                return isset($GLOBALS['variables'][$text]) ? $GLOBALS['variables'][$text] : $text;
            }, $out[1]);
            file_put_contents($mapfile, str_replace($out[0], $toArray, $template));
        }
    }
}