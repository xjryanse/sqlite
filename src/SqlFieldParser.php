<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * SQL 条件片段解析（供 ORM Sql 下钻等使用）。
 */
final class SqlFieldParser
{
    /**
     * @return array{alias: string, field: string, full: string}
     */
    public static function parseSqlFieldStruct(string $sqlCondStr): array
    {
        $sqlCondition = trim($sqlCondStr);
        $result = [
            'alias' => '',
            'field' => '',
            'full' => '',
        ];
        if ($sqlCondition === '') {
            return $result;
        }
        $pattern = '/(?:(?P<table_alias>[a-zA-Z0-9_]+)\.)?(?P<field_name>[a-zA-Z0-9_]+)/';
        if (preg_match($pattern, $sqlCondition, $matches)) {
            $result['alias'] = isset($matches['table_alias']) ? $matches['table_alias'] : '';
            $result['field'] = $matches['field_name'];
            $result['full'] = $matches[0];
        }

        return $result;
    }
}
