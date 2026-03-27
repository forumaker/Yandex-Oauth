<?php

namespace forumaker\Yandex\Providers;

use Flarum\Forum\Auth\Registration;
use FoF\OAuth\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use forumaker\Yandex\OAuth2\YandexProvider;
use forumaker\Yandex\OAuth2\YandexResourceOwner;

class Yandex extends Provider
{
    public function name(): string
    {
        return 'yandex';
    }

    public function link(): string
    {
        return 'https://id.yandex.ru';
    }

    public function fields(): array
    {
        return [
            'client_id' => 'yandex_client_id_label',
            'client_secret' => 'yandex_client_secret_label',
        ];
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        return new YandexProvider([
            'clientId' => $this->getSetting('client_id'),
            'clientSecret' => $this->getSetting('client_secret'),
            'redirectUri' => $redirectUri,
        ]);
    }

    public function pkceEnabled(): bool
    {
        return false;
    }

    public function suggestions(Registration $registration, mixed $user, string $token): void
    {
        if (! $user instanceof YandexResourceOwner) {
            return;
        }

        if ($user->getEmail()) {
            $registration->provideTrustedEmail($user->getEmail());
        }

        if ($user->getLogin()) {
            $registration->suggestUsername($this->sanitizeUsername($user->getLogin()));
        } elseif ($user->getRealName()) {
            $registration->suggestUsername($this->sanitizeUsername($user->getRealName()));
        }

        if ($user->getRealName()) {
            $registration->provideNickname($user->getRealName());
        }

        if ($user->getAvatarUrl()) {
            $registration->provideAvatar($user->getAvatarUrl());
        }
    }

    protected function sanitizeUsername(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\pL\pN_\-]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return mb_substr($value !== '' ? $value : 'yandex-user', 0, 30);
    }
}