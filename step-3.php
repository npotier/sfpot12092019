<?php

// Exemple 3 : simple PUT de données en mode formulaire

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

$client = HttpClient::create();

$url = "https://postman-echo.com/put";

$formFields = [
    'title' => 'foo2',
    'body' => 'bar2',
    'file_field' => DataPart::fromPath('./step-3-file.txt'),
];
$formData = new FormDataPart($formFields);

$response = $client->request('PUT', $url, [
    'headers' => $formData->getPreparedHeaders()->toArray(),
    'body' => $formData->bodyToIterable()
]);

dump([
    $response->getStatusCode(),
    $response->toArray()
]);