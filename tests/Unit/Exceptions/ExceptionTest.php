<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ConnectionException;
use Atlasphp\Atlas\Exceptions\InvalidRequestException;
use Atlasphp\Atlas\Exceptions\ModelNotFoundException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\ServerException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

it('AtlasException extends RuntimeException', function () {
    $e = new AtlasException('test');

    expect($e)->toBeInstanceOf(RuntimeException::class);
});

it('AuthenticationException stores provider', function () {
    $e = new AuthenticationException('openai');

    expect($e->provider)->toBe('openai');
    expect($e->getMessage())->toContain('openai');
    expect($e)->toBeInstanceOf(AtlasException::class);
});

it('AuthorizationException stores provider and model', function () {
    $e = new AuthorizationException('openai', 'gpt-4o');

    expect($e->provider)->toBe('openai');
    expect($e->model)->toBe('gpt-4o');
    expect($e->getMessage())->toContain('openai');
    expect($e->getMessage())->toContain('gpt-4o');
});

it('RateLimitException stores provider, model, and retryAfter', function () {
    $e = new RateLimitException('openai', 'gpt-4o', 30);

    expect($e->provider)->toBe('openai');
    expect($e->model)->toBe('gpt-4o');
    expect($e->retryAfter)->toBe(30);
});

it('RateLimitException::from extracts Retry-After header', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('header')->with('Retry-After')->andReturn('60');
    $response->shouldReceive('status')->andReturn(429);

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    $e = RateLimitException::from('openai', 'gpt-4o', $requestException);

    expect($e)->toBeInstanceOf(RateLimitException::class);
    expect($e->provider)->toBe('openai');
    expect($e->model)->toBe('gpt-4o');
    expect($e->retryAfter)->toBe(60);
    expect($e->statusCode)->toBe(429);
    expect($e->getPrevious())->toBe($requestException);
});

it('RateLimitException::from preserves a 529 overloaded status', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('header')->with('Retry-After')->andReturn(null);
    $response->shouldReceive('status')->andReturn(529);

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    expect(RateLimitException::from('anthropic', 'claude', $requestException)->statusCode)->toBe(529);
});

it('RateLimitException::from returns null retryAfter when header is missing', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('header')->with('Retry-After')->andReturn(null);
    $response->shouldReceive('status')->andReturn(429);

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    $e = RateLimitException::from('openai', 'gpt-4o', $requestException);

    expect($e->retryAfter)->toBeNull();
});

it('ProviderException stores all properties', function () {
    $e = new ProviderException('openai', 'gpt-4o', 500, 'Internal server error');

    expect($e->provider)->toBe('openai');
    expect($e->model)->toBe('gpt-4o');
    expect($e->statusCode)->toBe(500);
    expect($e->providerMessage)->toBe('Internal server error');
});

it('ProviderException::from extracts status and error message', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(500);
    $response->shouldReceive('json')->andReturn(['error' => ['message' => 'Model overloaded']]);

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    $e = ProviderException::from('openai', 'gpt-4o', $requestException);

    expect($e)->toBeInstanceOf(ProviderException::class);
    expect($e->statusCode)->toBe(500);
    expect($e->providerMessage)->toBe('Model overloaded');
    expect($e->getPrevious())->toBe($requestException);
});

it('ProviderException::from extracts the real message across provider error shapes', function (array $body, string $expected) {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn(400);
    $response->shouldReceive('json')->andReturn($body);

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    expect(ProviderException::from('p', 'm', $requestException)->providerMessage)->toBe($expected);
})->with([
    'openai/anthropic/google error.message' => [['error' => ['message' => 'bad request']], 'bad request'],
    'elevenlabs detail.message' => [['detail' => ['message' => 'quota exceeded']], 'quota exceeded'],
    'elevenlabs/jina string detail' => [['detail' => 'invalid voice'], 'invalid voice'],
    'cohere top-level message' => [['message' => 'invalid api key'], 'invalid api key'],
    'ollama string error' => [['error' => 'model not found'], 'model not found'],
]);

it('ProviderException::from falls back to the request-exception message when the body has no recognizable error', function () {
    Http::fake(['*' => Http::response(['weird' => 'shape'], 500)]);

    try {
        Http::get('https://x.test/fail')->throw();
    } catch (RequestException $e) {
        $ex = ProviderException::from('p', 'm', $e);

        expect($ex->statusCode)->toBe(500);
        expect($ex->providerMessage)->toBe($e->getMessage());
    }
});

it('ProviderException::fromStreamError extracts the message from common shapes', function () {
    expect(ProviderException::fromStreamError('anthropic', '', ['message' => 'Overloaded'])->providerMessage)
        ->toBe('Overloaded');

    expect(ProviderException::fromStreamError('openai', '', ['error' => ['message' => 'bad request']])->providerMessage)
        ->toBe('bad request');

    expect(ProviderException::fromStreamError('ollama', '', ['error' => 'boom'])->providerMessage)
        ->toBe('boom');
});

it('ProviderException::fromStreamError pulls a numeric code into statusCode', function () {
    $e = ProviderException::fromStreamError('google', 'gemini', ['code' => 429, 'message' => 'Resource exhausted']);

    expect($e->statusCode)->toBe(429);
    expect($e->model)->toBe('gemini');
});

it('ProviderException::fromStreamError falls back to a default message when none is present', function () {
    $e = ProviderException::fromStreamError('openai', '', ['unexpected' => true]);

    expect($e->providerMessage)->toBe('Provider returned an error during streaming.');
    expect($e->statusCode)->toBeNull();
    expect($e->getMessage())->not->toContain('[0]');
});

it('UnsupportedFeatureException::make includes feature and provider', function () {
    $e = UnsupportedFeatureException::make('streaming', 'google');

    expect($e->getMessage())->toContain('streaming');
    expect($e->getMessage())->toContain('google');
    expect($e)->toBeInstanceOf(AtlasException::class);
});

it('ProviderNotFoundException includes key in message', function () {
    $e = new ProviderNotFoundException('unknown');

    expect($e->getMessage())->toContain('unknown');
    expect($e)->toBeInstanceOf(AtlasException::class);
});

// ─── Hierarchy: the provider-error family extends ProviderException ──────────

it('the provider-error family extends ProviderException with the right status', function (ProviderException $e, ?int $status) {
    expect($e)->toBeInstanceOf(ProviderException::class);
    expect($e)->toBeInstanceOf(AtlasException::class);
    expect($e->statusCode)->toBe($status);
})->with([
    'authentication' => [new AuthenticationException('openai'), 401],
    'authorization' => [new AuthorizationException('openai', 'gpt-4o'), 403],
    'rate limit' => [new RateLimitException('openai', 'gpt-4o'), 429],
    'invalid request' => [new InvalidRequestException('openai', 'gpt-4o', 400, 'bad'), 400],
    'model not found' => [new ModelNotFoundException('openai', 'gpt-4o', 404, 'nope'), 404],
    'server' => [new ServerException('openai', 'gpt-4o', 503, 'down'), 503],
    'connection' => [new ConnectionException('openai', 'gpt-4o'), null],
]);

it('AuthenticationException threads the model when provided', function () {
    $e = new AuthenticationException('openai', 'gpt-4o');

    expect($e->model)->toBe('gpt-4o');
    expect($e->statusCode)->toBe(401);
});

it('AuthenticationException preserves its own message and is catchable as ProviderException', function () {
    $caught = null;

    try {
        throw new AuthenticationException('openai');
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(AuthenticationException::class);
    expect($caught->getMessage())->toBe('Authentication failed for provider [openai].');
    expect($caught->provider)->toBe('openai');
});

it('ConnectionException is a ProviderException with a null status and no status bracket', function () {
    $e = new ConnectionException('openai', 'gpt-4o', new RuntimeException('cURL error 28: timed out'));

    expect($e)->toBeInstanceOf(ProviderException::class);
    expect($e->statusCode)->toBeNull();
    expect($e->getMessage())->toBe('Connection to provider [openai] failed: cURL error 28: timed out');
});

it('ProviderException omits the status bracket when statusCode is null', function () {
    $e = new ProviderException('openai', 'gpt-4o', null, 'something went wrong');

    expect($e->getMessage())->toBe('Provider [openai] error: something went wrong');
});
