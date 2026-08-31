<?php

use Illuminate\Http\Request;

$proxies = env('TRUSTED_PROXIES', '*');

if (is_string($proxies) && $proxies !== '*' && $proxies !== '**') {
    $proxies = array_values(array_filter(array_map('trim', explode(',', $proxies))));
}

return [

    'proxies' => $proxies,

    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB,
];
