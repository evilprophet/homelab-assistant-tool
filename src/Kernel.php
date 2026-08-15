<?php

declare(strict_types=1);

namespace EvilStudio\HAT;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected const string APPLICATION_NAME = 'HAT (HomeLab Assistant Tools)';

    public function __construct(string $environment, bool $debug)
    {
        // Doctrine hydrates DATETIME_IMMUTABLE without a timezone, so the value picks
        // up PHP's default. Timestamps are stored as UTC wall time, so anything else
        // here makes the app read them as local time and display them unshifted.
        // The configured `timezone` parameter still drives all display conversion.
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
    }

    public function getName(): string
    {
        return self::APPLICATION_NAME;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $parametersFilePath = $this->getProjectDir() . '/config/parameters.yaml';
        if (is_file($parametersFilePath)) {
            $container->import($parametersFilePath);
        } elseif ($this->environment !== 'test') {
            throw new RuntimeException(
                sprintf(
                    "Missing required configuration file '%s'. Run 'hat:setup:configure' first.",
                    $parametersFilePath
                )
            );
        }

        $configDir = $this->getProjectDir() . '/config';

        $container->import($configDir . '/{packages}/*.yaml');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.yaml');
        $container->import($configDir . '/{services}.yaml');
        $container->import($configDir . '/{services}_' . $this->environment . '.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = $this->getProjectDir() . '/config';

        $routesDirectory = $configDir . '/routes';
        if (is_dir($routesDirectory)) {
            $environmentRoutesDirectory = $routesDirectory . '/' . $this->environment;
            if (is_dir($environmentRoutesDirectory)) {
                $routes->import($environmentRoutesDirectory . '/*.yaml', 'glob');
            }

            $routes->import($routesDirectory . '/*.yaml', 'glob');
        }

        $routesFile = $configDir . '/routes.yaml';
        if (is_file($routesFile)) {
            $routes->import($routesFile);
        }
    }
}
