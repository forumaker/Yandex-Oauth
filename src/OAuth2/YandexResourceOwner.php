<?php

namespace forumaker\Yandex\OAuth2;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class YandexResourceOwner implements ResourceOwnerInterface
{
    public function __construct(protected array $response = [])
    {
    }

    public function getId(): ?string
    {
        return isset($this->response['id']) ? (string) $this->response['id'] : null;
    }

    public function getLogin(): ?string
    {
        return $this->response['login'] ?? null;
    }

    public function getRealName(): ?string
    {
        $realName = $this->response['real_name'] ?? null;

        if (is_string($realName) && trim($realName) !== '') {
            return $realName;
        }

        $first = trim((string) ($this->response['first_name'] ?? ''));
        $last = trim((string) ($this->response['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }

    public function getEmail(): ?string
    {
        return $this->response['default_email']
            ?? $this->response['email']
            ?? null;
    }

    public function getAvatarId(): ?string
    {
        return $this->response['default_avatar_id'] ?? null;
    }

    public function getAvatarUrl(int $size = 200): ?string
    {
        $avatarId = $this->getAvatarId();

        if (! $avatarId || ($this->response['is_avatar_empty'] ?? false) === true) {
            return null;
        }

        return "https://avatars.yandex.net/get-yapic/{$avatarId}/islands-{$size}";
    }

    public function toArray(): array
    {
        return $this->response;
    }
}