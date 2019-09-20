<?php
// Exemple 1 : simple GET

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

$url = "https://api.meetup.com/AFSY-Aix-Marseille-Symfony-et-PHP/events/264335660";

$response = $client->request('GET', $url);

dump([
    $response->getStatusCode(),
    $response->getHeaders()['content-type'][0],
    $response->getContent(),
    $response->toArray()
]);