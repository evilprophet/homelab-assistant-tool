<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    $projectDirectory = dirname(__DIR__);
    $dotenv = new Dotenv();
    $defaultEnvironmentFile = $projectDirectory . '/.env';
    $testEnvironmentFile = $projectDirectory . '/.env.test';

    if (is_file($defaultEnvironmentFile)) {
        $dotenv->bootEnv($defaultEnvironmentFile);
    } elseif (is_file($testEnvironmentFile)) {
        $dotenv->overload($testEnvironmentFile);
    }
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
