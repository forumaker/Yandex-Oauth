<?php

namespace forumaker\Yandex\OAuth2;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class YandexProvider extends AbstractProvider
{
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://oauth.yandex.ru/authorize';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://oauth.yandex.ru/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://login.yandex.ru/info';
    }

    protected function getDefaultScopes(): array
    {
        return [];
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (isset($data['error'])) {
            $message = $data['error_description'] ?? $data['message'] ?? $data['error'];

            throw new IdentityProviderException((string) $message, (int) $response->getStatusCode(), $data);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token): YandexResourceOwner
    {
        return new YandexResourceOwner($response);
    }

    protected function getAuthorizationParameters(array $options): array
    {
        $options = parent::getAuthorizationParameters($options);

        if (empty($options['scope'])) {
            unset($options['scope']);
        }

        return $options;
    }

    protected function getDefaultHeaders(): array
    {
        return ['Accept' => 'application/json'] + parent::getDefaultHeaders();
    }

    public function getResourceOwner(AccessToken $token): YandexResourceOwner
    {
        $request = $this->getAuthenticatedRequest(
            self::METHOD_GET,
            $this->getResourceOwnerDetailsUrl($token),
            $token,
            ['headers' => ['Authorization' => 'OAuth ' . $token->getToken()]]
        );

        $response = $this->getParsedResponse($request);

        return $this->createResourceOwner($response, $token);
    }
}