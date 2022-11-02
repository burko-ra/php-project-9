<?php

namespace PageAnalyzer;

class Validator
{
    public function normalize(string $urlName): string
    {
        $urlParts = parse_url(strtolower($urlName));
        $scheme = $urlParts['scheme'] ?? '';
        $host = $urlParts['host'] ?? '';
        return $scheme . "://" . $host;
    }

    /**
     * @return array<int, string>
     */
    public function validate(string $urlName)
    {
        $errors = [];

        $urlParts = parse_url(trim($urlName));
        if (!$urlParts) {
            $errors[] = 'Некорректный URL';
        } elseif ($urlName === '') {
            $errors[] = 'URL не должен быть пустым';
        } else {
            $urlParts['name'] = $this->normalize($urlName);
            $validator = new \Valitron\Validator($urlParts);
            $validator->rule('required', 'scheme');
            $validator->rule('required', 'host');
            $validator->rule('lengthMax', 'name', 255);
            $validator->rule('url', 'name');
            //$validator->rule('regex', 'scheme', '/^[a-z0-9][a-z0-9\.\-]+\.[a-z]{2,}$/i');
            $validator->validate();

            if (is_bool($validator->errors())) {
                throw new \Exception('Expected array, boolean given');
            }

            if (!empty($validator->errors())) {
                $errors[] = 'Некорректный URL';
            }
        }

        return $errors;
    }
}
