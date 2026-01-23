<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'image' => [
                'provider' => 'openai',
                'model' => 'dall-e-3',
            ],
        ],
    ]));
    $this->container = new Container;
    $this->registry = new PipelineRegistry;
    $this->pipelineRunner = new PipelineRunner($this->registry, $this->container);
    $this->service = new ImageService($this->prismBuilder, $this->configService, $this->pipelineRunner);
});

test('it returns a new instance when using provider', function () {
    $newService = $this->service->using('anthropic');

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting model', function () {
    $newService = $this->service->model('dall-e-2');

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting size', function () {
    $newService = $this->service->size('1024x1024');

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting quality', function () {
    $newService = $this->service->quality('hd');

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting provider options', function () {
    $newService = $this->service->withProviderOptions(['style' => 'vivid']);

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it merges provider options', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::on(function ($options) {
            return isset($options['style']) && $options['style'] === 'vivid'
                && isset($options['response_format']) && $options['response_format'] === 'url';
        }), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withProviderOptions(['style' => 'vivid'])
        ->withProviderOptions(['response_format' => 'url'])
        ->generate('A sunset');
});

test('it generates image with defaults', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = 'A beautiful sunset over mountains';

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('A sunset');

    expect($result)->toBe([
        'url' => 'https://example.com/image.png',
        'base64' => null,
        'revised_prompt' => 'A beautiful sunset over mountains',
    ]);
});

test('it chains fluent methods', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('anthropic', 'custom-model', 'A sunset', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service
        ->using('anthropic')
        ->model('custom-model')
        ->size('512x512')
        ->quality('standard')
        ->generate('A sunset');

    expect($result)->toHaveKeys(['url', 'base64', 'revised_prompt']);
});

test('it runs image.before_generate pipeline', function () {
    $this->registry->define('image.before_generate', 'Before generate pipeline');
    ImageBeforeGenerateHandler::reset();
    $this->registry->register('image.before_generate', ImageBeforeGenerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andReturn($mockResponse);

    $this->service->generate('A sunset');

    expect(ImageBeforeGenerateHandler::$called)->toBeTrue();
    expect(ImageBeforeGenerateHandler::$data)->not->toBeNull();
    expect(ImageBeforeGenerateHandler::$data['prompt'])->toBe('A sunset');
    expect(ImageBeforeGenerateHandler::$data['provider'])->toBe('openai');
    expect(ImageBeforeGenerateHandler::$data['model'])->toBe('dall-e-3');
});

test('it runs image.after_generate pipeline', function () {
    $this->registry->define('image.after_generate', 'After generate pipeline');
    ImageAfterGenerateHandler::reset();
    $this->registry->register('image.after_generate', ImageAfterGenerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = 'Revised prompt';

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andReturn($mockResponse);

    $this->service
        ->size('1024x1024')
        ->quality('hd')
        ->generate('A sunset');

    expect(ImageAfterGenerateHandler::$called)->toBeTrue();
    expect(ImageAfterGenerateHandler::$data)->not->toBeNull();
    expect(ImageAfterGenerateHandler::$data['prompt'])->toBe('A sunset');
    expect(ImageAfterGenerateHandler::$data['provider'])->toBe('openai');
    expect(ImageAfterGenerateHandler::$data['model'])->toBe('dall-e-3');
    expect(ImageAfterGenerateHandler::$data['size'])->toBe('1024x1024');
    expect(ImageAfterGenerateHandler::$data['quality'])->toBe('hd');
    expect(ImageAfterGenerateHandler::$data['result'])->toBe([
        'url' => 'https://example.com/image.png',
        'base64' => null,
        'revised_prompt' => 'Revised prompt',
    ]);
});

test('it allows before_generate pipeline to modify prompt', function () {
    $this->registry->define('image.before_generate', 'Before generate pipeline');
    $this->registry->register('image.before_generate', ImagePromptModifyingHandler::class);

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'MODIFIED: A sunset', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andReturn($mockResponse);

    $this->service->generate('A sunset');
});

test('it runs image.on_error pipeline when generate fails', function () {
    $this->registry->define('image.on_error', 'Error pipeline');
    ImageErrorHandler::reset();
    $this->registry->register('image.on_error', ImageErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andThrow(new \RuntimeException('Image API Error'));

    try {
        $this->service
            ->size('1024x1024')
            ->quality('hd')
            ->generate('A sunset');
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(ImageErrorHandler::$called)->toBeTrue();
    expect(ImageErrorHandler::$data)->not->toBeNull();
    expect(ImageErrorHandler::$data['prompt'])->toBe('A sunset');
    expect(ImageErrorHandler::$data['provider'])->toBe('openai');
    expect(ImageErrorHandler::$data['model'])->toBe('dall-e-3');
    expect(ImageErrorHandler::$data['size'])->toBe('1024x1024');
    expect(ImageErrorHandler::$data['quality'])->toBe('hd');
    expect(ImageErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(ImageErrorHandler::$data['exception']->getMessage())->toBe('Image API Error');
});

test('it rethrows exception after running error pipeline', function () {
    $this->registry->define('image.on_error', 'Error pipeline');
    ImageErrorHandler::reset();
    $this->registry->register('image.on_error', ImageErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andThrow(new \RuntimeException('Image API Error'));

    expect(fn () => $this->service->generate('A sunset'))
        ->toThrow(\RuntimeException::class, 'Image API Error');
});

test('it includes size and quality in before_generate pipeline data', function () {
    $this->registry->define('image.before_generate', 'Before generate pipeline');
    ImageBeforeGenerateHandler::reset();
    $this->registry->register('image.before_generate', ImageBeforeGenerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andReturn($mockResponse);

    $this->service
        ->size('1024x1024')
        ->quality('hd')
        ->generate('A sunset');

    expect(ImageBeforeGenerateHandler::$data['size'])->toBe('1024x1024');
    expect(ImageBeforeGenerateHandler::$data['quality'])->toBe('hd');
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it returns a new instance when setting retry', function () {
    $newService = $this->service->withRetry(3, 1000);

    expect($newService)->toBeInstanceOf(ImageService::class);
    expect($newService)->not->toBe($this->service);
});

test('it passes retry to PrismBuilder when generating', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $retryConfig = [3, 1000, null, true];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'), $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry(3, 1000, null, true)
        ->generate('A sunset');
});

test('it uses config retry when withRetry is not called', function () {
    // Create service with config that has retry enabled
    $configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'image' => [
                'provider' => 'openai',
                'model' => 'dall-e-3',
            ],
            'retry' => [
                'enabled' => true,
                'times' => 2,
                'delay_ms' => 500,
            ],
        ],
    ]));

    $service = new ImageService($this->prismBuilder, $configService, $this->pipelineRunner);

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'), Mockery::on(function ($retry) {
            return is_array($retry) && $retry[0] === 2 && $retry[1] === 500;
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $service->generate('A sunset');
});

test('it passes retry with closure to PrismBuilder', function () {
    $sleepFn = fn ($attempt) => $attempt * 100;

    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'), Mockery::on(function ($retry) use ($sleepFn) {
            return is_array($retry) && $retry[0] === 3 && $retry[1] === $sleepFn;
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry(3, $sleepFn)
        ->generate('A sunset');
});

test('it passes retry with array of delays to PrismBuilder', function () {
    $mockRequest = Mockery::mock();
    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'), Mockery::on(function ($retry) {
            return is_array($retry) && $retry[0] === [100, 200, 300];
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry([100, 200, 300])
        ->generate('A sunset');
});

// Pipeline Handler Classes for Tests

class ImageBeforeGenerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ImageAfterGenerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ImagePromptModifyingHandler implements PipelineContract
{
    public function handle(mixed $data, \Closure $next): mixed
    {
        $data['prompt'] = 'MODIFIED: '.$data['prompt'];

        return $next($data);
    }
}

class ImageErrorHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}
