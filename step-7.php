<?php

// Exemple : Utilisation du cache
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        $cache = new TagAwareAdapter(
            new FilesystemAdapter(
                // the subdirectory of the main cache directory where cache items are stored
                $namespace = '',
                // in seconds; applied to cache items that don't define their own lifetime
                // 0 means to store the cache items indefinitely (i.e. until the files are deleted)
                $defaultLifetime = 0,
                // the main cache directory (the application needs read-write permissions on it)
                // if none is specified, a directory is created inside the system temporary directory
                $directory = "./tmp-with-tag"
            )
        );        

        $artistName = $input->getArgument('artistName');

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/.env');

        $client = HttpClient::create();

        $url = "http://ws.audioscrobbler.com/2.0/";
        
        $albums = $cache->get('albums_'.md5($artistName), function (ItemInterface $item) use($client, $url, $artistName) {
            
            $item->expiresAfter(3600);
            
            $item->tag(['albums', $artistName]);

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
        
        // $cache->invalidateTags(['albums']);
        // $cache->invalidateTags(['artistName']);
        $lines = [];

        $i = 1;
        foreach ($albums['topalbums']['album'] as $album)
        {
            if (isset($album['mbid'])) {
                $lines[] = [
                    $i,
                    $album['mbid'],
                    $album['name'], 
                    $album['url']
                    
                ];
                $i++;
            }
        }
        $table = new Table($output);
        $table
            ->setHeaders(['Choice', 'Id', 'Album', 'url'])
            ->setRows($lines)
        ;     

        $output->write(sprintf("\033\143"));
        $table->render();

        $question = new Question('Which album do you choose ? ', '1');

        $helper = $this->getHelper('question');
        $albumNumber = $helper->ask($input, $output, $question);
        $album = $lines[$albumNumber-1];

        $output->writeln('You chose album the album : <info>'.$album[2]."</info>");

        $url = 'https://www.googleapis.com/youtube/v3/search';
        $response = $client->request('GET', $url, [
            "query" => [
                'part'          => 'id, snippet',
                'type'          => 'playlist',
                'q'             => $artistName . ' - ' .$album[2],
                "key"           => $_ENV['YOUTUBE_API_KEY'],
                "maxResults"    => 1,
                'topicId'       => '/m/04rlf'
            ]    
        ]);    
        
        $result = $response->toArray();
        if (!isset($result['items']) || sizeof($result['items']) != 1) {
            $output->writeln('<error>No results found in Youtube API</error>');
            return;
        }

        $playlistId = $result['items'][0]['id']['playlistId'];

        $url = 'https://www.googleapis.com/youtube/v3/playlistItems';
        $response = $client->request('GET', $url, [
            "query" => [
                'part'          => 'id, snippet',
                'playlistId'    => $playlistId,
                'fields'        => 'items',
                'maxResults'    => 50,
                "key"           => $_ENV['YOUTUBE_API_KEY']
            ]    
        ]);    

        $result = $response->toArray();
        $tracks = [];
        $i = 1;
        foreach($result['items'] as $track) {           
            $tracks[] = [
                $i,
                $track['snippet']['title'],
                $track['snippet']['resourceId']['videoId'],
            ];
            $i++;
        }

        $trackNumber = null;
        while(true) {
            $table = new Table($output);
            $table
                ->setHeaders(['Choice', 'Name', 'Id'])
                ->setRows($tracks)
            ;     

            $output->write(sprintf("\033\143"));
            $output->writeln('Current album : '.$album[2]);
            $table->render();

            if (!$trackNumber) {
                $question = new Question('Which song do you choose ? ', '1');

                $helper = $this->getHelper('question');
                $trackNumber = $helper->ask($input, $output, $question);
            }

            $track = $tracks[$trackNumber-1];

            $process = new Process([
                './youtube-dl', 
                '--extract-audio', 
                '--format', 
                'bestaudio[ext=m4a]',
                '--ffmpeg-location', 
                './ffmpeg',
                '--output',
                'tmp/'.md5($artistName.' - '.$track[1]).'.m4a',

                'https://www.youtube.com/watch?v='.$track[2]
            ]);

            $process->mustRun(function ($type, $buffer) use ($output) {
                if (Process::ERR === $type) {
                    $output->write(sprintf("\033\143"));
                    $output->writeln($buffer);
                } else {
                    $output->write(sprintf("\033\143"));
                    $output->writeln($buffer);
                }
            });  
            
            $output->write(sprintf("\033\143"));
            $output->writeln("Now playing : <info>".$artistName.' - '.$track[1]."</info>");
            $output->writeln('Song from : https://www.youtube.com/watch?v='.$track[2]);
            $process = new Process([
            'afplay', 
            'tmp/'.md5($artistName.' - '.$track[1]).'.m4a',
            ]);
            $process->start();

            $question = new Question('Available commands : <info>(s)</info> Stop | <info>(n)</info> Next | <info>(p)</info> Previous ');

            $helper = $this->getHelper('question');
            $command = $helper->ask($input, $output, $question);
            $process->stop(3, SIGINT);
            
            if($command == "n") {
                $trackNumber++;
            } elseif($command == "p") {
                $trackNumber--;
            }
            else {
                $trackNumber = null;
            }


        }
    }
}

$application = new Application();

$application->add(new GetAlbumsCommand());

$application->run();
