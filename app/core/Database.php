<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use mysqli_result;
use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $this->connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($this->connection->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset($config['charset']);
        $this->connection->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    /**
     * Execute a prepared statement and return results.
     */
    public function query(string $sql, array $params = []): mysqli_result|bool
    {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Query preparation failed: ' . $this->connection->error);
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Query execution failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false && $stmt->errno === 0) {
            // Non-SELECT query
            return true;
        }

        return $result ?: true;
    }

    /**
     * Fetch all rows as associative arrays.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    /**
     * Fetch a single row.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            return $row ?: null;
        }
        return null;
    }

    /**
     * Fetch a single scalar value.
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);
        if ($row !== null) {
            return reset($row);
        }
        return null;
    }

    /**
     * Execute an INSERT and return the last insert ID.
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (`$columns`) VALUES ($placeholders)";
        $this->query($sql, array_values($data));

        return (int) $this->connection->insert_id;
    }

    /**
     * Execute an UPDATE and return affected rows.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));

        $sql = "UPDATE `$table` SET $set WHERE $where";
        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Query preparation failed: ' . $this->connection->error);
        }

        $types = $this->getParamTypes($params);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        return $stmt->affected_rows;
    }

    /**
     * Execute a DELETE and return affected rows.
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `$table` WHERE $where";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Query preparation failed: ' . $this->connection->error);
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->affected_rows;
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    /**
     * Commit a transaction.
     */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void
    {
        $this->connection->rollback();
    }

    /**
     * Get the last insert ID.
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    /**
     * Get affected rows from last query.
     */
    public function affectedRows(): int
    {
        return $this->connection->affected_rows;
    }

    /**
     * Escape a string (prefer prepared statements).
     */
    public function escape(string $value): string
    {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Determine parameter types for prepared statements.
     */
    private function getParamTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_null($param)) {
                $types .= 's';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}
