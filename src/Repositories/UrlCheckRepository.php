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
    public function getBy(string $urlId, string $column = 'url_id')
    {
        $sql = "SELECT * 
            FROM url_checks
            WHERE $column = :urlId
            ORDER BY id DESC";
        return $this->db->query($sql, [':urlId' => $urlId]);
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
        $this->db->query($sql, $params);
    }
}
