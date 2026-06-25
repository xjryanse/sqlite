<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use Exception;
use xjryanse\phplite\ormcore\CoreBasePDown;
use xjryanse\phplite\ormcore\OrmCoreBase;
use xjryanse\servicesdk\data\DataSdk;
use xjryanse\servicesdk\DbSdk;

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
     * sqlite_catalog / tenancy_sqlite 开启时：本地 SQLite；否则 dbTenancy + DataSdk。
     */
    public static function commInst($id = 0)
    {
        global $svBindId;
        if (!$svBindId) {
            throw new Exception(static::class . '未设置$hostBindId');
        }
        $inst = OrmCoreBase::inst($id);
        if (SqliteCatalog::isEnabled()) {
            $dataSdk = (new SqliteCatalogDataSdk())->dbBind(0);
        } else {
            $dbId = DbSdk::dbId(static::$dbCate, $svBindId);
            $dataSdk = DataSdk::inst($svBindId)->dbBind($dbId);
        }
        $inst->setDataSdk($dataSdk);
        $inst->setTable(static::getTable());

        return $inst;
    }
}
