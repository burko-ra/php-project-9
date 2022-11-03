<?php

namespace PageAnalyzer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class Parser
{
    public string $urlName;

    public Client $client;

    /**
     * @param Client|null $client
     * @return array<mixed>
     */
    public function __construct($client = null)
    {
        $this->client = $client ?? new Client([
            'timeout'  => 5.0,
            'allow_redirects' => false
        ]);
    }

    public function getHtml(string $urlName): string
    {
        return $this->client
            //->get($this->urlName)
            ->get($urlName)
            ->getBody()
            ->getContents();
    }

    public function getStatusCode(string $urlName): int
    {
        try {
            return $this->client
                ->get($urlName)
                ->getStatusCode();
        } catch (ClientException $e) {
            return $e->getResponse()->getStatusCode();
        } catch (ServerException $e) {
            return $e->getResponse()->getStatusCode();
        }
    }
}
