<?php

namespace PageAnalyzer\Repositories;

use PageAnalyzer\Database;

class UrlCheckRepository
{
    protected Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * @return array<mixed>
     */
    public function getBy(string $value, string $column)
    {
        $sql = "SELECT * 
            FROM url_checks
            WHERE $column = :value
            ORDER BY id DESC";
        return $this->db->getAll($sql, [':value' => $value]);
    }

    /**
     * @param array<mixed> $check
     * @return void
     */
    public function add($check)
    {
        $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at) VALUES
            (:urlId, :statusCode, :h1, :title, :description, :createdAt)";
        $params = [
            ':urlId' => $check['urlId'],
            ':statusCode' => $check['statusCode'],
            ':createdAt' => $check['createdAt'],
            ':h1' => $check['h1'] ?? '',
            ':title' => $check['title'] ?? '',
            ':description' => $check['description'] ?? ''
        ];
        return $this->db->insert($sql, $params);
    }

    /**
     * @return array<mixed>
     */
    public function getDistinct()
    {
        $sql = "SELECT DISTINCT ON (url_id)
            url_id,
            created_at as url_last_check,
            status_code as url_last_status_code
        FROM url_checks
        ORDER BY url_id DESC, url_last_check DESC";
        return $this->db->getAll($sql);
    }
}
