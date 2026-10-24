<?php

declare(strict_types=1);

namespace forumaker\Yandex\Tests\unit;

use Flarum\Forum\Auth\Registration;
use Flarum\Settings\SettingsRepositoryInterface;
use forumaker\Yandex\OAuth2\YandexProvider;
use forumaker\Yandex\OAuth2\YandexResourceOwner;
use forumaker\Yandex\Providers\Yandex;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

/** The Yandex ID OAuth client and its bridge into fof/oauth's sign-in flow. */
class YandexProviderTest extends TestCase
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function provider(array $queue = []): YandexProvider
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new YandexProvider(
            ['clientId' => 'client-1', 'clientSecret' => 'secret', 'redirectUri' => 'https://forum.test/auth/yandex'],
            ['httpClient' => new Client(['handler' => $stack])]
        );
    }

    private function settings(array $values = []): SettingsRepositoryInterface
    {
        return new class($values) implements SettingsRepositoryInterface {
            public function __construct(private array $values)
            {
            }

            public function all(): array
            {
                return $this->values;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
            }

            public function delete(string $keyLike): void
            {
            }
        };
    }

    public function test_it_talks_to_yandex_and_asks_for_no_scope_by_default(): void
    {
        $provider = $this->provider();
        $url = $provider->getAuthorizationUrl();

        $this->assertStringStartsWith('https://oauth.yandex.ru/authorize?', $url);
        $this->assertStringContainsString('client_id=client-1', $url);
        $this->assertStringNotContainsString('scope=', $url);
        $this->assertSame('https://oauth.yandex.ru/token', $provider->getBaseAccessTokenUrl([]));
    }

    public function test_the_user_is_fetched_with_the_oauth_header(): void
    {
        $provider = $this->provider([new Response(200, [], json_encode(['id' => '77', 'login' => 'ivan', 'default_email' => 'ivan@yandex.ru']))]);

        $owner = $provider->getResourceOwner(new AccessToken(['access_token' => 'tok']));

        $this->assertInstanceOf(YandexResourceOwner::class, $owner);
        $this->assertSame('77', $owner->getId());
        $this->assertSame('ivan@yandex.ru', $owner->getEmail());

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://login.yandex.ru/info', (string) $request->getUri()->withQuery(''));
        $this->assertSame('OAuth tok', $request->getHeaderLine('Authorization'));
    }

    public function test_an_error_answer_is_an_error_with_yandexs_message(): void
    {
        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('Invalid token');

        $this->provider([new Response(401, [], json_encode(['error' => 'unauthorized', 'error_description' => 'Invalid token']))])
            ->getResourceOwner(new AccessToken(['access_token' => 'tok']));
    }

    public function test_the_flarum_provider_describes_itself_and_builds_the_client_from_settings(): void
    {
        $yandex = new Yandex($this->settings(['fof-oauth.yandex.client_id' => 'abc', 'fof-oauth.yandex.client_secret' => 'def']));

        $this->assertSame('yandex', $yandex->name());
        $this->assertFalse($yandex->pkceEnabled());
        $this->assertArrayHasKey('client_id', $yandex->fields());
        $this->assertArrayHasKey('client_secret', $yandex->fields());

        $client = $yandex->provider('https://forum.test/auth/yandex');
        $this->assertInstanceOf(YandexProvider::class, $client);
        $this->assertStringContainsString('client_id=abc', $client->getAuthorizationUrl());
    }

    public function test_sign_up_suggestions_come_from_the_yandex_profile(): void
    {
        $yandex = new Yandex($this->settings());
        $registration = new Registration();

        $yandex->suggestions($registration, new YandexResourceOwner([
            'login' => 'ivan.petrov', 'real_name' => 'Иван Петров', 'default_email' => 'ivan@yandex.ru', 'default_avatar_id' => '1/a',
        ]), 'tok');

        $this->assertSame('ivan@yandex.ru', $registration->getProvided()['email']);
        $this->assertSame('ivan-petrov', $registration->getSuggested()['username']);
        $this->assertSame('Иван Петров', $registration->getSuggested()['nickname']);
        $this->assertSame('https://avatars.yandex.net/get-yapic/1/a/islands-200', $registration->getProvided()['avatar_url']);
    }

    public function test_the_username_falls_back_to_the_real_name_is_transliterated_and_capped(): void
    {
        $yandex = new Yandex($this->settings());

        $registration = new Registration();
        $yandex->suggestions($registration, new YandexResourceOwner(['real_name' => 'Иван Петров']), 'tok');
        $this->assertSame('Ivan-Petrov', $registration->getSuggested()['username'], 'a Cyrillic name is transliterated, not dropped');

        $registration = new Registration();
        $yandex->suggestions($registration, new YandexResourceOwner(['login' => str_repeat('a', 80)]), 'tok');
        $this->assertSame(30, mb_strlen($registration->getSuggested()['username']));

        $registration = new Registration();
        $yandex->suggestions($registration, new YandexResourceOwner(['login' => '!!!']), 'tok');
        $this->assertSame('yandex-user', $registration->getSuggested()['username']);
    }

    public function test_something_that_is_not_a_yandex_profile_suggests_nothing(): void
    {
        $registration = new Registration();

        (new Yandex($this->settings()))->suggestions($registration, new \stdClass(), 'tok');

        $this->assertSame([], $registration->getSuggested());
        $this->assertSame([], $registration->getProvided());
    }
}
