<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use xjryanse\phplite\logic\ModelQueryCon;

/**
 * {@see SqliteReadOrm::table} 返回的链式查询构造器。
 */
final class SqliteReadQuery
{
    /** @var string */
    protected $from;

    /** @var array<int, string> */
    protected $whereParts = [];

    /** @var string */
    protected $orderBy = '';

    /** @var string */
    protected $fieldsStr = '*';

    /** @var string */
    protected $groupBy = '';

    /** @var string */
    protected $havingRaw = '';

    public function __construct($from)
    {
        $this->from = $from;
    }

    /**
     * @param array|mixed $con
     *
     * @return $this
     */
    public function where($con)
    {
        if ($con) {
            $part = ModelQueryCon::conditionParse($con);
            if ($part !== '' && $part !== null) {
                $this->whereParts[] = '(' . $part . ')';
            }
        }

        return $this;
    }

    /**
     * @param mixed $order
     *
     * @return $this
     */
    public function order($order)
    {
        if ($order !== '' && $order !== null) {
            $this->orderBy = $order;
        }

        return $this;
    }

    /**
     * @param array|string $field
     *
     * @return $this
     */
    public function field($field)
    {
        $this->fieldsStr = is_array($field) ? implode(',', $field) : $field;

        return $this;
    }

    /**
     * @param mixed $group
     *
     * @return $this
     */
    public function group($group)
    {
        $this->groupBy = $group;

        return $this;
    }

    /**
     * @param mixed $having
     *
     * @return $this
     */
    public function having($having)
    {
        $this->havingRaw = $having;

        return $this;
    }

    protected function buildWhereSql(): string
    {
        if (!$this->whereParts) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $this->whereParts);
    }

    /**
     * FROM … WHERE … GROUP BY … HAVING（不含 SELECT / ORDER BY）。
     */
    private function buildFromSegment(): string
    {
        $sql = ' FROM ' . $this->from . $this->buildWhereSql();
        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . $this->groupBy;
        }
        if ($this->havingRaw) {
            $sql .= ' HAVING ' . $this->havingRaw;
        }

        return $sql;
    }

    /**
     * 完整 SELECT（含 ORDER BY，不含 LIMIT）。
     */
    private function buildSelectSql(): string
    {
        $sql = 'SELECT ' . $this->fieldsStr . $this->buildFromSegment();
        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }

        return $sql;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select()
    {
        return SqliteConnection::queryAll($this->buildSelectSql());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find()
    {
        return SqliteConnection::queryFirst($this->buildSelectSql() . ' LIMIT 1');
    }

    /**
     * @param int|string $page
     * @param int|string $perPage
     *
     * @return array<string, mixed>
     */
    public function paginate($page, $perPage)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $base = $this->buildFromSegment();

        $countSql = 'SELECT COUNT(1) AS c FROM (SELECT 1 AS _x' . $base . ') AS _cnt';
        $total = (int) SqliteConnection::queryScalar($countSql);

        $sql = $this->buildSelectSql() . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $data = SqliteConnection::queryAll($sql);
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $data,
            'total' => $total,
            'totalRecords' => $total,
            'per_page' => $perPage,
            'listRows' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function column($valueExpr, $keyField)
    {
        $sql = 'SELECT ' . $valueExpr . ' AS __v, ' . $keyField . ' AS __k FROM ' . $this->from;
        $sql .= $this->buildWhereSql();
        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . $this->groupBy;
        } else {
            $sql .= ' GROUP BY ' . $keyField;
        }
        if ($this->havingRaw) {
            $sql .= ' HAVING ' . $this->havingRaw;
        }
        $rows = SqliteConnection::queryAll($sql);
        $out = [];
        foreach ($rows as $r) {
            if (array_key_exists('__k', $r)) {
                $out[$r['__k']] = $r['__v'];
            }
        }

        return $out;
    }
}
