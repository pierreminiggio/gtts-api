<?php

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\App;

/** @var string $requestUrl */
$requestUrl = $_SERVER['REQUEST_URI'];

/** @var string|null $queryParameters */
$queryParameters = ! empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : null;

/** @var string $calledEndPoint */
$calledEndPoint = $queryParameters
    ? str_replace($queryParameters, '', $requestUrl)
    : $requestUrl
;

if (strlen($calledEndPoint) > 1 && substr($calledEndPoint, -1) === '/') {
    /** @var string $calledEndPoint */
    $calledEndPoint = substr($calledEndPoint, 0, -1);
}

(new App())->run(
    $calledEndPoint,
    $queryParameters
);

exit;
