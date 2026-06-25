<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * 本地 SQLite「元数据 / catalog」：与 {@see SqliteConnection} 共用连接。
 * 不在此处 setPath，路径须由应用层 {@see SqliteConnection::setPath()} 初始化。
 */
final class SqliteCatalog
{
    /** @var list<string> */
    private const CONFIG_PATH_KEYS = ['sqlite_catalog.path'];

    /** @var list<string> */
    private const CONFIG_ENABLED_KEYS = ['sqlite_catalog.enabled'];

    /**
     * 读取配置中的 SQLite 文件路径（不校验、不 setPath）。
     *
     * @return string 未配置时返回空字符串
     */
    public static function resolvedSqlitePath(): string
    {
        if (function_exists('config')) {
            foreach (self::CONFIG_PATH_KEYS as $key) {
                $p = config($key);
                if (is_string($p) && $p !== '') {
                    return $p;
                }
            }
        }

        return '';
    }

    /**
     * @return bool|null null 表示未显式配置
     */
    private static function explicitEnabledFlag(): ?bool
    {
        if (!function_exists('config')) {
            return null;
        }
        foreach (self::CONFIG_ENABLED_KEYS as $key) {
            $explicit = config($key);
            if ($explicit === false) {
                return false;
            }
            if ($explicit === true) {
                return true;
            }
        }

        return null;
    }

    /**
     * 是否走本机 SQLite 元数据（已 setPath 或配置 path 对应文件存在）。
     */
    public static function isEnabled(): bool
    {
        $flag = self::explicitEnabledFlag();
        if ($flag === false) {
            return false;
        }
        if (SqliteConnection::hasPath()) {
            return true;
        }
        $path = self::resolvedSqlitePath();
        if ($path !== '' && is_file($path)) {
            return true;
        }

        return false;
    }

    /**
     * 兼容旧调用；vendor 不 setPath，由应用层负责。
     */
    public static function bootstrapFromConfig(): void
    {
    }

    /**
     * @return string 找不到返回空字符串
     */
    public static function keyToIdBySqlKey($sqlKey): string
    {
        $row = SqliteConnection::queryFirst(
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
        $rows = SqliteConnection::queryAll(
            'SELECT id, sql_id, table_name, alias, join_type, "on" AS join_on_col '
            . 'FROM w_sql_table WHERE sql_id = ? AND status = 1 ORDER BY sort',
            [$sqlId]
        );
        $listsN = [];
        foreach ($rows as $row) {
            $listsN[] = [
                'id' => $row['id'],
                'sql_id' => $row['sql_id'],
                'table_name' => $row['table_name'],
                'alias' => $row['alias'] ?? '',
                'join_type' => $row['join_type'] ?? '',
                'on' => $row['join_on_col'] ?? $row['on'] ?? '',
                'isSep' => 0,
                'sepSuffix' => [],
            ];
        }

        return $listsN;
    }
}
