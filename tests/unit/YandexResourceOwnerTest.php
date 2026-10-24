<?php

declare(strict_types=1);

namespace forumaker\Yandex\Tests\unit;

use forumaker\Yandex\OAuth2\YandexResourceOwner;
use PHPUnit\Framework\TestCase;

/** What the forum learns about a visitor from Yandex ID's login.yandex.ru/info answer. */
class YandexResourceOwnerTest extends TestCase
{
    public function test_the_id_is_a_string(): void
    {
        $this->assertSame('1000034', (new YandexResourceOwner(['id' => 1000034]))->getId());
        $this->assertNull((new YandexResourceOwner([]))->getId());
    }

    public function test_the_real_name_is_preferred_then_first_and_last_name(): void
    {
        $this->assertSame('Иван Петров', (new YandexResourceOwner(['real_name' => 'Иван Петров', 'first_name' => 'Не', 'last_name' => 'Это']))->getRealName());
        $this->assertSame('Иван Петров', (new YandexResourceOwner(['real_name' => '  ', 'first_name' => 'Иван', 'last_name' => 'Петров']))->getRealName());
        $this->assertSame('Иван', (new YandexResourceOwner(['first_name' => 'Иван']))->getRealName());
        $this->assertNull((new YandexResourceOwner([]))->getRealName());
    }

    public function test_the_default_email_beats_the_plain_one(): void
    {
        $this->assertSame('main@yandex.ru', (new YandexResourceOwner(['default_email' => 'main@yandex.ru', 'email' => 'other@example.com']))->getEmail());
        $this->assertSame('other@example.com', (new YandexResourceOwner(['email' => 'other@example.com']))->getEmail());
        $this->assertNull((new YandexResourceOwner([]))->getEmail());
    }

    public function test_the_avatar_url_is_built_from_the_avatar_id_and_size(): void
    {
        $owner = new YandexResourceOwner(['default_avatar_id' => '12345/abc']);

        $this->assertSame('https://avatars.yandex.net/get-yapic/12345/abc/islands-200', $owner->getAvatarUrl());
        $this->assertSame('https://avatars.yandex.net/get-yapic/12345/abc/islands-50', $owner->getAvatarUrl(50));
    }

    public function test_no_avatar_is_offered_for_an_empty_or_missing_one(): void
    {
        $this->assertNull((new YandexResourceOwner([]))->getAvatarUrl());
        $this->assertNull((new YandexResourceOwner(['default_avatar_id' => '1/a', 'is_avatar_empty' => true]))->getAvatarUrl());
    }
}
