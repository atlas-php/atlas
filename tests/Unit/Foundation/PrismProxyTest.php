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
