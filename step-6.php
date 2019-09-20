<?php

// Exemple 6 : Utilisation du cache
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
// new : cache
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

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
        // new : cache
        $cache = new FilesystemAdapter(
            // the subdirectory of the main cache directory where cache items are stored
            $namespace = '',
            // in seconds; applied to cache items that don't define their own lifetime
            // 0 means to store the cache items indefinitely (i.e. until the files are deleted)
            $defaultLifetime = 0,
            // the main cache directory (the application needs read-write permissions on it)
            // if none is specified, a directory is created inside the system temporary directory
            $directory = "./tmp"
        );        

        /*
        De nombreux adaptateurs de cache sont disponibles par défaut
        https://symfony.com/doc/current/components/cache.html#available-cache-adapters

        APCu Cache Adapter
        Array Cache Adapter
        Chain Cache Adapter
        Doctrine Cache Adapter
        Filesystem Cache Adapter
        Memcached Cache Adapter
        PDO & Doctrine DBAL Cache Adapter
        Php Array Cache Adapter
        Php Files Cache Adapter
        Proxy Cache Adapter
        Redis Cache Adapter
        */
        
        $artistName = $input->getArgument('artistName');

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/.env');

        $client = HttpClient::create();

        $url = "http://ws.audioscrobbler.com/2.0/";
        
        // new : cache
        $albums = $cache->get('albums_'.md5($artistName), function (ItemInterface $item) use($client, $url, $artistName) {
            
            $item->expiresAfter(3600);
            
            $response = $client->request('GET', $url, [
                "query" => [
                    'method' => 'artist.getTopAlbums',
                    'artist' => $artistName,
                    "api_key" => $_ENV['STEP_5_API_KEY'],
                    "format" => "json"
                ]    
            ]);
            
            $albums = $response->toArray();

            return $albums;
        });

        //$cache->delete('albums_'.md5($artistName));
        
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
