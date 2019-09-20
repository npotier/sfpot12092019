<?php

// Exemple 4 : simple GET avec une authentification

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$client = HttpClient::create();

$url = "https://postman-echo.com/basic-auth";

$response = $client->request('GET', $url, [
    'auth_basic' => [
        $_ENV['STEP_4_USERNAME'], 
        $_ENV['STEP_4_PASSWORD']
    ],
]);

dump([
    $response->getStatusCode(),
    $response->toArray()
]);