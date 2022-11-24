<?php

namespace PageAnalyzer\UrlNormalizer;

/**
 * @return string|false
 */
function normalizeUrl(string $urlName)
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
