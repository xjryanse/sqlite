<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * 本地 SQLite「元数据 / catalog」：w_sql、w_sql_table 等；与 {@see SqliteConnection} 共用连接。
 *
 * 配置优先读 {@see self::CONFIG_PATH_KEYS} 中第一个存在的 path；enabled 同理。
 */
final class SqliteCatalog
{
    /** @var list<string> */
    private const CONFIG_PATH_KEYS = ['sqlite_catalog.path', 'tenancy_sqlite.path'];

    /** @var list<string> */
    private const CONFIG_ENABLED_KEYS = ['sqlite_catalog.enabled', 'tenancy_sqlite.enabled'];

    /**
     * 解析实际使用的 SQLite 文件路径（配置 path 优先，否则默认路径，文件须存在）。
     *
     * @return string 找不到可用文件时返回空字符串
     */
    public static function resolvedSqlitePath(): string
    {
        if (function_exists('config')) {
            foreach (self::CONFIG_PATH_KEYS as $key) {
                $p = config($key);
                if (is_string($p) && $p !== '' && is_file($p)) {
                    return $p;
                }
            }
        }

        return is_file(SqliteConnection::DEFAULT_PATH) ? SqliteConnection::DEFAULT_PATH : '';
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
     * 是否走本机 SQLite 元数据（与 {@see DbSysBase::commInst} 本地读一致）。
     */
    public static function isEnabled(): bool
    {
        $flag = self::explicitEnabledFlag();
        if ($flag === false) {
            return false;
        }
        if ($flag === true) {
            return true;
        }

        return self::resolvedSqlitePath() !== '';
    }

    /**
     * 按解析到的路径设置 {@see SqliteConnection}（在 {@see isEnabled} 为真时调用即可）。
     */
    /**
     * 元数据 catalog 必须可用；不可用时抛异常（无 MySQL 兜底）。
     */
    public static function requireEnabled(): void
    {
        self::bootstrapFromConfig();
        if (!empty($GLOBALS['_sqlite_catalog_path']) && is_file($GLOBALS['_sqlite_catalog_path'])) {
            return;
        }
        $path = self::resolvedSqlitePath();
        if ($path !== '' && is_file($path)) {
            return;
        }
        $configured = '';
        if (function_exists('config')) {
            foreach (self::CONFIG_PATH_KEYS as $key) {
                $p = config($key);
                if (is_string($p) && $p !== '') {
                    $configured = $p;
                    break;
                }
            }
        }
        throw new \Exception(
            'tenancy SQLite 元数据库不可用，请配置 sqlite_catalog.path 并确保文件存在且可读。'
            . ($configured !== '' ? ' 已配置:' . $configured : '')
            . ' 默认路径:' . SqliteConnection::DEFAULT_PATH
        );
    }

    public static function bootstrapFromConfig(): void
    {
        // 请求级 catalog（如 abnormal.db）优先，避免后续 commInst 被 tenancy 配置覆盖
        if (!empty($GLOBALS['_sqlite_catalog_path']) && is_file($GLOBALS['_sqlite_catalog_path'])) {
            SqliteConnection::setPath($GLOBALS['_sqlite_catalog_path']);
            return;
        }
        if (!self::isEnabled()) {
            return;
        }
        $path = self::resolvedSqlitePath();
        if ($path !== '') {
            SqliteConnection::setPath($path);
        }
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
