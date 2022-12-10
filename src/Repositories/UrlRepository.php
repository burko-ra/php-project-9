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
        return $this->db->query($sql);
    }

    /**
     * @return array<mixed>
     */
    public function getBy(string $value, string $column)
    {
        $sql = "SELECT *
            FROM urls
            WHERE $column = :value";
        $urls = $this->db->query($sql, [':value' => $value]);
        return $urls;
    }

    /**
     * @return array<mixed>|null
     */
    public function getById(string $id)
    {
        $urls = $this->getBy($id, 'id');
        return empty($urls) ? null : $urls[0];
    }

    /**
     * @return void
     */
    public function add(string $name)
    {
        $sql = "INSERT INTO urls (name, created_at) VALUES
            (:name, :createdAt)";
        $params = [
            ':name' => $name,
            ':createdAt' => Carbon::now()
        ];
        $this->db->query($sql, $params);
    }
}
