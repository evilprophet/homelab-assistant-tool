<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new RuntimeException('APP_ENV is not configured and symfony/dotenv is not installed.');
    }

    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

foreach (['APP_ENV', 'APP_DEBUG', 'APP_SECRET'] as $requiredVariable) {
    if (!array_key_exists($requiredVariable, $_SERVER) && !array_key_exists($requiredVariable, $_ENV)) {
        throw new RuntimeException(sprintf('Missing required environment variable: %s.', $requiredVariable));
    }

    $_SERVER[$requiredVariable] = (string)($_SERVER[$requiredVariable] ?? $_ENV[$requiredVariable]);
    $_ENV[$requiredVariable] = $_SERVER[$requiredVariable];
}

$_SERVER['APP_DEBUG'] = filter_var($_SERVER['APP_DEBUG'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ? '1' : '0';
$_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'];
