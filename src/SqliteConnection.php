<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * 进程内单例：指向一个 SQLite 文件（默认只读），供元数据 / 本地 catalog 等场景共用。
 * 路径须由应用层 {@see setPath()} 设置，未设置时查询直接抛异常。
 */
final class SqliteConnection
{
    /** 非 sqlite_% 的用户表清单（sqlite_master） */
    public const SQL_LIST_USER_TABLE_NAMES = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";

    /** @var string|null */
    private static $pathOverride;

    /** @var SqliteDb|null */
    private static $db;

    public static function setPath(?string $path): void
    {
        self::$pathOverride = $path;
        self::$db = null;
    }

    public static function hasPath(): bool
    {
        return self::$pathOverride !== null && self::$pathOverride !== '';
    }

    public static function effectivePath(): string
    {
        if (!self::hasPath()) {
            throw new \Exception('SQLite 路径未初始化，请先调用 SqliteConnection::setPath()');
        }

        return self::$pathOverride;
    }

    public static function db(): SqliteDb
    {
        if (self::$db === null) {
            self::$db = SqliteDb::open(self::effectivePath(), ['readonly' => true]);
        }

        return self::$db;
    }

    /**
     * Workerman 长进程：关闭并丢弃进程内 PDO，下次查询重建连接（整文件替换后需调用）。
     */
    public static function reconnect(): void
    {
        self::$db = null;
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
