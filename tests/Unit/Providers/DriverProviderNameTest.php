<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\ConnectionException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\ServerException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Regression coverage for provider mislabeling: drivers built on a shared
 * wire-format type (chat_completions, responses) must attribute errors,
 * connection failures, and unsupported-feature messages to the consumer's
 * configured provider key — not the driver type returned by name().
 */
function makeSharedDriver(string $providerKey): Driver
{
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com', provider: $providerKey);
    $http = Mockery::mock(HttpClient::class);

    // name() returns the wire-format type, mimicking ChatCompletionsDriver/ResponsesDriver.
    return new class($config, $http) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true);
        }

        public function name(): string
        {
            return 'chat_completions';
        }
    };
}

function makeReqExceptionStatus(int $status): RequestException
{
    Http::fake(['*' => Http::response('error', $status)]);

    try {
        Http::get('https://api.test.com/fail')->throw();
    } catch (RequestException $e) {
        return $e;
    }

    throw new RuntimeException('Expected RequestException');
}

function textRequestForProviderName(): TextRequest
{
    return new TextRequest('model', null, null, [], [], null, null, null, [], [], []);
}

it('attributes a typed HTTP error to the configured provider key, not the driver type', function (string $exceptionClass, int $status) {
    $caught = null;

    try {
        makeSharedDriver('groq')->handleRequestException('llama-3', makeReqExceptionStatus($status));
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf($exceptionClass);
    expect($caught->provider)->toBe('groq');
    expect($caught->getMessage())->not->toContain('chat_completions');
})->with([
    'auth 401' => [AuthenticationException::class, 401],
    'rate limit 429' => [RateLimitException::class, 429],
    'server 503' => [ServerException::class, 503],
    'base 500' => [ProviderException::class, 500],
]);

it('attributes a connection failure to the configured provider key', function () {
    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->andThrow(
        new Illuminate\Http\Client\ConnectionException('cURL error 28: timed out')
    );

    $caught = null;

    try {
        makeSharedDriver('ollama')->withHandler('text', $handler)->text(textRequestForProviderName());
    } catch (ConnectionException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(ConnectionException::class);
    expect($caught->provider)->toBe('ollama');
});

it('names the configured provider in an unsupported-feature error', function () {
    $caught = null;

    try {
        makeSharedDriver('groq')->embed(new EmbedRequest('model', 'text'));
    } catch (UnsupportedFeatureException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(UnsupportedFeatureException::class);
    expect($caught->getMessage())->toContain('groq');
    expect($caught->getMessage())->not->toContain('chat_completions');
});

it('falls back to the driver wire-format name when no provider key is stamped', function () {
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com'); // provider = ''
    $http = Mockery::mock(HttpClient::class);

    $driver = new class($config, $http) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities;
        }

        public function name(): string
        {
            return 'chat_completions';
        }
    };

    $caught = null;

    try {
        $driver->handleRequestException('m', makeReqExceptionStatus(401));
    } catch (AuthenticationException $e) {
        $caught = $e;
    }

    expect($caught->provider)->toBe('chat_completions');
});
