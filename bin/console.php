<?php

require __DIR__ . '/../vendor/autoload.php';

use EvilStudio\HAT\Application;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();

$applicationDirectory = __DIR__ . '/..';
$container->setParameter('application_directory', $applicationDirectory);
$container->setParameter('log_directory', sprintf('%s/var/log', $applicationDirectory));

$loader = new YamlFileLoader($container, new FileLocator());
$loader->load(__DIR__ . '/../config/parameters.yaml');
$loader->load(__DIR__ . '/../config/services.yaml');

$container->compile();

$container->get(Application::class)->run();
