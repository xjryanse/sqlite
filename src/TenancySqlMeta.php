<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * tenancy.db 元数据（w_sql、w_sql_table 等）轻量读取。
 */
final class TenancySqlMeta
{
    /**
     * 解析实际使用的 SQLite 文件路径（配置 path 优先，否则默认路径，文件须存在）。
     *
     * @return string 找不到可用文件时返回空字符串
     */
    public static function resolvedSqlitePath(): string
    {
        $fromConfig = function_exists('config') ? config('tenancy_sqlite.path') : null;
        if (is_string($fromConfig) && $fromConfig !== '' && is_file($fromConfig)) {
            return $fromConfig;
        }
        if (is_file(SqliteTenancy::DEFAULT_PATH)) {
            return SqliteTenancy::DEFAULT_PATH;
        }

        return '';
    }

    /**
     * 是否走本机 tenancy.db 元数据（与 {@see DbSysBase::commInst} 本地读一致）。
     */
    public static function isEnabled(): bool
    {
        if (!function_exists('config')) {
            return self::resolvedSqlitePath() !== '';
        }
        $explicit = config('tenancy_sqlite.enabled');
        if ($explicit === false) {
            return false;
        }
        if ($explicit === true) {
            return true;
        }

        return self::resolvedSqlitePath() !== '';
    }

    /**
     * 按解析到的路径设置 {@see SqliteTenancy}（在 {@see isEnabled} 为真时调用即可）。
     */
    public static function bootstrapFromConfig(): void
    {
        if (!self::isEnabled()) {
            return;
        }
        $path = self::resolvedSqlitePath();
        if ($path !== '') {
            SqliteTenancy::setPath($path);
        }
    }

    /**
     * @return string 找不到返回空字符串
     */
    public static function keyToIdBySqlKey($sqlKey): string
    {
        $row = SqliteTenancy::queryFirst(
            'SELECT id FROM w_sql WHERE sql_key = ? LIMIT 1',
            [$sqlKey]
        );
        if (!$row || !isset($row['id'])) {
            return '';
        }

        return (string) $row['id'];
    }

    /**
     * @param string|int $sqlId
     * @param array      $uparam 预留
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sqlTableForGenerate($sqlId, $uparam = []): array
    {
        $rows = SqliteTenancy::queryAll(
            'SELECT id, sql_id, table_name, alias, join_type, "on" AS join_on_col '
            . 'FROM w_sql_table WHERE sql_id = ? AND status = 1 ORDER BY sort',
            [$sqlId]
        );
        $listsN = [];
        foreach ($rows as $row) {
            $on = isset($row['join_on_col']) ? $row['join_on_col'] : (isset($row['on']) ? $row['on'] : '');
            $listsN[] = [
                'id' => $row['id'],
                'sql_id' => $row['sql_id'],
                'table_name' => $row['table_name'],
                'alias' => isset($row['alias']) ? $row['alias'] : '',
                'join_type' => isset($row['join_type']) ? $row['join_type'] : '',
                'on' => $on,
                'isSep' => 0,
                'sepSuffix' => [],
            ];
        }

        return $listsN;
    }
}
