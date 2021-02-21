<?php

namespace App;

use Illuminate\Support\Str;

class App
{
    public function run(string $path, ?string $queryParameters): void
    {
        if ($path === '/') {
            $commandResult = shell_exec('LC_CTYPE=en_US.utf8 gtts-cli --all');
            $lines = explode(PHP_EOL, $commandResult);
            $langs = array_filter(array_map(fn (string $line): string => trim(explode(': ', $line)[0]), $lines));
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

        $text = urldecode(substr($path, 1));

        $filename = Str::slug($text, '_') . '_' . $lang . '.mp3';
        $cacheFolder =
            __DIR__
            . DIRECTORY_SEPARATOR
            . '..'
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'cache'
        ;
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
