<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function findBy(string $column, mixed $value): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `$column` = ? LIMIT 1",
            [$value]
        );
    }

    public function all(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` ORDER BY `{$this->primaryKey}` DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function where(string $column, mixed $value, int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE `$column` = ? ORDER BY `{$this->primaryKey}` DESC LIMIT ? OFFSET ?",
            [$value, $limit, $offset]
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    public function updateById(int $id, array $data): int
    {
        return $this->db->update($this->table, $data, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function deleteById(int $id): int
    {
        return $this->db->delete($this->table, "`{$this->primaryKey}` = ?", [$id]);
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        $result = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE $where",
            $params
        );
        return (int) $result;
    }

    public function paginate(int $page = 1, int $perPage = 15, string $where = '1=1', array $params = [], string $orderBy = null): array
    {
        $orderBy = $orderBy ?? "`{$this->primaryKey}` DESC";
        $offset = ($page - 1) * $perPage;
        $total = $this->count($where, $params);

        $items = $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE $where ORDER BY $orderBy LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($offset + $perPage) < $total,
            ],
        ];
    }

    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `$column` = ?";
        $params = [$value];

        if ($excludeId !== null) {
            $sql .= " AND `{$this->primaryKey}` != ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }
}
