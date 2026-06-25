<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use Exception;
use xjryanse\phplite\logic\ModelQueryCon;

/**
 * 本地 SQLite 替代 DataSdk 的元数据读路径（仅 ORM tableData*）。
 */
final class SqliteCatalogDataSdk
{
    /** @var int|string */
    protected $dbId = 0;

    /**
     * @param int|string $dbId
     *
     * @return $this
     */
    public function dbBind($dbId)
    {
        $this->dbId = $dbId;

        return $this;
    }

    /**
     * @return int|string
     */
    public function getDbId()
    {
        return $this->dbId;
    }

    /**
     * @param string $tableName
     */
    private function assertCatalogTable(string $tableName): void
    {
        if (!is_string($tableName) || !preg_match('/^w_[a-z0-9_]+$/', $tableName)) {
            throw new Exception('非法表名:' . $tableName);
        }
    }

    private function prepareCatalogTableRead(string $tableName): void
    {
        $this->assertCatalogTable($tableName);
    }

    /**
     * @param string     $tableName
     * @param string|int $id
     *
     * @return array<string, mixed>
     */
    public function tableDataGet($tableName, $id, $emptyErr = true): array
    {
        $this->prepareCatalogTableRead($tableName);
        $row = SqliteConnection::queryFirst(
            'SELECT * FROM `' . $tableName . '` WHERE `id` = ? LIMIT 1',
            [(string) $id]
        );
        if (!$row && $emptyErr) {
            throw new Exception('没有取到数据:' . $tableName . '#' . $id);
        }

        return $row ?: [];
    }

    /**
     * @param string $tableName
     * @param array  $con
     * @param string $orderBy
     * @param string $allowFields
     *
     * @return array<string, mixed>
     */
    public function tableDataConFind($tableName, $con = [], $orderBy = '', $allowFields = ''): array
    {
        $list = $this->tableDataConList($tableName, $con, $orderBy, $allowFields);

        return $list ? $list[0] : [];
    }

    /**
     * @param string     $tableName
     * @param array      $con
     * @param string     $orderBy
     * @param int|string $fourth limit(int) 或 allowFields(string)
     *
     * @return array<int, array<string, mixed>>
     */
    public function tableDataConList($tableName, $con = [], $orderBy = '', $fourth = ''): array
    {
        $this->prepareCatalogTableRead($tableName);

        $limit = 0;
        $allowFields = '';
        if (is_int($fourth)) {
            $limit = $fourth;
        } elseif (is_string($fourth)) {
            $allowFields = $fourth;
        }

        $fields = ($allowFields !== '' && $allowFields !== '0') ? $allowFields : '*';
        $wherePart = ModelQueryCon::conditionParse($con);
        $sql = 'SELECT ' . $fields . ' FROM `' . $tableName . '` WHERE ' . $wherePart;
        if ($orderBy !== '' && $orderBy !== null) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return SqliteConnection::queryAll($sql);
    }
}
