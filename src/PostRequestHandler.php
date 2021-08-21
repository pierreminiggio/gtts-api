<?php

namespace App;

use Exception;
use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

class PostRequestHandler
{

    public function run(
        string $path,
        ?string $body,
        ?string $authHeader,
        string $processedCacheFolder,
        string $speecheloCacheFolder,
        string $host,
        ?string $queryParameters
    ): void
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

            $defaultEnhanceValue = '1';
            $enhance = $defaultEnhanceValue;

            if ($queryParameters !== null) {
                parse_str(substr($queryParameters, 1), $parsedParameters);
                $enhance = isset($parsedParameters['enhance']) && $parsedParameters['enhance'] !== $defaultEnhanceValue
                    ? '0'
                    : $defaultEnhanceValue
                ;
            }

            $processedName = sha1(base64_encode($text)) . ($enhance !== $defaultEnhanceValue ? '_raw' : '');
            $filename = $processedName . '.mp3';
            $completeFileName = $processedCacheFolder . $filename;

            $processedUrl = $host . '/public/cache/processed/' . $processedName . '.mp3';

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
                    $enhance === $defaultEnhanceValue ? 180 : 60,
                    0,
                    [
                        'text' => $text,
                        'lang' => $lang,
                        'tld' => $tld,
                        'enhance' => $enhance
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
        } elseif ($path === '/speechelo') {
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

            if (! isset($jsonBody['text']) || ! isset($jsonBody['voice'])) {
                http_response_code(400);

                return;
            }

            $text = $jsonBody['text'];
            $voice = $jsonBody['voice'];

            if (! file_exists($speecheloCacheFolder)) {
                mkdir($speecheloCacheFolder);
            }

            $enhance = true;

            if ($queryParameters !== null) {
                parse_str(substr($queryParameters, 1), $parsedParameters);
                $enhance = isset($parsedParameters['enhance']) || $parsedParameters['enhance'] === '1';
            }

            $enhancedspeecheloName = sha1(base64_encode($text));
            $rawSpeecheloName = $enhancedspeecheloName . '_raw';
            $speecheloName = $enhance ? $enhancedspeecheloName : $rawSpeecheloName;
            $filename = $speecheloName . '.mp3';
            $completeFileName = $speecheloCacheFolder . $filename;

            $speecheloUrl = $host . '/public/cache/speechelo/' . $speecheloName . '.mp3';

            if (file_exists($completeFileName)) {
                $this->showFileUrl($speecheloUrl);
            }

            $completeRawFileName = $speecheloCacheFolder . $rawSpeecheloName . '.mp3';

            $githubActionRunStarterAndArtifactDownloader = (
                new GithubActionRunStarterAndArtifactDownloaderFactory()
            )->make();

            if (! file_exists($completeRawFileName)) {
                set_time_limit(0);

                $speecheloProjects = $config['speecheloProjects'];
                $speecheloProject = $speecheloProjects[array_rand($speecheloProjects)];

                try {
                    $artifacts = $githubActionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
                        $speecheloProject['token'],
                        $speecheloProject['account'],
                        $speecheloProject['project'],
                        'get-sound.yml',
                        70,
                        0,
                        [
                            'text' => $text,
                            'voice' => $voice
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

                $speecheloMP3Url = trim(file_get_contents($artifact));
            
                $fp = fopen($completeRawFileName, 'w+');
                $ch = curl_init($speecheloMP3Url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);

                if ($httpCode !== 200) {
                    unlink($completeRawFileName);
                    http_response_code(500);

                    return;
                }
            }

            if ($enhance) {
                $rawSpeecheloUrl = $host . '/public/cache/speechelo/' . $rawSpeecheloName . '.mp3';
                $enhanceProjects = $config['enhanceProjects'];
                $enhanceProject = $enhanceProjects[array_rand($enhanceProjects)];

                try {
                    $artifacts = $githubActionRunStarterAndArtifactDownloader->runActionAndGetArtifacts(
                        $enhanceProject['token'],
                        $enhanceProject['account'],
                        $enhanceProject['project'],
                        'enhance.yml',
                        140,
                        0,
                        [
                            'url' => $rawSpeecheloUrl
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
            }
            
            if (file_exists($completeFileName)) {
                $this->showFileUrl($speecheloUrl);
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
