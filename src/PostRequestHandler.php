<?php

namespace App;

use Exception;
use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

class PostRequestHandler
{

    public function run(string $path, ?string $body, ?string $authHeader, string $processedCacheFolder, string $host): void
    {
        $projectDirectory = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

        $config = (new ConfigProvider($projectDirectory))->get();
        $token = $config['token'];

        if ($path === '/processed') {
            $this->checkToken($authHeader, $token);

            if (! $body) {
                http_response_code(400);

                return;
            }

            $jsonBody = json_decode($body, true);

            if (! $jsonBody) {
                http_response_code(400);

                return;
            }

            if (! isset($jsonBody['text']) || ! isset($jsonBody['lang']) || ! isset($jsonBody['tld'])) {
                http_response_code(400);

                return;
            }

            $text = $jsonBody['text'];
            $lang = $jsonBody['lang'];
            $tld = $jsonBody['tld'];

            if (! file_exists($processedCacheFolder)) {
                mkdir($processedCacheFolder);
            }

            $processedName = sha1(base64_encode($text));
            $filename = $processedName . '.mp3';
            $completeFileName = $processedCacheFolder . $filename;

            $processedUrl = $host . '/processed/' . $processedName;

            if (file_exists($completeFileName)) {
                $this->showFileUrl($processedUrl);
            }

            $voiceProjects = $config['voiceProjects'];
            $voiceProject = $voiceProjects[array_rand($voiceProjects)];

            $githubActionRunStarterAndArtifactDownloader = (
                new GithubActionRunStarterAndArtifactDownloaderFactory()
            )->make();

            set_time_limit(0);

            try {
                $artifacts = $githubActionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
                    $voiceProject['token'],
                    $voiceProject['account'],
                    $voiceProject['project'],
                    'get-sound.yml',
                    300,
                    0,
                    [
                        'text' => $text,
                        'lang' => $lang,
                        'tld' => $tld
                    ]
                );
            } catch (Exception) {
                http_response_code(500);

                return;
            }

            if (! $artifacts) {
                http_response_code(500);

                return;
            }

            $artifact = $artifacts[0];

            if (! file_exists($artifact)) {
                http_response_code(500);

                return;
            }

            rename($artifact, $completeFileName);
            
            if (file_exists($completeFileName)) {
                $this->showFileUrl($processedUrl);
            }

            http_response_code(500);

            return;
        }

        http_response_code(404);
    }

    protected function checkToken(?string $authHeader, string $validToken): void
    {
        if ($authHeader && $authHeader === 'Bearer ' . $validToken) {
            return;
        }

        http_response_code(401);
        exit;
    }

    protected function showFileUrl(string $processedUrl): void
    {
        http_response_code(200);
        echo json_encode(['url' => $processedUrl]);

        exit;
    }
}
