<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;

function makeMiddlewarePendingRegistry(): ProviderRegistryContract
{
    return Mockery::mock(ProviderRegistryContract::class);
}

it('TextRequest passes middleware to built request', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('TextRequest merges multiple withMiddleware calls', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());
    $mw1 = fn () => 'mw1';
    $mw2 = fn () => 'mw2';

    $pending->withMiddleware([$mw1])->withMiddleware([$mw2]);
    $request = $pending->buildRequest();

    expect($request->middleware)->toHaveCount(2);
});

it('ImageRequest passes middleware to built request', function () {
    $pending = new ImageRequest('openai', 'dall-e-3', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('AudioRequest passes middleware to built request', function () {
    $pending = new AudioRequest('openai', 'tts-1', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('VideoRequest passes middleware to built request', function () {
    $pending = new VideoRequest('openai', 'video-1', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('EmbedRequest passes middleware to built request', function () {
    $pending = new EmbedRequest('openai', 'embed-1', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('ModerateRequest passes middleware to built request', function () {
    $pending = new ModerateRequest('openai', 'mod-1', makeMiddlewarePendingRegistry());
    $middleware = [fn () => 'mw1'];

    $pending->withMiddleware($middleware);
    $request = $pending->buildRequest();

    expect($request->middleware)->toBe($middleware);
});

it('withMiddleware returns $this for fluent chaining', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());

    $result = $pending->withMiddleware([fn () => 'mw']);

    expect($result)->toBe($pending);
});

// ── withMeta() tests ─────────────────────────────────────────────────────────

it('TextRequest passes meta to built request', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('TextRequest merges multiple withMeta calls', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123])->withMeta(['session' => 'abc']);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123, 'session' => 'abc']);
});

it('ImageRequest passes meta to built request', function () {
    $pending = new ImageRequest('openai', 'dall-e-3', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('AudioRequest passes meta to built request', function () {
    $pending = new AudioRequest('openai', 'tts-1', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('VideoRequest passes meta to built request', function () {
    $pending = new VideoRequest('openai', 'video-1', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('EmbedRequest passes meta to built request', function () {
    $pending = new EmbedRequest('openai', 'embed-1', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('ModerateRequest passes meta to built request', function () {
    $pending = new ModerateRequest('openai', 'mod-1', makeMiddlewarePendingRegistry());

    $pending->withMeta(['user_id' => 123]);
    $request = $pending->buildRequest();

    expect($request->meta)->toBe(['user_id' => 123]);
});

it('withMeta returns $this for fluent chaining', function () {
    $pending = new TextRequest('openai', 'gpt-4o', makeMiddlewarePendingRegistry());

    $result = $pending->withMeta(['key' => 'value']);

    expect($result)->toBe($pending);
});
