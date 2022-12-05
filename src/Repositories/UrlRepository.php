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
    public function getBy(string $urlName, string $column = 'id')
    {
        $sql = "SELECT *
            FROM urls
            WHERE $column = :urlName";
        $urls = $this->db->query($sql, ['urlName' => $urlName]);
        return empty($urls) ? null : $urls[0];
    }

    /**
     * @return void
     */
    public function add(string $urlName)
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
