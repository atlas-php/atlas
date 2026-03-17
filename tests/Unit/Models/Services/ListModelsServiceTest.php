<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Services\ListModelsService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;

beforeEach(function (): void {
    $this->cache = new CacheRepository(new ArrayStore);
    $this->service = new ListModelsService(app(HttpFactory::class), $this->cache);

    config()->set('prism.providers.openai', [
        'api_key' => 'test-openai-key',
        'url' => 'https://api.openai.com/v1',
    ]);
    config()->set('prism.providers.anthropic', [
        'api_key' => 'test-anthropic-key',
        'url' => 'https://api.anthropic.com/v1',
    ]);
    config()->set('prism.providers.gemini', [
        'api_key' => 'test-gemini-key',
        'url' => 'https://generativelanguage.googleapis.com/v1beta/models',
    ]);
    config()->set('prism.providers.ollama', [
        'api_key' => '',
        'url' => 'http://localhost:11434',
    ]);
    config()->set('prism.providers.elevenlabs', [
        'api_key' => 'test-elevenlabs-key',
        'url' => 'https://api.elevenlabs.io/v1',
    ]);
    config()->set('atlas.models.cache.enabled', true);
    config()->set('atlas.models.cache.ttl', 3600);
});

test('has returns true for supported providers', function (): void {
    expect($this->service->has('openai'))->toBeTrue()
        ->and($this->service->has('anthropic'))->toBeTrue()
        ->and($this->service->has('gemini'))->toBeTrue();
});

test('has returns false for unsupported providers', function (): void {
    expect($this->service->has('perplexity'))->toBeFalse()
        ->and($this->service->has('voyageai'))->toBeFalse()
        ->and($this->service->has('z'))->toBeFalse();
});

test('has returns false for unknown providers', function (): void {
    expect($this->service->has('unknown_provider'))->toBeFalse();
});

test('has accepts Prism Provider enum', function (): void {
    expect($this->service->has(Provider::OpenAI))->toBeTrue()
        ->and($this->service->has(Provider::Perplexity))->toBeFalse();
});

test('get returns null for unsupported provider', function (): void {
    expect($this->service->get('perplexity'))->toBeNull();
});

test('get returns null for unknown provider', function (): void {
    expect($this->service->get('nonexistent'))->toBeNull();
});

test('get fetches and caches OpenAI models', function (): void {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'gpt-3.5-turbo'],
            ],
        ]),
    ]);

    $models = $this->service->get('openai');

    expect($models)->toBe([
        ['id' => 'gpt-3.5-turbo', 'name' => null],
        ['id' => 'gpt-4o', 'name' => null],
    ]);

    // Verify cached
    expect($this->cache->has('atlas:models:openai'))->toBeTrue();
});

test('get returns cached results on second call', function (): void {
    $cachedModels = [
        ['id' => 'cached-model', 'name' => null],
    ];
    $this->cache->put('atlas:models:openai', $cachedModels, 3600);

    $models = $this->service->get('openai');

    expect($models)->toBe($cachedModels);

    // No HTTP calls should have been made
    Http::assertNothingSent();
});

test('get fetches Anthropic models with display names', function (): void {
    Http::fake([
        'api.anthropic.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4'],
                ['id' => 'claude-3-haiku-20240307', 'display_name' => 'Claude 3 Haiku'],
            ],
        ]),
    ]);

    $models = $this->service->get('anthropic');

    expect($models)->toBe([
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku'],
        ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4'],
    ]);
});

test('get fetches Gemini models', function (): void {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'models' => [
                ['name' => 'models/gemini-pro', 'displayName' => 'Gemini Pro'],
                ['name' => 'models/gemini-1.5-flash', 'displayName' => 'Gemini 1.5 Flash'],
            ],
        ]),
    ]);

    $models = $this->service->get('gemini');

    expect($models)->toBe([
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash'],
        ['id' => 'gemini-pro', 'name' => 'Gemini Pro'],
    ]);
});

test('get returns null when API key is missing for non-keyless provider', function (): void {
    config()->set('prism.providers.openai.api_key', '');

    $models = $this->service->get('openai');

    expect($models)->toBeNull();
});

test('get returns null when base URL is missing', function (): void {
    config()->set('prism.providers.openai.url', '');

    $models = $this->service->get('openai');

    expect($models)->toBeNull();
});

test('get returns null on HTTP failure', function (): void {
    Http::fake([
        'api.openai.com/v1/models' => Http::response('Server Error', 500),
    ]);

    $models = $this->service->get('openai');

    expect($models)->toBeNull();
});

test('get skips cache when caching is disabled', function (): void {
    config()->set('atlas.models.cache.enabled', false);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o']],
        ]),
    ]);

    $this->service->get('openai');

    expect($this->cache->has('atlas:models:openai'))->toBeFalse();
});

test('refresh always fetches fresh data', function (): void {
    $this->cache->put('atlas:models:openai', [['id' => 'old-model', 'name' => null]], 3600);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'new-model']],
        ]),
    ]);

    $models = $this->service->refresh('openai');

    expect($models)->toBe([['id' => 'new-model', 'name' => null]]);

    // Cache should be updated
    expect($this->cache->get('atlas:models:openai'))->toBe([['id' => 'new-model', 'name' => null]]);
});

test('refresh returns null for unsupported provider', function (): void {
    expect($this->service->refresh('perplexity'))->toBeNull();
});

test('clear removes cached data', function (): void {
    $this->cache->put('atlas:models:openai', [['id' => 'cached', 'name' => null]], 3600);

    $this->service->clear('openai');

    expect($this->cache->has('atlas:models:openai'))->toBeFalse();
});

test('clear handles unknown provider gracefully', function (): void {
    // Should not throw
    $this->service->clear('nonexistent');

    expect(true)->toBeTrue();
});

test('all returns models keyed by provider', function (): void {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o']],
        ]),
        'api.anthropic.com/v1/models' => Http::response([
            'data' => [['id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4']],
        ]),
        '*' => Http::response('', 500),
    ]);

    $all = $this->service->all();

    expect($all)->toHaveKey('openai')
        ->and($all)->toHaveKey('anthropic')
        ->and($all['openai'])->toBe([['id' => 'gpt-4o', 'name' => null]])
        ->and($all['anthropic'])->toBe([['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4']]);
});

test('Ollama fallback from v1 models to api tags', function (): void {
    Http::fake([
        'localhost:11434/v1/models' => Http::response('', 500),
        'localhost:11434/api/tags' => Http::response([
            'models' => [
                ['name' => 'llama2:latest'],
                ['name' => 'codellama:7b'],
            ],
        ]),
    ]);

    $models = $this->service->get('ollama');

    expect($models)->toBe([
        ['id' => 'codellama:7b', 'name' => null],
        ['id' => 'llama2:latest', 'name' => null],
    ]);
});

test('Ollama uses v1 models when available', function (): void {
    Http::fake([
        'localhost:11434/v1/models' => Http::response([
            'data' => [
                ['id' => 'llama2:latest'],
            ],
        ]),
    ]);

    $models = $this->service->get('ollama');

    expect($models)->toBe([
        ['id' => 'llama2:latest', 'name' => null],
    ]);
});

test('Ollama works without API key', function (): void {
    config()->set('prism.providers.ollama.api_key', '');

    Http::fake([
        'localhost:11434/v1/models' => Http::response([
            'data' => [['id' => 'llama2:latest']],
        ]),
    ]);

    $models = $this->service->get('ollama');

    expect($models)->not->toBeNull();
});

test('get returns null when provider config is missing', function (): void {
    config()->set('prism.providers.openai', null);

    $models = $this->service->get('openai');

    expect($models)->toBeNull();
});

test('get fetches ElevenLabs models with xi-api-key auth', function (): void {
    Http::fake([
        'api.elevenlabs.io/v1/models' => Http::response([
            ['model_id' => 'eleven_v3', 'name' => 'Eleven v3'],
            ['model_id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5'],
        ]),
    ]);

    $models = $this->service->get('elevenlabs');

    expect($models)->toBe([
        ['id' => 'eleven_flash_v2_5', 'name' => 'Eleven Flash v2.5'],
        ['id' => 'eleven_v3', 'name' => 'Eleven v3'],
    ]);
});
