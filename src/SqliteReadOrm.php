<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

/**
 * {@see SqliteConnection} 上的只读链式查询入口（与 phplite DbOrm 签名对齐的占位参数）。
 */
final class SqliteReadOrm
{
    public static function query($sql, $dbSource = null)
    {
        return SqliteConnection::queryAll($sql);
    }

    /**
     * @param mixed $dbSource 保留参数，与 phplite DbOrm 签名对齐，当前忽略
     */
    public static function table($tableExpr, $dbSource = null)
    {
        return new SqliteReadQuery($tableExpr);
    }
}
