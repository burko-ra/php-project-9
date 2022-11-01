<?php

namespace PageAnalyzer\Engine;

use Valitron\Validator;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use DiDom\Document;

use function DI\value;

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
 * @return array<mixed>
 */
function getUrlChecks(int $urlId)
{
    $sql = "SELECT * 
        FROM url_checks
        WHERE url_id = '{$urlId}'
        ORDER BY id ASC";
    $matches = query($sql);
    return $matches;
}

/**
 * @param string $url
 * @param Client $client
 * @return array<int|string>|false
 */
function getParsedData(string $url, Client $client)
{
    $createdAt = Carbon::now()->toDateTimeString();
    $parsedData = ['created_at' => $createdAt];

    try {
        $responce = $client->get($url);
    } catch (ClientException $e) {
        $parsedData['status_code'] = $e->getResponse()->getStatusCode();
        return $parsedData;
    } catch (ServerException $e) {
        $parsedData['status_code'] = $e->getResponse()->getStatusCode();
        return $parsedData;
    } catch (ConnectException $e) {
        return false;
    } catch (RequestException $e) {
        return false;
    }

    $statusCode = $responce->getStatusCode();
    $parsedData['status_code'] = $statusCode;

    if ($statusCode !== 200) {
        return $parsedData;
    }

    $html = $responce->getBody()->getContents();
    $document = new Document($html);

    $headers = $document->find('h1');
    if (count($headers) > 0) {
        /**
         * @var \DiDom\Element $h1
         */
        $h1 = $headers[0];
        $parsedData['h1'] = $h1->text();
    }

    $description = $document->find('meta[name=description]');
    if (count($description) > 0) {
        $parsedData['description'] = $description[0]->getAttribute('content') ?? '';
    }

    $titles = $document->find('title');
    if (count($titles) > 0) {
        /**
         * @var \DiDom\Element $title
         */
        $title = $titles[0];
        $parsedData['title'] = $title->text();
    }

    return $parsedData;
}

/**
 * @param int $urlId
 * @param array<mixed> $parsedData
 * @return void
 */
function insertUrlCheck(int $urlId, $parsedData): void
{
    $dbh = connect();
    $createdAt = $parsedData['created_at'];
    $statusCode = $parsedData['status_code'];
    $h1 = $parsedData['h1'] ?? '';
    $title = $parsedData['title'] ?? '';
    $description = $parsedData['description'] ?? '';
    $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at) VALUES
        (:urlId, :statusCode, :h1, :title, :description, :createdAt)";
    $query = $dbh->prepare($sql);
    $query->execute([
        ':urlId' => $urlId,
        ':statusCode' => $statusCode,
        'h1' => $h1,
        'title' => $title,
        'description' => $description,



        ':createdAt' => $createdAt
    ]);
}
