<?php

namespace PageAnalyzer\Tests;

use PHPUnit\Framework\TestCase;
use PageAnalyzer\Parser;

class ParserTest extends TestCase
{
    protected $client;
    protected $code;

    public function setUp(): void
    {
        $code = 200;

        $stub = $this->createMock(\GuzzleHttp\Client::class);
        $stub->method('get')
            ->willReturn($this->returnSelf());
        $stub->method('getStatusCode')
        ->will($code);

        $this->code = $code;

        $this->client = $stub;
    }

    public function testGetStatusCode(): void
    {
        $parser = new Parser($this->client);
        $this->assertEquals($this->code, $parser->getStatusCode('https://stub'));
    }
}
