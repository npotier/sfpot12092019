<?php

// Exemple 2 : simple POST de données en JSON

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

$url = "https://postman-echo.com/post";

$response = $client->request('POST', $url, [
    'json' =>  [ 
        "title"  => 'foo',
        "body"   => 'bar',
        "userId" => 1
    ]
]);

dump([
    $response->getStatusCode(),
    $response->toArray()
]);