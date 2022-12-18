<?php

namespace PageAnalyzer\Repositories;

use Carbon\Carbon;
use PageAnalyzer\Database;

class UrlRepository
{
    protected Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * @return array<mixed>
     */
    public function all()
    {
        $sql = "SELECT
            id as url_id,
            name as url_name
        FROM urls
        ORDER BY url_id DESC";
        return $this->db->getAll($sql);
    }

    /**
     * @return array<mixed>
     */
    public function getBy(string $value, string $column)
    {
        $sql = "SELECT *
            FROM urls
            WHERE $column = :value";
        return $this->db->getAll($sql, [':value' => $value]);
    }

    /**
     * @return array<mixed>|null
     */
    public function getById(string $id)
    {
        $sql = "SELECT *
        FROM urls
        WHERE id = :id";
        return $this->db->getRow($sql, [':id' => $id]);
    }

    public function add(string $name): string
    {
        $sql = "INSERT INTO urls (name, created_at) VALUES
            (:name, :createdAt)";
        $params = [
            ':name' => $name,
            ':createdAt' => Carbon::now()
        ];
        return $this->db->insert($sql, $params);
    }
}
