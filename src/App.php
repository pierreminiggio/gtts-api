<?php

namespace App;

use Illuminate\Support\Str;

class App
{
    public function run(string $path, ?string $queryParameters): void
    {
        if ($path === '/') {
            http_response_code(404);

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
            exec(
                'gtts-cli '
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
