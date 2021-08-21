<?php

namespace App;

use Illuminate\Support\Str;

class App
{
    public function run(
        string $path,
        ?string $queryParameters,
        string $method,
        ?string $body,
        ?string $authHeader,
        string $host
    ): void
    {
        $cacheFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'cache'
            . DIRECTORY_SEPARATOR
        ;

        $langsFile = $cacheFolder . 'langs.json';

        if (! file_exists($langsFile)) {
            $commandResult = shell_exec('LC_CTYPE=en_US.utf8 gtts-cli --all');
            $lines = explode(PHP_EOL, $commandResult);
            $langs = array_filter(array_map(fn (string $line): string => trim(explode(': ', $line)[0]), $lines));
            file_put_contents($langsFile, json_encode($langs));
        } else {
            $langs = json_decode(file_get_contents($langsFile), true);
        }
        
        if ($path === '/') {
            http_response_code(200);
            echo json_encode(['langs' => $langs]);

            return;
        }

        $processedString = '/processed/';
        $processedCacheFolder = $cacheFolder . 'processed' . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $processedString)) {
            $identifier = substr($path, strlen($processedString));
            $filename = $identifier . '.mp3';
            $completeFileName = $processedCacheFolder . $filename;

            if (! file_exists($completeFileName)) {
                http_response_code(404);

                return;
            }

            (new MP3FileRenderer())->show($filename, $completeFileName);
        }

        $speecheloString = '/speechelo/';
        $speecheloCacheFolder = $cacheFolder . 'speechelo' . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $speecheloString)) {
            $identifier = substr($path, strlen($speecheloString));
            $filename = $identifier . '.mp3';
            $completeFileName = $speecheloCacheFolder . $filename;

            if (! file_exists($completeFileName)) {
                http_response_code(404);

                return;
            }

            (new MP3FileRenderer())->show($filename, $completeFileName);
        }

        if (! file_exists($cacheFolder)) {
            mkdir($cacheFolder);
        }

        if ($method === 'POST') {
            (new PostRequestHandler())->run($path, $body, $authHeader, $processedCacheFolder, $speecheloCacheFolder, $host, $queryParameters);

            return;
        }

        $lang = 'fr';

        if ($queryParameters) {
            parse_str(substr($queryParameters, 1), $parsedParameters);

            if (isset($parsedParameters['lang'])) {
                $lang = strtolower($parsedParameters['lang']);
            }
        }

        if (! in_array($lang, $langs)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Lang']);

            return;
        }

        $text = urldecode(substr($path, 1));

        $filename = Str::slug($text, '_') . '_' . $lang . '.mp3';
        $completeFileName =
            $cacheFolder
            . $filename
        ;

        if (! file_exists($completeFileName)) {
            shell_exec(
                'LC_CTYPE=en_US.utf8 gtts-cli '
                . escapeshellarg($text)
                . ' --output '
                . escapeshellarg($completeFileName)
                . ' --lang '
                . $lang
            );
        }

        (new MP3FileRenderer())->show($filename, $completeFileName);
    }
}
