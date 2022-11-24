<?php

namespace PageAnalyzer\Support\Helpers;

use DiDom\Exceptions\InvalidSelectorException;
use PageAnalyzer\Support\Optional;

function optional($attribute)
{
    if ($attribute !== null) {
        return $attribute;
    }
    return new Optional();
}

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
