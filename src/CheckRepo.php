<?php

namespace PageAnalyzer;

use PageAnalyzer\Connection;

class CheckRepo
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
    public function getById(string $urlId)
    {
        $sql = "SELECT * 
            FROM url_checks
            WHERE url_id = '{$urlId}'
            ORDER BY id ASC";
        return $this->query($sql);
    }

    /**
     * @param array<mixed> $check
     * @return void
     */
    public function save($check)
    {
        $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at) VALUES
            (:urlId, :statusCode, :h1, :title, :description, :createdAt)";
        $query = $this->dbh->prepare($sql);
        $query->execute([
            ':urlId' => $check['urlId'],
            ':statusCode' => $check['statusCode'],
            'h1' => $check['h1'],
            'title' => $check['title'],
            'description' => $check['description'],
            ':createdAt' => $check['createdAt']
        ]);
    }
}
