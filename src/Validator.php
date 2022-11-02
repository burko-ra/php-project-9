<?php

namespace PageAnalyzer;

class Validator
{
    /**
     * @return array<string>
     */
    public function validate(string $urlName)
    {
        $errors = [];

        $urlParts = parse_url(trim($urlName));
        if (!$urlParts) {
            $errors['url'] = 'Некорректный URL';
        } elseif ($urlName === '') {
            $errors['url'] = 'URL не должен быть пустым';
        } else {
            $urlParts['name'] = $this->normalize($urlName);
            $validator = new \Valitron\Validator($urlParts);
            $validator->rule('required', 'scheme');
            $validator->rule('required', 'host');
            $validator->rule('lengthMax', 'name', 255);
            $validator->validate();

            if (is_bool($validator->errors())) {
                throw new \Exception('Expected array, boolean given');
            }

            if (!empty($validator->errors())) {
                $errors['url'] = 'Некорректный URL';
            }
        }

        return $errors;
    }

    public function normalize(string $urlName): string
    {
        $urlParts = parse_url(strtolower($urlName));
        $scheme = $urlParts['scheme'] ?? '';
        $host = $urlParts['host'] ?? '';
        return $scheme . "://" . $host;
    }
}
