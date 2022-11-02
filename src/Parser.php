<?php

namespace PageAnalyzer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class Parser
{
    public string $urlName;
    protected Client $client;

    public function __construct(string $urlName, float $timeout = 5.0, bool $allowRedirects = false)
    {
        $this->urlName = $urlName;
        $this->client = new Client([
            'base_uri' => $urlName,
            'timeout'  => $timeout,
            'allow_redirects' => $allowRedirects
        ]);
    }

    public function getStatusCode(): int
    {
        try {
            return $this->client
                ->get($this->urlName)
                ->getStatusCode();
        } catch (ClientException $e) {
            return $e->getResponse()->getStatusCode();
        } catch (ServerException $e) {
            return $e->getResponse()->getStatusCode();
        }
    }

    public function getHtml(): string
    {
        return $this->client
            ->get($this->urlName)
            ->getBody()
            ->getContents();
    }

    // public function parse(string $url)
    // {
    //     $createdAt = Carbon::now()->toDateTimeString();
    //     $parsedData = ['created_at' => $createdAt];

    //     try {
    //         $response = $client->get($url);
    //     } catch (ClientException $e) {
    //         $parsedData['status_code'] = $e->getResponse()->getStatusCode();
    //         return $parsedData;
    //     } catch (ServerException $e) {
    //         $parsedData['status_code'] = $e->getResponse()->getStatusCode();
    //         return $parsedData;
    //     } catch (ConnectException $e) {
    //         return false;
    //     } catch (RequestException $e) {
    //         return false;
    //     }

    //     $statusCode = $response->getStatusCode();
    //     $parsedData['status_code'] = $statusCode;

    //     if ($statusCode !== 200) {
    //         return $parsedData;
    //     }

    //     $html = $response->getBody()->getContents();
    //     $document = new \DiDom\Document($html);

    //     $headers = $document->find('h1');
    //     if (count($headers) > 0) {
    //         /**
    //          * @var \DiDom\Element $h1
    //          */
    //         $h1 = $headers[0];
    //         $parsedData['h1'] = $h1->text();
    //     }

    //     $description = $document->find('meta[name=description]');
    //     if (count($description) > 0) {
    //         $parsedData['description'] = $description[0]->getAttribute('content') ?? '';
    //     }

    //     $titles = $document->find('title');
    //     if (count($titles) > 0) {
    //         /**
    //          * @var \DiDom\Element $title
    //          */
    //         $title = $titles[0];
    //         $parsedData['title'] = $title->text();
    //     }

    //     return $parsedData;
    // }

    // public function getTextFromTag($tag)
    // {
    //     $tags = $document->find('h1');
    //     if (count($headers) > 0) {
    //         /**
    //          * @var \DiDom\Element $h1
    //          */
    //         $h1 = $headers[0];
    //         $parsedData['h1'] = $h1->text();
    //     }

    // }
}
