<?php

declare(strict_types=1);

use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\AuthenticationException;
use Atlasphp\Atlas\Exceptions\AuthorizationException;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Exceptions\ProviderNotFoundException;
use Atlasphp\Atlas\Exceptions\RateLimitException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

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

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    $e = RateLimitException::from('openai', 'gpt-4o', $requestException);

    expect($e)->toBeInstanceOf(RateLimitException::class);
    expect($e->provider)->toBe('openai');
    expect($e->model)->toBe('gpt-4o');
    expect($e->retryAfter)->toBe(60);
    expect($e->getPrevious())->toBe($requestException);
});

it('RateLimitException::from returns null retryAfter when header is missing', function () {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('header')->with('Retry-After')->andReturn(null);

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
    $response->shouldReceive('json')->with('error.message', Mockery::any())->andReturn('Model overloaded');

    $requestException = Mockery::mock(RequestException::class);
    $requestException->response = $response;

    $e = ProviderException::from('openai', 'gpt-4o', $requestException);

    expect($e)->toBeInstanceOf(ProviderException::class);
    expect($e->statusCode)->toBe(500);
    expect($e->providerMessage)->toBe('Model overloaded');
    expect($e->getPrevious())->toBe($requestException);
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
    expect($e->statusCode)->toBe(0);
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
