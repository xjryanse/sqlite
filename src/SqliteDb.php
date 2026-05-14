<?php

declare(strict_types=1);

namespace xjryanse\sqlite;

use PDO;
use PDOException;
use RuntimeException;

/**
 * SQLite 通用连接与增删改查（PDO 预处理，表/列名白名单校验）。
 *
 * 表级方法命名：insert 新增、update 更新、delete 删除、selectOne/selectAll 查询。
 */
class SqliteDb
{
    /** @var PDO */
    private $pdo;

    /**
     * @param string|\PDO $path 数据库文件路径，或已配置好的 PDO（包装后不再应用 $pdoOptions）
     */
    public function __construct($path, array $pdoOptions = [])
    {
        if ($path instanceof PDO) {
            $this->pdo = $path;

            return;
        }
        if (!is_string($path)) {
            throw new \InvalidArgumentException('SqliteDb first argument must be string path or PDO');
        }
        $dsn = 'sqlite:' . $path;
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $this->pdo = new PDO($dsn, null, null, $pdoOptions + $defaults);
    }

    public static function memory(array $pdoOptions = []): self
    {
        return new self(':memory:', $pdoOptions);
    }

    /**
     * 按路径打开库：默认只读；非只读时可建父目录并应用 WAL 等 PRAGMA（与 {@see SqliteConnection} 默认场景对齐）。
     *
     * @param array{
     *     readonly?: bool,
     *     create_parent_dir?: bool,
     *     busy_timeout_ms?: int
     * } $options
     *
     * @throws RuntimeException
     */
    public static function open(string $path, array $options = []): self
    {
        $defaults = [
            'readonly' => true,
            'create_parent_dir' => false,
            'busy_timeout_ms' => 5000,
        ];
        $options = array_merge($defaults, $options);
        $isMemory = self::isMemoryPath($path);

        if (!$isMemory && empty($options['readonly']) && !empty($options['create_parent_dir'])) {
            $dir = dirname($path);
            if ($dir !== '.' && $dir !== '' && !is_dir($dir)) {
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException('无法创建 SQLite 目录: ' . $dir);
                }
            }
        }

        $attrs = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (!empty($options['readonly']) && !$isMemory) {
            if (defined('PDO::SQLITE_ATTR_OPEN_FLAGS') && defined('PDO::SQLITE_OPEN_READONLY')) {
                $attrs[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
            }
        }

        $dsn = self::buildDsn($path);
        try {
            $pdo = new PDO($dsn, null, null, $attrs);
        } catch (PDOException $e) {
            throw new RuntimeException('SQLite 连接失败: ' . $e->getMessage(), 0, $e);
        }

        $db = new self($pdo);
        if (empty($options['readonly']) || $isMemory) {
            $busy = (int) $options['busy_timeout_ms'];
            if ($busy > 0) {
                $db->execute('PRAGMA busy_timeout = ' . $busy);
            }
            $db->execute("PRAGMA journal_mode = 'WAL'");
            $db->execute("PRAGMA synchronous = 'NORMAL'");
            $db->execute('PRAGMA foreign_keys = ON');
        }

        return $db;
    }

    private static function isMemoryPath(string $path): bool
    {
        return $path === ':memory:' || $path === 'sqlite::memory:';
    }

    private static function buildDsn(string $path): string
    {
        if (self::isMemoryPath($path)) {
            return 'sqlite::memory:';
        }
        $real = str_replace('\\', '/', $path);

        return 'sqlite:' . $real;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * 执行任意 SQL（INSERT/UPDATE/DELETE 等），返回受影响行数。
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * 查询多行。
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * 查询单行，无结果返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * 查询单个标量（第一行第一列）。无行时返回 $whenNoRows（默认 null）；有行且列为 SQL NULL 时返回 null。
     *
     * @param mixed $whenNoRows PDO 无行时（fetchColumn 为 false）的返回值
     * @return mixed|null
     */
    public function queryScalar(string $sql, array $params = [], $whenNoRows = null)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn(0);
        if ($v === false) {
            return $whenNoRows;
        }

        return $v;
    }

    /**
     * 第一列值列表（PDO::FETCH_COLUMN）。
     *
     * @return array<int, mixed>
     */
    public function queryColumn(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $col = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        return $col ? $col : [];
    }

    /**
     * 执行语句（如 EXPLAIN），成功返回 true，语义同 PDOStatement::execute。
     */
    public function statement(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * 新增（INSERT），返回 lastInsertId（未指定自增列时可能为 "0" 字符串，由调用方处理）。
     */
    public function insert(string $table, array $data): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('insert data cannot be empty');
        }
        $this->assertIdentifier($table);
        $cols = array_keys($data);
        foreach ($cols as $c) {
            $this->assertIdentifier($c);
        }
        $quotedTable = $this->quoteIdent($table);
        $quotedCols = array_map([$this, 'quoteIdent'], $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO ' . $quotedTable . ' (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
        $this->pdo->prepare($sql)->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * 新增：INSERT OR REPLACE（主键/唯一冲突时替换）。
     */
    public function insertOrReplace(string $table, array $data): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('insert data cannot be empty');
        }
        $this->assertIdentifier($table);
        $cols = array_keys($data);
        foreach ($cols as $c) {
            $this->assertIdentifier($c);
        }
        $quotedTable = $this->quoteIdent($table);
        $quotedCols = array_map([$this, 'quoteIdent'], $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT OR REPLACE INTO ' . $quotedTable . ' (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
        $this->pdo->prepare($sql)->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * 新增：INSERT OR IGNORE（冲突时忽略）。
     */
    public function insertOrIgnore(string $table, array $data): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('insert data cannot be empty');
        }
        $this->assertIdentifier($table);
        $cols = array_keys($data);
        foreach ($cols as $c) {
            $this->assertIdentifier($c);
        }
        $quotedTable = $this->quoteIdent($table);
        $quotedCols = array_map([$this, 'quoteIdent'], $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT OR IGNORE INTO ' . $quotedTable . ' (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
        $this->pdo->prepare($sql)->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * 更新（UPDATE）。$where 为空会抛错，避免误更新全表。
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($where === []) {
            throw new \InvalidArgumentException('update where cannot be empty');
        }
        if ($data === []) {
            throw new \InvalidArgumentException('update data cannot be empty');
        }
        $this->assertIdentifier($table);
        foreach (array_keys($data) as $c) {
            $this->assertIdentifier($c);
        }
        [$whereSql, $whereParams] = $this->buildWhere($where);
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = $this->quoteIdent($col) . '=?';
        }
        $sql = 'UPDATE ' . $this->quoteIdent($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));

        return $stmt->rowCount();
    }

    /**
     * 删除（DELETE）。$where 为空会抛错。
     */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new \InvalidArgumentException('delete where cannot be empty');
        }
        $this->assertIdentifier($table);
        [$whereSql, $whereParams] = $this->buildWhere($where);
        $sql = 'DELETE FROM ' . $this->quoteIdent($table) . ' WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereParams);

        return $stmt->rowCount();
    }

    /**
     * 查询单行（SELECT … LIMIT 1）。$where 为空表示无 WHERE（全表第一行，慎用）。
     *
     * @param string[] $columns 列名，默认 ['*']；不可传用户原始字符串作表达式
     * @return array<string, mixed>|null
     */
    public function selectOne(string $table, array $where = [], array $columns = ['*']): ?array
    {
        $this->assertIdentifier($table);
        $select = $this->buildSelectList($columns);
        $sql = 'SELECT ' . $select . ' FROM ' . $this->quoteIdent($table);
        $params = [];
        if ($where !== []) {
            [$w, $params] = $this->buildWhere($where);
            $sql .= ' WHERE ' . $w;
        }
        $sql .= ' LIMIT 1';

        return $this->queryOne($sql, $params);
    }

    /**
     * 查询多行（SELECT）。
     *
     * @param string[] $columns
     * @return array<int, array<string, mixed>>
     */
    public function selectAll(
        string $table,
        array $where = [],
        array $columns = ['*'],
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $this->assertIdentifier($table);
        $select = $this->buildSelectList($columns);
        $sql = 'SELECT ' . $select . ' FROM ' . $this->quoteIdent($table);
        $params = [];
        if ($where !== []) {
            [$w, $params] = $this->buildWhere($where);
            $sql .= ' WHERE ' . $w;
        }
        if ($orderBy !== null && $orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        }

        return $this->queryAll($sql, $params);
    }

    public function count(string $table, array $where = []): int
    {
        $this->assertIdentifier($table);
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteIdent($table);
        $params = [];
        if ($where !== []) {
            [$w, $params] = $this->buildWhere($where);
            $sql .= ' WHERE ' . $w;
        }
        $n = $this->queryScalar($sql, $params);

        return (int) $n;
    }

    /**
     * 等值条件拼 AND；值可为数组表示 IN：['id' => [1,2,3]]。
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhere(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $col => $val) {
            $this->assertIdentifier((string) $col);
            $qcol = $this->quoteIdent((string) $col);
            if (is_array($val)) {
                if ($val === []) {
                    throw new \InvalidArgumentException('IN list cannot be empty for column ' . $col);
                }
                $ph = implode(',', array_fill(0, count($val), '?'));
                $parts[] = $qcol . ' IN (' . $ph . ')';
                foreach ($val as $v) {
                    $params[] = $v;
                }
            } else {
                $parts[] = $qcol . '=?';
                $params[] = $val;
            }
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @param string[] $columns
     */
    private function buildSelectList(array $columns): string
    {
        if ($columns === ['*']) {
            return '*';
        }
        foreach ($columns as $c) {
            $this->assertIdentifier($c);
        }

        return implode(',', array_map([$this, 'quoteIdent'], $columns));
    }

    private function assertIdentifier(string $name): void
    {
        if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid table/column identifier: ' . $name);
        }
    }

    private function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
