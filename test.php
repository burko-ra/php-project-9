<?php

$url = 'HTTPS://GOOGLE.COM';
$urlParts = parse_url(strtolower($url));
$scheme = $urlParts['scheme'];
$host = $urlParts['host'];
$normalizedUrl = $scheme . $host;
print($normalizedUrl);

