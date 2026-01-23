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

test('it generates image with defaults', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('A sunset');

    expect($result)->toBeArray();
    expect($result['url'])->toBe('https://example.com/image.png');
});

test('it generates image with provider override', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('anthropic', 'dall-e-3', 'A sunset', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('A sunset', ['provider' => 'anthropic']);

    expect($result)->toBeArray();
    expect($result['url'])->toBe('https://example.com/image.png');
});

test('it generates image with model override', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-2', 'A sunset', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('A sunset', ['model' => 'dall-e-2']);

    expect($result)->toBeArray();
    expect($result['url'])->toBe('https://example.com/image.png');
});

test('it generates image with size and quality', function () {
    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', ['size' => '1024x1024', 'quality' => 'hd'], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('A sunset', [
        'size' => '1024x1024',
        'quality' => 'hd',
    ]);

    expect($result)->toBeArray();
    expect($result['url'])->toBe('https://example.com/image.png');
});

test('it passes provider options to PrismBuilder', function () {
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
            return isset($options['style']) && $options['style'] === 'vivid';
        }), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service->generate('A sunset', ['provider_options' => ['style' => 'vivid']]);
});

test('it passes retry to PrismBuilder', function () {
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
        ->with('openai', 'dall-e-3', 'A sunset', [], $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service->generate('A sunset', [], $retryConfig);
});

test('it runs image.before_generate pipeline', function () {
    $this->registry->define('image.before_generate', 'Before generate pipeline');
    ImageBeforeGenerateHandler::reset();
    $this->registry->register('image.before_generate', ImageBeforeGenerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->images = [];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
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
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service->generate('A sunset');

    expect(ImageAfterGenerateHandler::$called)->toBeTrue();
    expect(ImageAfterGenerateHandler::$data)->not->toBeNull();
    expect(ImageAfterGenerateHandler::$data['prompt'])->toBe('A sunset');
    expect(ImageAfterGenerateHandler::$data['result']['url'])->toBe('https://example.com/image.png');
});

test('it runs image.on_error pipeline when generate fails', function () {
    $this->registry->define('image.on_error', 'Error pipeline');
    ImageErrorHandler::reset();
    $this->registry->register('image.on_error', ImageErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->andThrow(new \RuntimeException('API Error'));

    try {
        $this->service->generate('A sunset');
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(ImageErrorHandler::$called)->toBeTrue();
    expect(ImageErrorHandler::$data)->not->toBeNull();
    expect(ImageErrorHandler::$data['prompt'])->toBe('A sunset');
    expect(ImageErrorHandler::$data['provider'])->toBe('openai');
    expect(ImageErrorHandler::$data['model'])->toBe('dall-e-3');
    expect(ImageErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
});

test('it includes size and quality in after_generate pipeline', function () {
    $this->registry->define('image.after_generate', 'After generate pipeline');
    ImageAfterGenerateHandler::reset();
    $this->registry->register('image.after_generate', ImageAfterGenerateHandler::class);

    $mockRequest = Mockery::mock();

    $mockImage = new stdClass;
    $mockImage->url = 'https://example.com/image.png';
    $mockImage->base64 = null;
    $mockImage->revisedPrompt = null;

    $mockResponse = new stdClass;
    $mockResponse->images = [$mockImage];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $this->service->generate('A sunset', ['size' => '1024x1024', 'quality' => 'hd']);

    expect(ImageAfterGenerateHandler::$data['size'])->toBe('1024x1024');
    expect(ImageAfterGenerateHandler::$data['quality'])->toBe('hd');
});

test('it uses config retry when explicit retry is null', function () {
    $configRetry = [2, 500, null, true];
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
    $mockResponse = new stdClass;
    $mockResponse->images = [];

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', [], $configRetry)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('generate')
        ->once()
        ->andReturn($mockResponse);

    $service->generate('A sunset');
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
