<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
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
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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
            $this->usage = (object) ['inputTokens' => 50, 'outputTokens' => 25];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 5, 'outputTokens' => 0];
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
            $this->usage = (object) ['inputTokens' => 20, 'outputTokens' => 15];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 5, 'outputTokens' => 0];
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

it('completes standalone execution with token counts', function () {
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
            $this->usage = (object) ['inputTokens' => 100, 'outputTokens' => 42];
        }
    };

    $middleware->handle($context, fn () => $response);

    $execution = Execution::latest('id')->first();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->total_input_tokens)->toBe(100);
    expect($execution->total_output_tokens)->toBe(42);
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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

    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = (object) ['inputTokens' => 10, 'outputTokens' => 0];
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
    expect($asset->metadata)->toBeArray();
    expect($asset->metadata['source'])->toBe('tool_execution');
    expect($asset->metadata['tool_call_id'])->toBe($toolCallRecord->id);
    expect($asset->metadata['tool_name'])->toBe('generate_image');
});
