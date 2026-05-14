<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\ModelQueryCon;

/**
 * 基于 {@see SqliteConnection} 的只读查询与 SQL 片段拼装（表名→ORM 类名等仍属业务约定）。
 */
final class SqliteCatalogSql
{
    /** @var array<string, string> 分表等场景覆盖表→服务类 */
    public static $bindServiceObj = [];

    public static function getService($tableName): string
    {
        if (!$tableName) {
            return '';
        }
        if (isset(self::$bindServiceObj[$tableName])) {
            return self::$bindServiceObj[$tableName];
        }
        $res = explode('_', (string) $tableName);
        if (!isset($res[1])) {
            return '';
        }
        $module = $res[1];
        $parts = array_map('ucfirst', $res);
        $last = end($parts);
        if (is_numeric($last) && mb_strlen((string) $last) === 4) {
            array_pop($parts);
        }
        $tail = array_slice($parts, 1);

        return '\\orm\\' . $module . '\\' . implode('', $tail);
    }

    public static function tableNameDbSource($tableName): string
    {
        $orm = self::getService($tableName);

        return class_exists($orm) && method_exists($orm, 'dbSource') ? $orm::dbSource() : 'dbBusi';
    }

    /**
     * @return array<int, string>
     */
    private static function allTableNames(): array
    {
        $rows = SqliteConnection::queryAll(SqliteConnection::SQL_LIST_USER_TABLE_NAMES);

        return $rows ? array_column($rows, 'name') : [];
    }

    public static function isTableExist($tableName, $dbSource = ''): bool
    {
        if (!self::getService($tableName)) {
            return false;
        }

        return in_array($tableName, self::allTableNames(), true);
    }

    /**
     * @param array<int, string> $fieldsArr
     */
    public static function sumFieldStr(array $fieldsArr): string
    {
        return implode(',', array_map(static function (string $v): string {
            return 'CAST(ROUND(COALESCE(SUM(`' . $v . '`), 0), 2) AS REAL) AS `' . $v . '`';
        }, $fieldsArr));
    }

    /**
     * 生成关联查询 SQL（仅拼语句；执行走 {@see SqliteReadOrm} 或 {@see SqliteConnection::queryAll}）。
     */
    public static function generateJoinSql($fields, $arr = [], $groupFields = [], $con = [], $orderBy = '', $whereFields = [], $havingFields = []): string
    {
        $tSql = self::generateJoinTable($arr);
        if ($con) {
            $whereFields[] = ModelQueryCon::conditionParse($con);
        }
        if ($whereFields) {
            $tSql .= ' where ' . implode(' and ', $whereFields);
        }
        $groupStr = $groupFields ? implode(',', $groupFields) : '';
        if ($groupStr) {
            $tSql .= ' group by ' . $groupStr;
        }
        if ($havingFields) {
            $tSql .= ' having ' . implode(' and ', $havingFields);
        }
        $fieldStr = $fields ? implode(',', $fields) : '*';
        $sqlFinal = 'select ' . $fieldStr;
        if ($tSql) {
            $sqlFinal .= ' from ' . $tSql;
        }
        if ($orderBy) {
            $sqlFinal .= ' order by ' . $orderBy;
        }

        return $sqlFinal;
    }

    /**
     * @param array<int, array<string, mixed>> $arr
     */
    private static function generateJoinTable(array $arr = []): string
    {
        $tSql = '';
        foreach ($arr as $v) {
            if (Arrays::value($v, 'join_type')) {
                $tSql .= ' ' . $v['join_type'] . ' join ';
            }
            $tSql .= $v['realTable'];
            if (!empty($v['alias'])) {
                $tSql .= ' as ' . $v['alias'] . ' ';
            }
            if (Arrays::value($v, 'on')) {
                $tSql .= ' on ' . $v['on'] . ' ';
            }
        }

        return $tSql;
    }

    /**
     * @param array<int|string, string>              $tables
     * @param array<string, array<int, string>>        $fieldArr
     * @param array<int|string, array<string, mixed>>  $whereArr
     * @param array<int|string, array<int, string>>    $groupFields
     */
    public static function generateUnionSql($tables, $fieldArr, $whereArr = [], $groupFields = []): string
    {
        $sqlArr = [];
        foreach ($tables as $i => $table) {
            $alias = is_numeric($i) ? 't' . $i : $i;
            $fields = [];
            foreach ($fieldArr as $k => $v) {
                $fields[] = $v[$i] . ' as ' . $k;
            }
            $con = $whereArr[$i] ?? [];
            $inst = QueryOrm::inst()->instInit()->setTable($table)->alias($alias)->where($con)->field(implode(',', $fields));
            $groupFArr = $groupFields[$i] ?? [];
            $groupStr = $groupFArr ? implode(',', $groupFArr) : '';
            if ($groupStr) {
                $inst->group($groupStr);
            }
            $sqlArr[] = $inst->select();
        }

        return '(' . implode(' union ', $sqlArr) . ')';
    }
}
