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

    public function getHtml(): string
    {
        return $this->client
            ->get($this->urlName)
            ->getBody()
            ->getContents();
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
}
