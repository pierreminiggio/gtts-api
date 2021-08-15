<?php

namespace App;

class MP3FileRenderer
{
    
    public function show(string $filename, string $completeFileName): void
    {
        header('Content-type: audio/mpeg');
        header('Content-length: ' . filesize($completeFileName));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('X-Pad: avoid browser bug');
        header('Cache-Control: no-cache');
        readfile($completeFileName);
        exit;
    }
}
