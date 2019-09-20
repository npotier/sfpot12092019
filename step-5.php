<?php

// Exemple 5 : une commande qui récupère les albums d'un artiste
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class GetAlbumsCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'get-albums';

    protected function configure()
    {
        $this
            ->setDescription('Get albums from an artist')
            ->addArgument('artistName', InputArgument::REQUIRED, 'The artist name')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $artistName = $input->getArgument('artistName');

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/.env');

        $client = HttpClient::create();

        $url = "http://ws.audioscrobbler.com/2.0/";
        
        $response = $client->request('GET', $url, [
            "query" => [
                'method' => 'artist.getTopAlbums',
                'artist' => $artistName,
                "api_key" => $_ENV['STEP_5_API_KEY'],
                "format" => "json"
            ]    
        ]);
        $albums = $response->toArray();
        
        $lines = [];

        foreach ($albums['topalbums']['album'] as $album)
        {
            if (isset($album['mbid'])) {
                $lines[] = [
                    $album['mbid'],
                    $album['name'], 
                    $album['url']
                    
                ];
            }
        }
        $table = new Table($output);
        $table
        ->setHeaders(['Id', 'Album', 'url'])
        ->setRows($lines);     

        $table->render();

    }
}



$application = new Application();

$application->add(new GetAlbumsCommand());

$application->run();
