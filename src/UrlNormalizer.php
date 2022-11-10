<?php

namespace PageAnalyzer;

class UrlNormalizer
{
    /**
     * @return string|false
     */
    public function normalize(string $urlName)
    {
        $urlParts = parse_url(strtolower($urlName));
        if ($urlParts === false) {
            return false;
        }
        $scheme = $urlParts['scheme'] ?? '';
        $host = $urlParts['host'] ?? '';
        $separator = $scheme === '' ? '' : '://';
        return $scheme . $separator . $host;
    }
}
