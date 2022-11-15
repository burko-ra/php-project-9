<?php

namespace PageAnalyzer;

use Carbon\Carbon;

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
        $sql = "SELECT DISTINCT ON (urls.id) urls.id as url_id,
        urls.name as url_name,
        url_checks.created_at as url_last_check,
        url_checks.status_code as url_last_status_code
        FROM urls
        LEFT JOIN url_checks
        ON urls.id = url_checks.url_id
        ORDER BY urls.id DESC, url_last_check DESC";
        return $this->db->query($sql);
    }

    /**
     * @return array<mixed>|null
     */
    public function getById(string $urlId)
    {
        $sql = "SELECT *
            FROM urls
            WHERE id = :urlId";
        $urls = $this->db->query($sql, [':urlId' => $urlId]);
        return empty($urls) ? null : $urls[0];
    }

    /**
     * @return array<mixed>|false
     */
    public function getIdByName(string $urlName)
    {
        $sql = "SELECT id
            FROM urls
            WHERE name = :urlName";
        $urls = $this->db->query($sql, [':urlName' => $urlName]);
        return empty($urls) ? false : $urls[0]['id'];
    }

    /**
     * @return void
     */
    public function save(string $urlName)
    {
        $createdAt = Carbon::now()->toDateTimeString();
        $sql = "INSERT INTO urls (name, created_at) VALUES
            (:name, :createdAt)";
        $params = [
            ':name' => $urlName,
            ':createdAt' => $createdAt
        ];
        $this->db->query($sql, $params);
    }
}
