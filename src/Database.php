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
     * @return array<mixed>
     */
    public function query($sql)
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

    public function prepareAndExecute(string $sql, $params = [])
    {
        $sth = $this->dbh->prepare($sql);
        $sth->execute($params);
    }
}
