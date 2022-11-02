<?php

namespace PageAnalyzer;

use Carbon\Carbon;
use PageAnalyzer\Connection;

class UrlRepo
{
    protected \PDO $dbh;

    public function __construct(Connection $connection)
    {
        $this->dbh = $connection->dbh;
    }

    /**
     * @param string $sql
     * @return array<mixed>
     */
    protected function query($sql)
    {
        $matches = $this->dbh->query($sql);
        if (!$matches) {
            throw new \Exception('Cannot execute the query');
        }

        $result = $matches->fetchAll(0);
        if ($result === false) {
            throw new \Exception('Expect array, boolean given');
        }

        return $result;
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
        return $this->query($sql);
    }

    /**
     * @return array<mixed>|false
     */
    public function findById(string $urlId)
    {
        $sql = "SELECT *
            FROM urls
            WHERE id = '{$urlId}'";
        $urls = $this->query($sql);
        return empty($urls) ? false : $urls[0];
    }

    /**
     * @return array<mixed>|false
     */
    public function findByName(string $urlName)
    {
        $sql = "SELECT *
            FROM urls
            WHERE name = '{$urlName}'";
        $urls = $this->query($sql);
        return empty($urls) ? false : $urls[0];
    }

    /**
     * @return void
     */
    public function save(string $urlName)
    {
        $createdAt = Carbon::now()->toDateTimeString();
        $sql = "INSERT INTO urls (name, created_at) VALUES
            (:name, :createdAt)";
        $query = $this->dbh->prepare($sql);
        $query->execute([':name' => $urlName, ':createdAt' => $createdAt]);
    }
}
