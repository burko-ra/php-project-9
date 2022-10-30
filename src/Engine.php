<?php

namespace PageAnalyzer\Engine;

use Valitron\Validator;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

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
    $sql = "SELECT name
        FROM urls
        WHERE name = '{$url}'";
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
    $createdAt = Carbon::now()->toDateTimeString();
    $sql = "INSERT INTO urls (name, created_at) VALUES
        (:name, :createdAt)";
    $query = $dbh->prepare($sql);
    $query->execute([':name' => $url, ':createdAt' => $createdAt]);
}

/**
 * @param string $url
 * @return string
 */
function getUrlId($url)
{
    $sql = "SELECT id
        FROM urls
        WHERE name = '{$url}'";
    $matches = query($sql);
    return $matches[0]['id'];
}

/**
 * @param int $urlId
 * @return array<mixed>
 */
function getUrlInfo($urlId)
{
    $sql = "SELECT id, name, created_at
        FROM urls
        WHERE id = '{$urlId}'";
    $matches = query($sql);
    return $matches[0];
}

/**
 * @return array<mixed>
 */
function getUrls()
{
    $sql = "SELECT DISTINCT ON (urls.id) urls.id as url_id,
        urls.name as url_name,
        url_checks.created_at as url_last_check,
        url_checks.status_code as url_last_status_code
        FROM urls
        LEFT JOIN url_checks
        ON urls.id = url_checks.url_id
        ORDER BY urls.id DESC, url_last_check DESC";
    $matches = query($sql);
    return $matches;
}

/**
 * @param int $urlId
 * @return void
 */
function insertUrlCheck($urlId, $statusCode): void
{
    $dbh = connect();
    $createdAt = Carbon::now()->toDateTimeString();
    $sql = "INSERT INTO url_checks (url_id, status_code, created_at) VALUES
        (:urlId, :statusCode, :createdAt)";
    $query = $dbh->prepare($sql);
    $query->execute([':urlId' => $urlId, ':statusCode' => $statusCode, ':createdAt' => $createdAt]);
}

/**
 * @param int $urlId
 * @return array<mixed>
 */
function getUrlChecks($urlId)
{
    $sql = "SELECT id, status_code, created_at 
        FROM url_checks
        WHERE url_id = '{$urlId}'
        ORDER BY id ASC";
    $matches = query($sql);
    return $matches;
}

function getStatusCode($url)
{
    print "here";
    $client = new Client([
        'base_uri' => $url,
        'timeout'  => 3.0,
        'allow_redirects' => false
    ]);
    try {
        $request = $client->request('GET', '');
    } catch (ConnectException $e) {
        return false;
    } catch (ClientException $e) {
        return $e->getResponse()->getStatusCode();
    } catch (ServerException $e) {
        return $e->getResponse()->getStatusCode();
    }
    return $request->getStatusCode();
}
