<?php

namespace PageAnalyzer\Tests;

use PageAnalyzer\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    protected Validator $validator;

    public function setUp(): void
    {
        $this->validator = new Validator();
    }

    /**
     * @param array<string> $errors
     * @return void
     * @dataProvider validateProvider
     */
    public function testValidate(string $urlName, $errors): void
    {
        $this->assertEquals($errors, $this->validator->validate($urlName));
    }

    /**
     * @return array<string, mixed>
     */
    public function validateProvider()
    {
        $longUrl = 'https://' . str_repeat('qwertyuiop', 30) . "com";
        return [
            'correct url' => [
                'https://sample.com',
                []
            ],
            'empty url' => [
                '',
                ['URL не должен быть пустым']
            ],
            'url without scheme' => [
                'sample.com',
                ['Некорректный URL']
            ],
            'url without domain' => [
                'https://',
                ['Некорректный URL']
            ],
            'too short domain url' => [
                'https://.com',
                ['Некорректный URL']
            ],
            'too long domain url' => [
                $longUrl,
                ['Некорректный URL']
            ],
            'url with invalid symbols' => [
                'https://s|mple.com',
                ['Некорректный URL']
            ]
        ];
    }

    /**
     * @return void
     * @dataProvider normalizeProvider
     */
    public function testNormalize(string $urlName, string $normalizedUrlName): void
    {
        $this->assertEquals($normalizedUrlName, $this->validator->normalize($urlName));
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeProvider()
    {
        return [
            'url with some path and query' => [
                'https://sample.com/path?name=value',
                'https://sample.com'
            ],
            'empty url' => [
                '',
                '://'
            ],
            'uppercase' => [
                'https://SAMPLE.com',
                'https://sample.com'
            ]
        ];
    }
}
