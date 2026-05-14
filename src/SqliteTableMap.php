<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * 模型类（短类名）→ SQLite 物理表名：前缀 + 驼峰转蛇形，与 phplite CoreBasePDown::getTable 规则一致。
 *
 * 示例（前缀默认 w_）：
 * - coresl\sql\Sql → w_sql
 * - coresl\sql\SqlField → w_sql_field
 * - coresl\sql\SqlTable → w_sql_table
 *
 * 可通过 {@see register} / {@see registerMany} 覆盖任意 FQCN 的表名。
 */
final class SqliteTableMap
{
    /** @var array<string, string> 完全限定类名 => 表名（完整表名，含前缀） */
    private static $registered = [];

    public static function register(string $fullyQualifiedClassName, string $tableName): void
    {
        self::$registered[$fullyQualifiedClassName] = $tableName;
    }

    /**
     * @param array<string, string> $map FQCN => 表名
     */
    public static function registerMany(array $map): void
    {
        foreach ($map as $class => $table) {
            self::register((string) $class, (string) $table);
        }
    }

    public static function unregister(string $fullyQualifiedClassName): void
    {
        unset(self::$registered[$fullyQualifiedClassName]);
    }

    public static function clear(): void
    {
        self::$registered = [];
    }

    /**
     * 已注册则用注册表名，否则按类名推导。
     */
    public static function resolve(string $fullyQualifiedClassName, string $prefix = 'w_'): string
    {
        if (isset(self::$registered[$fullyQualifiedClassName])) {
            return self::$registered[$fullyQualifiedClassName];
        }

        return self::fromClass($fullyQualifiedClassName, $prefix);
    }

    /**
     * 取短类名后：驼峰 → 蛇形 + 前缀（不查注册表）。
     */
    public static function fromClass(string $fullyQualifiedClassName, string $prefix = 'w_'): string
    {
        $pos = strrpos($fullyQualifiedClassName, '\\');
        $short = $pos === false ? $fullyQualifiedClassName : substr($fullyQualifiedClassName, $pos + 1);

        return $prefix . self::shortNameToSnake($short);
    }

    /**
     * 与 CoreBasePDown 一致：首字母外大写前加下划线再 strtolower。
     */
    public static function shortNameToSnake(string $shortClassName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClassName));
    }
}
