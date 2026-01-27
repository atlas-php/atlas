<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pipelines\PipelineRegistry;
use Atlasphp\Atlas\Pipelines\PipelineRunner;
use Atlasphp\Atlas\PrismProxy;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new PipelineRegistry;
    $this->runner = new PipelineRunner($this->registry, $this->container);
});

test('getPipelineEvents returns all terminal method events', function () {
    $events = PrismProxy::getPipelineEvents();

    // Text module events
    expect($events)->toContain('text.before_text');
    expect($events)->toContain('text.after_text');
    expect($events)->toContain('text.before_stream');
    expect($events)->toContain('text.after_stream');

    // Structured module events
    expect($events)->toContain('structured.before_structured');
    expect($events)->toContain('structured.after_structured');

    // Embeddings module events
    expect($events)->toContain('embeddings.before_embeddings');
    expect($events)->toContain('embeddings.after_embeddings');

    // Image module events
    expect($events)->toContain('image.before_generate');
    expect($events)->toContain('image.after_generate');

    // Audio module events
    expect($events)->toContain('audio.before_audio');
    expect($events)->toContain('audio.after_audio');
    expect($events)->toContain('audio.before_text');
    expect($events)->toContain('audio.after_text');

    // Moderation module events
    expect($events)->toContain('moderation.before_moderation');
    expect($events)->toContain('moderation.after_moderation');
});

test('withMetadata adds metadata immutably', function () {
    $pendingRequest = new stdClass;
    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');

    $proxy2 = $proxy->withMetadata(['key' => 'value']);

    expect($proxy->getMetadata())->toBe([]);
    expect($proxy2->getMetadata())->toBe(['key' => 'value']);
});

test('withMetadata merges metadata', function () {
    $pendingRequest = new stdClass;
    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');

    $proxy2 = $proxy->withMetadata(['key1' => 'value1']);
    $proxy3 = $proxy2->withMetadata(['key2' => 'value2']);

    expect($proxy3->getMetadata())->toBe([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

test('fluent method calls return new proxy instance', function () {
    $pendingRequest = new class
    {
        public function withSystemPrompt(string $prompt): static
        {
            $clone = clone $this;

            return $clone;
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $proxy2 = $proxy->withSystemPrompt('Test prompt');

    expect($proxy2)->toBeInstanceOf(PrismProxy::class);
    expect($proxy2)->not->toBe($proxy);
});

test('fluent method chain works correctly', function () {
    $pendingRequest = new class
    {
        public string $prompt = '';

        public string $maxTokens = '';

        public function withSystemPrompt(string $prompt): static
        {
            $clone = clone $this;
            $clone->prompt = $prompt;

            return $clone;
        }

        public function usingMaxTokens(int $tokens): static
        {
            $clone = clone $this;
            $clone->maxTokens = (string) $tokens;

            return $clone;
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $proxy2 = $proxy->withSystemPrompt('Test')->usingMaxTokens(100);

    expect($proxy2)->toBeInstanceOf(PrismProxy::class);
});

test('terminal method executes with before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('text.before_text');
    $this->registry->define('text.after_text');
    $this->registry->register('text.before_text', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'response'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->asText();

    expect($beforeCalled)->toBeTrue();
    expect($result->text)->toBe('response');
});

test('terminal method executes with after pipeline', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('text.before_text');
    $this->registry->define('text.after_text');
    $this->registry->register('text.after_text', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'test response'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->asText();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->text)->toBe('test response');
});

test('pipeline can modify response', function () {
    $this->registry->define('text.before_text');
    $this->registry->define('text.after_text');
    $this->registry->register('text.after_text', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['text' => 'modified'];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'original'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->asText();

    expect($result->text)->toBe('modified');
});

test('metadata is passed to pipeline context', function () {
    $capturedMetadata = null;

    $this->registry->define('text.before_text');
    $this->registry->define('text.after_text');
    $this->registry->register('text.before_text', new class($capturedMetadata) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['metadata'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'response'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $proxy = $proxy->withMetadata(['request_id' => '123']);
    $proxy->asText();

    expect($capturedMetadata)->toBe(['request_id' => '123']);
});

test('module is passed to pipeline context', function () {
    $capturedModule = null;

    $this->registry->define('text.before_text');
    $this->registry->define('text.after_text');
    $this->registry->register('text.before_text', new class($capturedModule) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->captured = $data['pipeline'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'response'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $proxy->asText();

    expect($capturedModule)->toBe('text');
});

test('generator method returns generator', function () {
    $this->registry->define('text.before_stream');
    $this->registry->define('text.after_stream');

    $pendingRequest = new class
    {
        public function asStream(): Generator
        {
            yield 'chunk1';
            yield 'chunk2';
            yield 'chunk3';
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->asStream();

    expect($result)->toBeInstanceOf(Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toBe(['chunk1', 'chunk2', 'chunk3']);
});

test('generator method runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('text.before_stream');
    $this->registry->register('text.before_stream', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asStream(): Generator
        {
            yield 'chunk';
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->asStream();

    // Pipeline runs when generator is consumed
    iterator_to_array($result);

    expect($beforeCalled)->toBeTrue();
});

test('structured module terminal method works', function () {
    $this->registry->define('structured.before_structured');
    $this->registry->define('structured.after_structured');

    $pendingRequest = new class
    {
        public function asStructured(): object
        {
            return (object) ['data' => ['key' => 'value']];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'structured');
    $result = $proxy->asStructured();

    expect($result->data)->toBe(['key' => 'value']);
});

test('embeddings module terminal method works', function () {
    $this->registry->define('embeddings.before_embeddings');
    $this->registry->define('embeddings.after_embeddings');

    $pendingRequest = new class
    {
        public function asEmbeddings(): object
        {
            return (object) ['embeddings' => [[0.1, 0.2, 0.3]]];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'embeddings');
    $result = $proxy->asEmbeddings();

    expect($result->embeddings)->toBe([[0.1, 0.2, 0.3]]);
});

test('image module terminal method works', function () {
    $this->registry->define('image.before_generate');
    $this->registry->define('image.after_generate');

    $pendingRequest = new class
    {
        public function generate(): object
        {
            return (object) ['url' => 'https://example.com/image.png'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'image');
    $result = $proxy->generate();

    expect($result->url)->toBe('https://example.com/image.png');
});

test('audio module asAudio terminal method works', function () {
    $this->registry->define('audio.before_audio');
    $this->registry->define('audio.after_audio');

    $pendingRequest = new class
    {
        public function asAudio(): object
        {
            return (object) ['audio' => 'base64data'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $result = $proxy->asAudio();

    expect($result->audio)->toBe('base64data');
});

test('audio module asText terminal method works', function () {
    $this->registry->define('audio.before_text');
    $this->registry->define('audio.after_text');

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'transcribed text'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $result = $proxy->asText();

    expect($result->text)->toBe('transcribed text');
});

test('moderation module terminal method works', function () {
    $this->registry->define('moderation.before_moderation');
    $this->registry->define('moderation.after_moderation');

    $pendingRequest = new class
    {
        public function asModeration(): object
        {
            return (object) ['flagged' => false];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'moderation');
    $result = $proxy->asModeration();

    expect($result->flagged)->toBeFalse();
});

test('proxyToRequest returns self when method returns non-object', function () {
    // This tests the edge case where a fluent method returns a non-object value
    // The proxy should return self to maintain the chain
    $pendingRequest = new class
    {
        public function someFluentMethod(): bool
        {
            return true; // Returns non-object
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->someFluentMethod();

    // Should return the same proxy instance to maintain fluent chain
    expect($result)->toBeInstanceOf(PrismProxy::class);
    expect($result)->toBe($proxy);
});

test('proxyToRequest handles null return value', function () {
    $pendingRequest = new class
    {
        public function methodReturningNull(): ?object
        {
            return null;
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->methodReturningNull();

    // Should return self when method returns null
    expect($result)->toBeInstanceOf(PrismProxy::class);
    expect($result)->toBe($proxy);
});

test('proxyToRequest handles string return value', function () {
    $pendingRequest = new class
    {
        public function getConfigValue(): string
        {
            return 'some-config-value';
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->getConfigValue();

    // Should return self when method returns non-object
    expect($result)->toBeInstanceOf(PrismProxy::class);
    expect($result)->toBe($proxy);
});

test('proxyToRequest handles integer return value', function () {
    $pendingRequest = new class
    {
        public function getCount(): int
        {
            return 42;
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'text');
    $result = $proxy->getCount();

    // Should return self when method returns non-object
    expect($result)->toBeInstanceOf(PrismProxy::class);
    expect($result)->toBe($proxy);
});

// =============================================================================
// Structured Module Pipeline Tests
// =============================================================================

test('structured module runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('structured.before_structured');
    $this->registry->define('structured.after_structured');
    $this->registry->register('structured.before_structured', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asStructured(): object
        {
            return (object) ['data' => ['key' => 'value']];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'structured');
    $proxy->asStructured();

    expect($beforeCalled)->toBeTrue();
});

test('structured module runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('structured.before_structured');
    $this->registry->define('structured.after_structured');
    $this->registry->register('structured.after_structured', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asStructured(): object
        {
            return (object) ['data' => ['name' => 'test']];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'structured');
    $proxy->asStructured();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->data)->toBe(['name' => 'test']);
});

test('structured module pipeline can modify response', function () {
    $this->registry->define('structured.before_structured');
    $this->registry->define('structured.after_structured');
    $this->registry->register('structured.after_structured', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['data' => ['modified' => true]];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asStructured(): object
        {
            return (object) ['data' => ['original' => true]];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'structured');
    $result = $proxy->asStructured();

    expect($result->data)->toBe(['modified' => true]);
});

// =============================================================================
// Embeddings Module Pipeline Tests
// =============================================================================

test('embeddings module runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('embeddings.before_embeddings');
    $this->registry->define('embeddings.after_embeddings');
    $this->registry->register('embeddings.before_embeddings', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asEmbeddings(): object
        {
            return (object) ['embeddings' => [[0.1, 0.2]]];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'embeddings');
    $proxy->asEmbeddings();

    expect($beforeCalled)->toBeTrue();
});

test('embeddings module runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('embeddings.before_embeddings');
    $this->registry->define('embeddings.after_embeddings');
    $this->registry->register('embeddings.after_embeddings', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asEmbeddings(): object
        {
            return (object) ['embeddings' => [[0.5, 0.6, 0.7]]];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'embeddings');
    $proxy->asEmbeddings();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->embeddings)->toBe([[0.5, 0.6, 0.7]]);
});

test('embeddings module pipeline can modify response', function () {
    $this->registry->define('embeddings.before_embeddings');
    $this->registry->define('embeddings.after_embeddings');
    $this->registry->register('embeddings.after_embeddings', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['embeddings' => [[1.0, 1.0, 1.0]]];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asEmbeddings(): object
        {
            return (object) ['embeddings' => [[0.0, 0.0, 0.0]]];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'embeddings');
    $result = $proxy->asEmbeddings();

    expect($result->embeddings)->toBe([[1.0, 1.0, 1.0]]);
});

// =============================================================================
// Image Module Pipeline Tests
// =============================================================================

test('image module runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('image.before_generate');
    $this->registry->define('image.after_generate');
    $this->registry->register('image.before_generate', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function generate(): object
        {
            return (object) ['url' => 'https://example.com/img.png'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'image');
    $proxy->generate();

    expect($beforeCalled)->toBeTrue();
});

test('image module runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('image.before_generate');
    $this->registry->define('image.after_generate');
    $this->registry->register('image.after_generate', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function generate(): object
        {
            return (object) ['url' => 'https://example.com/generated.png'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'image');
    $proxy->generate();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->url)->toBe('https://example.com/generated.png');
});

test('image module pipeline can modify response', function () {
    $this->registry->define('image.before_generate');
    $this->registry->define('image.after_generate');
    $this->registry->register('image.after_generate', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['url' => 'https://cdn.example.com/cached.png'];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function generate(): object
        {
            return (object) ['url' => 'https://example.com/original.png'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'image');
    $result = $proxy->generate();

    expect($result->url)->toBe('https://cdn.example.com/cached.png');
});

// =============================================================================
// Audio Module (asAudio) Pipeline Tests
// =============================================================================

test('audio module asAudio runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('audio.before_audio');
    $this->registry->define('audio.after_audio');
    $this->registry->register('audio.before_audio', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asAudio(): object
        {
            return (object) ['audio' => 'base64data'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $proxy->asAudio();

    expect($beforeCalled)->toBeTrue();
});

test('audio module asAudio runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('audio.before_audio');
    $this->registry->define('audio.after_audio');
    $this->registry->register('audio.after_audio', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asAudio(): object
        {
            return (object) ['audio' => 'audiobase64content'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $proxy->asAudio();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->audio)->toBe('audiobase64content');
});

test('audio module asAudio pipeline can modify response', function () {
    $this->registry->define('audio.before_audio');
    $this->registry->define('audio.after_audio');
    $this->registry->register('audio.after_audio', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['audio' => 'modified_audio_data'];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asAudio(): object
        {
            return (object) ['audio' => 'original_audio_data'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $result = $proxy->asAudio();

    expect($result->audio)->toBe('modified_audio_data');
});

// =============================================================================
// Audio Module (asText - Speech to Text) Pipeline Tests
// =============================================================================

test('audio module asText runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('audio.before_text');
    $this->registry->define('audio.after_text');
    $this->registry->register('audio.before_text', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'transcribed'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $proxy->asText();

    expect($beforeCalled)->toBeTrue();
});

test('audio module asText runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('audio.before_text');
    $this->registry->define('audio.after_text');
    $this->registry->register('audio.after_text', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'Hello, this is a transcription.'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $proxy->asText();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->text)->toBe('Hello, this is a transcription.');
});

test('audio module asText pipeline can modify response', function () {
    $this->registry->define('audio.before_text');
    $this->registry->define('audio.after_text');
    $this->registry->register('audio.after_text', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            $data['response'] = (object) ['text' => 'Modified transcription'];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asText(): object
        {
            return (object) ['text' => 'Original transcription'];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'audio');
    $result = $proxy->asText();

    expect($result->text)->toBe('Modified transcription');
});

// =============================================================================
// Moderation Module Pipeline Tests
// =============================================================================

test('moderation module runs before pipeline', function () {
    $beforeCalled = false;

    $this->registry->define('moderation.before_moderation');
    $this->registry->define('moderation.after_moderation');
    $this->registry->register('moderation.before_moderation', new class($beforeCalled) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asModeration(): object
        {
            return (object) ['flagged' => false];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'moderation');
    $proxy->asModeration();

    expect($beforeCalled)->toBeTrue();
});

test('moderation module runs after pipeline with response', function () {
    $afterCalled = false;
    $capturedResponse = null;

    $this->registry->define('moderation.before_moderation');
    $this->registry->define('moderation.after_moderation');
    $this->registry->register('moderation.after_moderation', new class($afterCalled, $capturedResponse) implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function __construct(private bool &$called, private &$captured) {}

        public function handle(mixed $data, Closure $next): mixed
        {
            $this->called = true;
            $this->captured = $data['response'] ?? null;

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asModeration(): object
        {
            return (object) ['flagged' => true, 'categories' => ['violence']];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'moderation');
    $proxy->asModeration();

    expect($afterCalled)->toBeTrue();
    expect($capturedResponse->flagged)->toBeTrue();
    expect($capturedResponse->categories)->toBe(['violence']);
});

test('moderation module pipeline can modify response', function () {
    $this->registry->define('moderation.before_moderation');
    $this->registry->define('moderation.after_moderation');
    $this->registry->register('moderation.after_moderation', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, Closure $next): mixed
        {
            // Simulate overriding moderation result
            $data['response'] = (object) ['flagged' => false, 'overridden' => true];

            return $next($data);
        }
    });

    $pendingRequest = new class
    {
        public function asModeration(): object
        {
            return (object) ['flagged' => true];
        }
    };

    $proxy = new PrismProxy($this->runner, $pendingRequest, 'moderation');
    $result = $proxy->asModeration();

    expect($result->flagged)->toBeFalse();
    expect($result->overridden)->toBeTrue();
});

// =============================================================================
// Cross-Module Pipeline Context Tests
// =============================================================================

test('each module receives correct pipeline name in context', function () {
    $modules = [
        'structured' => 'asStructured',
        'embeddings' => 'asEmbeddings',
        'image' => 'generate',
        'moderation' => 'asModeration',
    ];

    foreach ($modules as $module => $method) {
        $capturedPipeline = null;

        $container = new Container;
        $registry = new PipelineRegistry;
        $runner = new PipelineRunner($registry, $container);

        $beforeEvent = $module === 'image' ? "$module.before_generate" : "$module.before_$method";
        $beforeEvent = match ($module) {
            'structured' => 'structured.before_structured',
            'embeddings' => 'embeddings.before_embeddings',
            'image' => 'image.before_generate',
            'moderation' => 'moderation.before_moderation',
        };

        $registry->define($beforeEvent);
        $registry->register($beforeEvent, new class($capturedPipeline) implements \Atlasphp\Atlas\Contracts\PipelineContract
        {
            public function __construct(private &$captured) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->captured = $data['pipeline'] ?? null;

                return $next($data);
            }
        });

        $pendingRequest = new class($method)
        {
            public function __construct(private string $method) {}

            public function __call(string $name, array $arguments): object
            {
                return (object) ['result' => 'ok'];
            }
        };

        $proxy = new PrismProxy($runner, $pendingRequest, $module);
        $proxy->$method();

        expect($capturedPipeline)->toBe($module, "Module $module should have pipeline name '$module' in context");
    }
});

test('each module passes metadata to pipeline', function () {
    $modules = [
        'structured' => 'asStructured',
        'embeddings' => 'asEmbeddings',
        'image' => 'generate',
        'moderation' => 'asModeration',
    ];

    foreach ($modules as $module => $method) {
        $capturedMetadata = null;

        $container = new Container;
        $registry = new PipelineRegistry;
        $runner = new PipelineRunner($registry, $container);

        $beforeEvent = match ($module) {
            'structured' => 'structured.before_structured',
            'embeddings' => 'embeddings.before_embeddings',
            'image' => 'image.before_generate',
            'moderation' => 'moderation.before_moderation',
        };

        $registry->define($beforeEvent);
        $registry->register($beforeEvent, new class($capturedMetadata) implements \Atlasphp\Atlas\Contracts\PipelineContract
        {
            public function __construct(private &$captured) {}

            public function handle(mixed $data, Closure $next): mixed
            {
                $this->captured = $data['metadata'] ?? null;

                return $next($data);
            }
        });

        $pendingRequest = new class($method)
        {
            public function __construct(private string $method) {}

            public function __call(string $name, array $arguments): object
            {
                return (object) ['result' => 'ok'];
            }
        };

        $proxy = new PrismProxy($runner, $pendingRequest, $module);
        $proxy = $proxy->withMetadata(['module_test' => $module]);
        $proxy->$method();

        expect($capturedMetadata)->toBe(['module_test' => $module], "Module $module should pass metadata to pipeline");
    }
});
