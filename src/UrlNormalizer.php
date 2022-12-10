<?php

namespace PageAnalyzer\UrlNormalizer;

/**
 * @return string|false
 */
function normalizeUrl(string $urlName)
{
    $urlParts = parse_url(mb_strtolower($urlName));
    if ($urlParts === false) {
        return false;
    }
    return $urlParts['scheme'] . '://' . $urlParts['host'];
}
