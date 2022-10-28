<?php

namespace PageAnalyzer\Engine;

use Valitron\Validator;
use Carbon\Carbon;

/**
 * @param string $url
 * @return array<mixed>
 */
function getUrlErrors($url): array
{
    $errors = [];

    $urlParts = parse_url($url);
    if (!$urlParts) {
        $errors[] = ['url' => 'Некорректный URL'];
    } elseif ($url === '') {
        $errors[] = ['url' => 'URL не должен быть пустым'];
    } else {
        $urlParts['name'] = trim($url);
        $validator = new Validator($urlParts);
        $validator->rule('required', 'scheme')->message('Некорректный URL: отсутствует протокол (http/https)');
        $validator->rule('required', 'host')->message('Некорректный URL: отсутствует доменное имя');
        $validator->rule('lengthMax', 'name', 255)->message('Некорректный URL: длина превышает 255 символов');
        $validator->validate();

        if (is_bool($validator->errors())) {
            throw new \Exception('Expected array, boolean given');
        }

        $errors = $validator->errors();
    }

    return $errors;
}

/**
 * @param string $url
 * @return string
 */
function normalizeUrl($url): string
{
    $urlParts = parse_url(strtolower($url));
    $scheme = $urlParts['scheme'] ?? '';
    $host = $urlParts['host'] ?? '';
    return $scheme . "://" . $host;
}

/**
 * @return \PDO
 */
function connect(): object
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
    return new \PDO($dsn);
}

/**
 * @param string $sql
 * @return array<mixed>
 */
function query($sql)
{
    $dbh = connect();
    $result = $dbh->query($sql);
    if (!$result) {
        throw new \Exception('Cannot execute the query');
    }

    $matches = $result->fetchAll(0);
    if ($matches === false) {
        throw new \Exception('Expect array, boolean given');
    }

    return $matches;
}

/**
 * @param string $url
 * @return bool
 */
function isUrlUnique($url): bool
{
    $sql = "SELECT name FROM urls WHERE name = '{$url}'";
    $matches = query($sql);
    return empty($matches);
}

/**
 * @param string $url
 * @return void
 */
function insertUrl($url): void
{
    $dbh = connect();
    $sqlInsertUrl = 'INSERT INTO urls (name, created_at) VALUES
        (:name, :created_at)';
    $queryInsertUrl = $dbh->prepare($sqlInsertUrl);
    $queryInsertUrl->execute([':name' => $url, ':created_at' => Carbon::now()->toDateTimeString()]);
}

/**
 * @param string $url
 * @return int
 */
function getUrlId($url)
{
    $sql = "SELECT id FROM urls WHERE name = '{$url}'";
    $matches = query($sql);
    return $matches[0]['id'];
}

/**
 * @param int $id
 * @return array<mixed>
 */
function getUrlInfo($id)
{
    $sql = "SELECT id, name, created_at FROM urls WHERE id = '{$id}'";
    $matches = query($sql);
    return $matches[0];
}

/**
 * @return array<mixed>
 */
function getUrls()
{
    $sql = "SELECT id, name FROM urls ORDER BY id DESC";
    $matches = query($sql);
    return $matches;
}
