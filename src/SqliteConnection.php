<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * 进程内单例：指向一个 SQLite 文件（默认只读），供元数据 / 本地 catalog 等场景共用。
 */
final class SqliteConnection
{
    /** 非 sqlite_% 的用户表清单（sqlite_master） */
    public const SQL_LIST_USER_TABLE_NAMES = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";

    /** 未配置时的默认文件路径（部署环境可覆盖） */
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
     *
     * @return mixed
     */
    public static function queryScalar(string $sql, array $params = [], $default = null)
    {
        return self::db()->queryScalar($sql, $params, $default);
    }
}
