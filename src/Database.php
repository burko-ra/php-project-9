<?php

namespace PageAnalyzer;

class Database
{
    public \PDO $dbh;

    public function __construct()
    {
        $dbUrl = getenv('DATABASE_URL');
        if (!$dbUrl) {
            throw new \Exception('Cannot get env var DATABASE_URL');
        }

        $databaseUrl = parse_url($dbUrl);
        $username = $databaseUrl['user'] ?? '';
        $password = $databaseUrl['pass'] ?? '';
        $host = $databaseUrl['host'] ?? '';
        $port = $databaseUrl['port'] ?? '';
        $dbName = ltrim($databaseUrl['path'] ?? '', '/');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbName;user=$username;password=$password";
        $this->dbh = new \PDO($dsn);
    }

    /**
     * @param string $sql
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public function query(string $sql, $params = [])
    {
        $sth = $this->dbh->prepare($sql);
        $res = $sth->execute($params);
        if (!$res) {
            throw new \Exception('Cannot execute the query');
        }

        $matches = $sth->fetchAll(0);
        if ($matches === false) {
            throw new \Exception('Expect array, boolean given');
        }

        return $matches;
    }
}
