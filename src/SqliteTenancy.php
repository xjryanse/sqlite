<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * tenancy.db 单例连接与路径（与 {@see TenancySqlMeta} 及业务侧只读查询共用同进程单连接）。
 */
final class SqliteTenancy
{
    public const DEFAULT_PATH = '/www/wwwroot/custom_conf/tenancy.db';

    /** @var string|null */
    private static $pathOverride;

    /** @var SqliteDb|null */
    private static $db;

    public static function setPath(?string $path): void
    {
        self::$pathOverride = $path;
        self::$db = null;
    }

    public static function effectivePath(): string
    {
        return (self::$pathOverride !== null && self::$pathOverride !== '')
            ? self::$pathOverride
            : self::DEFAULT_PATH;
    }

    public static function db(): SqliteDb
    {
        if (self::$db === null) {
            self::$db = SqliteDb::open(self::effectivePath(), ['readonly' => true]);
        }

        return self::$db;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function queryAll(string $sql, array $params = []): array
    {
        $rows = self::db()->queryAll($sql, $params);

        return $rows ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function queryFirst(string $sql, array $params = []): ?array
    {
        return self::db()->queryOne($sql, $params);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function queryScalar(string $sql, array $params = [], $default = null)
    {
        return self::db()->queryScalar($sql, $params, $default);
    }
}
