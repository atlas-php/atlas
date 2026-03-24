<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\VoiceRequest;
use Illuminate\Support\Facades\Http;

function makeXaiDriver(): XaiDriver
{
    return new XaiDriver(
        config: ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.x.ai/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

// ─── Identity ───────────────────────────────────────────────────────────────

it('returns xai as name', function () {
    expect(makeXaiDriver()->name())->toBe('xai');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('reports correct capabilities', function () {
    $cap = makeXaiDriver()->capabilities();

    expect($cap->supports('text'))->toBeTrue();
    expect($cap->supports('stream'))->toBeTrue();
    expect($cap->supports('structured'))->toBeTrue();
    expect($cap->supports('image'))->toBeTrue();
    expect($cap->supports('imageToText'))->toBeFalse();
    expect($cap->supports('audio'))->toBeTrue();
    expect($cap->supports('audioToText'))->toBeFalse();
    expect($cap->supports('video'))->toBeTrue();
    expect($cap->supports('videoToText'))->toBeFalse();
    expect($cap->supports('embed'))->toBeFalse();
    expect($cap->supports('moderate'))->toBeFalse();
    expect($cap->supports('vision'))->toBeTrue();
    expect($cap->supports('toolCalling'))->toBeTrue();
    expect($cap->supports('providerTools'))->toBeTrue();
    expect($cap->supports('models'))->toBeTrue();
    expect($cap->supports('voices'))->toBeTrue();
});

// ─── Unsupported modalities throw ───────────────────────────────────────────

it('throws UnsupportedFeatureException for audioToText', function () {
    makeXaiDriver()->audioToText(new AudioRequest('model', null, [], null, null, null, null, null, null));
})->throws(UnsupportedFeatureException::class);

it('throws UnsupportedFeatureException for embed', function () {
    makeXaiDriver()->embed(new EmbedRequest('model', 'text'));
})->throws(UnsupportedFeatureException::class, 'embed');

it('throws UnsupportedFeatureException for moderate', function () {
    makeXaiDriver()->moderate(new ModerateRequest('test'));
})->throws(UnsupportedFeatureException::class, 'moderate');

it('throws UnsupportedFeatureException for rerank', function () {
    makeXaiDriver()->rerank(new RerankRequest('model', 'query', ['doc']));
})->throws(UnsupportedFeatureException::class, 'rerank');

// ─── Provider handler ───────────────────────────────────────────────────────

it('lists models via provider handler', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response([
            'data' => [
                ['id' => 'grok-3', 'object' => 'model'],
                ['id' => 'grok-3-mini', 'object' => 'model'],
            ],
        ]),
    ]);

    $models = makeXaiDriver()->models();

    expect($models->models)->toContain('grok-3');
    expect($models->models)->toContain('grok-3-mini');
});

it('lists voices via provider handler', function () {
    $voices = makeXaiDriver()->voices();

    expect($voices->voices)->toHaveCount(5);
    expect($voices->voices)->toContain('ara');
    expect($voices->voices)->toContain('eve');
    expect($voices->voices)->toContain('leo');
    expect($voices->voices)->toContain('rex');
    expect($voices->voices)->toContain('sal');
});

it('supports voice capability', function () {
    expect(makeXaiDriver()->capabilities()->supports('voice'))->toBeTrue();
});

it('creates voice session via voice handler', function () {
    Http::fake([
        'api.x.ai/v1/realtime/client_secrets' => Http::response([
            'value' => 'eph_token_test',
            'expires_at' => time() + 60,
        ]),
    ]);

    $session = makeXaiDriver()->createVoiceSession(new VoiceRequest(
        model: 'grok-3-fast-realtime',
        instructions: 'Be helpful',
        voice: 'eve',
    ));

    expect($session->provider)->toBe('xai');
    expect($session->ephemeralToken)->toBe('eph_token_test');
    expect($session->connectionUrl)->toBe('wss://api.x.ai/v1/realtime');
});

// ─── Validate ──────────────────────────────────────────────────────────────

it('validates via provider handler', function () {
    Http::fake([
        'api.x.ai/v1/models' => Http::response([
            'data' => [['id' => 'grok-3', 'object' => 'model']],
        ]),
    ]);

    expect(makeXaiDriver()->validate())->toBeTrue();
});
