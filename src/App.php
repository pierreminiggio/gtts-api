<?php

namespace App;

use Illuminate\Support\Str;

class App
{
    public function run(string $path, ?string $queryParameters): void
    {

        $cacheFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'cache'
        ;

        $langsFile = $cacheFolder . DIRECTORY_SEPARATOR . 'langs.json';

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
            . DIRECTORY_SEPARATOR
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

        header('Content-type: audio/mpeg');
        header('Content-length: ' . filesize($completeFileName));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('X-Pad: avoid browser bug');
        header('Cache-Control: no-cache');
        readfile($completeFileName);
    }
}
