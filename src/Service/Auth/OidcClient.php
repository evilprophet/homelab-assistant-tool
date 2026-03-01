<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use JsonException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OidcClient
{
    protected const string DEFAULT_SCOPES = 'openid profile email';

    protected ?array $openIdConfiguration = null;

    public function __construct(
        #[Autowire('%env(string:OIDC_ISSUER)%')]
        protected string $issuer,
        #[Autowire('%env(string:OIDC_CLIENT_ID)%')]
        protected string $clientId,
        #[Autowire('%env(string:OIDC_CLIENT_SECRET)%')]
        protected string $clientSecret,
        #[Autowire('%env(string:OIDC_REDIRECT_URI)%')]
        protected string $redirectUri,
        #[Autowire('%env(string:HAT_OIDC_PROVIDER_NAME)%')]
        protected string $providerName
    ) {
    }

    public function getProviderName(): string
    {
        $name = trim($this->providerName);

        return $name === '' ? 'OIDC Provider' : $name;
    }

    public function buildAuthorizationUrl(string $state): string
    {
        $authorizationEndpoint = $this->getRequiredOpenIdConfigurationValue('authorization_endpoint');
        $query = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => self::DEFAULT_SCOPES,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return sprintf('%s?%s', $authorizationEndpoint, $query);
    }

    public function exchangeCodeForAccessToken(string $code): string
    {
        $tokenEndpoint = $this->getRequiredOpenIdConfigurationValue('token_endpoint');
        $payload = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->requestJson(
            $tokenEndpoint,
            'POST',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            $payload
        );

        $accessToken = $response['access_token'] ?? null;
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException('OIDC token response is missing access_token.');
        }

        return $accessToken;
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $userInfoEndpoint = $this->getRequiredOpenIdConfigurationValue('userinfo_endpoint');

        return $this->requestJson(
            $userInfoEndpoint,
            'GET',
            [
                sprintf('Authorization: Bearer %s', $accessToken),
                'Accept: application/json',
            ]
        );
    }

    protected function getRequiredOpenIdConfigurationValue(string $key): string
    {
        $config = $this->getOpenIdConfiguration();
        $value = $config[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf("OIDC discovery value '%s' is missing.", $key));
        }

        return $value;
    }

    protected function getOpenIdConfiguration(): array
    {
        if ($this->openIdConfiguration !== null) {
            return $this->openIdConfiguration;
        }

        $issuer = rtrim(trim($this->issuer), '/');
        if ($issuer === '') {
            throw new RuntimeException('OIDC_ISSUER cannot be empty.');
        }

        $discoveryUrl = sprintf('%s/.well-known/openid-configuration', $issuer);
        $this->openIdConfiguration = $this->requestJson($discoveryUrl, 'GET', ['Accept: application/json']);

        return $this->openIdConfiguration;
    }

    protected function requestJson(string $url, string $method, array $headers = [], ?string $body = null): array
    {
        $httpHeaders = $headers;
        if (!in_array('Accept: application/json', $httpHeaders, true)) {
            $httpHeaders[] = 'Accept: application/json';
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $httpHeaders),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $statusCode = $this->extractStatusCode($responseHeaders);

        if ($responseBody === false) {
            throw new RuntimeException(sprintf('OIDC request failed for URL: %s', $url));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                sprintf('OIDC request failed with HTTP status %d for URL: %s', $statusCode, $url)
            );
        }

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('OIDC response is not valid JSON for URL: %s', $url),
                (int)$exception->getCode(),
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('OIDC response JSON must be an object for URL: %s', $url));
        }

        return $decoded;
    }

    protected function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/', $header, $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return 0;
    }
}
