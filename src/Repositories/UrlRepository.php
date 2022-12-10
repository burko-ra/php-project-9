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
    public function getBy(string $urlName, string $column = 'id')
    {
        $sql = "SELECT *
            FROM urls
            WHERE $column = :urlName";
        $urls = $this->db->query($sql, ['urlName' => $urlName]);
        return $urls;
    }

    /**
     * @return array<mixed>|null
     */
    public function getById(string $urlName)
    {
        $urls = $this->getBy($urlName, 'id');
        return empty($urls) ? null : $urls[0];
    }

    /**
     * @return void
     */
    public function add(string $urlName)
    {
        $sql = "INSERT INTO urls (name, created_at) VALUES
            (:name, :createdAt)";
        $params = [
            ':name' => $urlName,
            ':createdAt' => Carbon::now()
        ];
        $this->db->query($sql, $params);
    }
}
