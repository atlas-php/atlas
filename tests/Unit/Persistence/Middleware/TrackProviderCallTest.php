<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\StorableContract;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Support\Facades\Storage;

it('skips execution creation when agent execution is active', function () {
    $service = new ExecutionService;

    // Create an execution to simulate an active agent execution
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-4o',
        type: ExecutionType::Text,
    );
    $service->beginExecution();

    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }
    };

    $executionCountBefore = Execution::count();

    $middleware->handle($context, fn () => $response);

    // Should NOT have created a second execution (the agent one already exists)
    expect(Execution::count())->toBe($executionCountBefore);
});

it('creates standalone execution for direct calls', function () {
    $service = new ExecutionService;

    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new stdClass,
        meta: [],
    );

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 50, 'output_tokens' => 25];
        }
    };

    $middleware->handle($context, fn () => $response);

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->provider)->toBe('openai');
    expect($execution->model)->toBe('gpt-4o');
    expect($execution->type)->toBe(ExecutionType::Text);
    expect($execution->status)->toBe(ExecutionStatus::Completed);
});

it('stores asset for image response', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->type)->toBe(AssetType::Image);
    expect($asset->mime_type)->toBe('image/png');
    expect($asset->execution_id)->not->toBeNull();
    expect($asset->size_bytes)->toBe(strlen('fake-image-bytes'));

    Storage::disk('local')->assertExists($asset->path);
});

it('stores asset for audio response', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'tts-1',
        method: 'audio',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 5, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-audio-bytes';
        }

        public function mimeType(): string
        {
            return 'audio/mpeg';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->type)->toBe(AssetType::Audio);
    expect($asset->mime_type)->toBe('audio/mpeg');
});

it('skips asset for text response', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new stdClass,
        meta: [],
    );

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 20, 'output_tokens' => 15];
        }
    };

    $middleware->handle($context, fn () => $response);

    expect(Asset::count())->toBe(0);
});

it('respects auto_store_assets config', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.persistence.auto_store_assets', false);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    expect(Asset::count())->toBe(0);
});

it('still stores assets when inside agent execution', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;

    // Simulate active agent execution
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-4o',
        type: ExecutionType::Text,
    );
    $service->beginExecution();

    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    expect(Asset::count())->toBe(1);
});

it('stores asset for video response', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'sora-1',
        method: 'video',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 5, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-video-bytes';
        }

        public function mimeType(): string
        {
            return 'video/mp4';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->type)->toBe(AssetType::Video);
    expect($asset->mime_type)->toBe('video/mp4');
});

it('fails standalone execution on exception', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new stdClass,
        meta: [],
    );

    try {
        $middleware->handle($context, function () {
            throw new RuntimeException('Provider crashed');
        });
    } catch (RuntimeException) {
        // expected
    }

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Failed);
    expect($execution->error)->toContain('Provider crashed');
});

it('re-throws exception after failing execution', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new stdClass,
        meta: [],
    );

    $middleware->handle($context, function () {
        throw new RuntimeException('Provider crashed');
    });
})->throws(RuntimeException::class, 'Provider crashed');

it('completes standalone execution with usage', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new stdClass,
        meta: [],
    );

    $response = new class
    {
        public Usage $usage;

        public function __construct()
        {
            $this->usage = new Usage(inputTokens: 100, outputTokens: 42);
        }
    };

    $middleware->handle($context, fn () => $response);

    $execution = Execution::latest('id')->first();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->usage)->toBe(['input_tokens' => 100, 'output_tokens' => 42]);
});

it('resolves default extension for unknown mime type', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-bytes';
        }

        public function mimeType(): string
        {
            return 'application/octet-stream';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    // Unknown mime type falls back to AssetType default — 'png' for Image
    expect($asset->path)->toEndWith('.png');
});

it('resolves png extension for image/png mime type', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull()
        ->and($asset->path)->toEndWith('.png')
        ->and($asset->mime_type)->toBe('image/png');
});

it('links asset to current tool call when inside tool execution', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;

    // Simulate agent execution with an active tool call
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-4o',
        type: ExecutionType::Text,
    );
    $service->beginExecution();

    $step = $service->createStep();
    $service->beginStep();

    $toolCall = new ToolCall(
        id: 'call_abc123',
        name: 'generate_image',
        arguments: ['prompt' => 'a cat'],
    );
    $toolCallRecord = $service->createToolCall($toolCall);

    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->tool_call_id)->toBe($toolCallRecord->id);
    expect($asset->metadata)->toBeNull();

    // Verify the relationship works
    expect($asset->toolCall)->not->toBeNull();
    expect($asset->toolCall->tool_call_id)->toBe('call_abc123');
    expect($asset->toolCall->name)->toBe('generate_image');
});

// ─── resolveExtension AssetType fallback branches ──────────────────────────

it('resolves mp3 extension for audio response with unknown mime type', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'tts-1',
        method: 'audio',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 5, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-audio-bytes';
        }

        public function mimeType(): string
        {
            return 'application/octet-stream';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();
    expect($asset)->not->toBeNull();
    expect($asset->path)->toEndWith('.mp3');
});

it('resolves mp4 extension for video response with unknown mime type', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'sora-2',
        method: 'video',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-video-bytes';
        }

        public function mimeType(): string
        {
            return 'application/octet-stream';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();
    expect($asset)->not->toBeNull();
    expect($asset->path)->toEndWith('.mp4');
});

it('resolves known mime type extensions in middleware', function (string $method, string $mimeType, string $extension) {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'test-model',
        method: $method,
        request: new stdClass,
        meta: [],
    );

    $response = new class($mimeType) implements StorableContract
    {
        public object $usage;

        public function __construct(private string $mime)
        {
            $this->usage = (object) ['input_tokens' => 1, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'bytes';
        }

        public function mimeType(): string
        {
            return $this->mime;
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();
    expect($asset)->not->toBeNull();
    expect($asset->path)->toEndWith(".{$extension}");
})->with([
    'image/jpeg' => ['image', 'image/jpeg', 'jpg'],
    'image/webp' => ['image', 'image/webp', 'webp'],
    'image/gif' => ['image', 'image/gif', 'gif'],
    'audio/wav' => ['audio', 'audio/wav', 'wav'],
    'audio/ogg' => ['audio', 'audio/ogg', 'ogg'],
    'video/webm' => ['video', 'video/webm', 'webm'],
]);

// ─── Response asset attachment ──────────────────────────────────────────────

it('sets asset on response when response has asset property', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    // Use a response with an asset property (like ImageResponse/VideoResponse/AudioResponse)
    $response = new class implements StorableContract
    {
        public ?object $asset = null;

        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $result = $middleware->handle($context, fn () => $response);

    expect($result->asset)->not->toBeNull();
    expect($result->asset)->toBeInstanceOf(Asset::class);
    expect($result->asset->type)->toBe(AssetType::Image);
    expect($result->asset->mime_type)->toBe('image/png');
});

it('does not set asset on response without asset property', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    // Response WITHOUT an asset property — no crash
    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $result = $middleware->handle($context, fn () => $response);

    // Asset still stored in DB, just not attached to response
    expect(Asset::count())->toBe(1);
    expect(property_exists($result, 'asset'))->toBeFalse();
});

it('makes asset available via ExecutionService getLastAsset', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public ?object $asset = null;

        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    // Before: no last asset
    expect($service->getLastAsset())->toBeNull();

    $middleware->handle($context, fn () => $response);

    // After: last asset is available without DB query
    $lastAsset = $service->getLastAsset();

    expect($lastAsset)->not->toBeNull();
    expect($lastAsset)->toBeInstanceOf(Asset::class);
    expect($lastAsset->type)->toBe(AssetType::Image);
});

it('getLastAsset is cleared on execution service reset', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public ?object $asset = null;

        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    expect($service->getLastAsset())->not->toBeNull();

    $service->reset();

    expect($service->getLastAsset())->toBeNull();
});

it('resolves mime type from response format property when mimeType method is absent', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    // Response with ->format property but no mimeType() method
    $response = new class implements StorableContract
    {
        public string $format = 'webp';

        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-webp-bytes';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->mime_type)->toBe('image/webp');
    expect($asset->path)->toEndWith('.webp');
});

// ─── Direct moderate/rerank calls (no usage property) ───────────────────────

it('completes direct moderate call without usage property', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'omni-moderation-latest',
        method: 'moderate',
        request: new stdClass,
        meta: [],
    );

    // ModerationResponse has no $usage property
    $response = new class
    {
        public bool $flagged = false;
    };

    $middleware->handle($context, fn () => $response);

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->usage)->toBeNull();
});

it('completes direct rerank call without usage property', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'cohere',
        model: 'rerank-v3',
        method: 'rerank',
        request: new stdClass,
        meta: [],
    );

    // RerankResponse has no $usage property
    $response = new class
    {
        public array $results = [];
    };

    $middleware->handle($context, fn () => $response);

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->usage)->toBeNull();
});

it('skips execution creation for voice sessions', function () {
    $service = new ExecutionService;
    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o-realtime',
        method: 'voice',
        request: new stdClass,
        meta: [],
    );

    $response = new class
    {
        public string $sessionId = 'sess_123';
    };

    $executionCountBefore = Execution::count();

    $middleware->handle($context, fn () => $response);

    // Voice sessions are tracked by AgentRequest::asVoice() — not here
    expect(Execution::count())->toBe($executionCountBefore);
});

it('derives asset owner from execution conversation', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');
    config()->set('atlas.storage.prefix', 'atlas');
    config()->set('atlas.persistence.auto_store_assets', true);

    $service = new ExecutionService;

    // Create a conversation with an owner
    $conversationModel = config('atlas.persistence.models.conversation', Conversation::class);
    $conversation = $conversationModel::create([
        'agent' => 'test-agent',
        'owner_type' => 'App\\Models\\User',
        'owner_id' => 42,
    ]);

    // Create execution linked to the conversation
    $service->createExecution(
        provider: 'openai',
        model: 'dall-e-3',
        type: ExecutionType::Image,
        conversationId: $conversation->id,
    );
    $service->beginExecution();

    $middleware = new TrackProviderCall($service);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: new stdClass,
        meta: [],
    );

    $response = new class implements StorableContract
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['input_tokens' => 10, 'output_tokens' => 0];
        }

        public function contents(): string
        {
            return 'fake-image-bytes';
        }

        public function mimeType(): string
        {
            return 'image/png';
        }
    };

    $middleware->handle($context, fn () => $response);

    $asset = Asset::latest('id')->first();

    expect($asset)->not->toBeNull();
    expect($asset->owner_type)->toBe('App\\Models\\User');
    expect($asset->owner_id)->toBe(42);
});
