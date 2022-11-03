<?php

namespace PageAnalyzer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class Parser
{
    public string $urlName;

    public Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout'  => 5.0,
            'allow_redirects' => false
        ]);
    }

    /**
     * @param Client|null $client
     */
    public function getHtml(string $urlName, $client = null): string
    {
        $client = $client ?? $this->client;
        return $this->client
            ->get($urlName)
            ->getBody()
            ->getContents();
    }

    /**
     * @param Client|null $client
     */
    public function getStatusCode(string $urlName, $client = null): int
    {
        $client = $client ?? $this->client;
        try {
            return $client
                ->get($urlName)
                ->getStatusCode();
        } catch (ClientException $e) {
            return $e->getResponse()->getStatusCode();
        } catch (ServerException $e) {
            return $e->getResponse()->getStatusCode();
        }
    }
}
