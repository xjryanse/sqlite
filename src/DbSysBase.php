<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use Exception;
use xjryanse\phplite\ormcore\CoreBasePDown;
use xjryanse\phplite\ormcore\OrmCoreBase;

/**
 * 元数据 ORM 基类：表名 {@see SqliteTableMap}；本地 catalog 开启时走 {@see SqliteCatalogDataSdk}。
 */
abstract class DbSysBase extends CoreBasePDown
{
    protected static $dbCate = 'dbTenancy';

    public static function getTable(): string
    {
        return SqliteTableMap::resolve(static::class);
    }

    /**
     * SQL 元数据 ORM：只读本地 catalog（tenancy.db / 请求级 abnormal.db），不走 dbTenancy。
     */
    public static function commInst($id = 0)
    {
        global $svBindId;
        if (!$svBindId) {
            throw new Exception(static::class . '未设置$hostBindId');
        }
        SqliteCatalog::requireEnabled();
        $inst = OrmCoreBase::inst($id);
        $dataSdk = (new SqliteCatalogDataSdk())->dbBind(0);
        $inst->setDataSdk($dataSdk);
        $inst->setTable(static::getTable());

        return $inst;
    }
}
