<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Support\PendingImageRequest;

beforeEach(function () {
    $this->imageService = Mockery::mock(ImageService::class);

    $this->request = new PendingImageRequest($this->imageService);
});

afterEach(function () {
    Mockery::close();
});

test('using returns new instance with provider', function () {
    $result = $this->request->using('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('model returns new instance with model', function () {
    $result = $this->request->model('dall-e-3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('size returns new instance with size', function () {
    $result = $this->request->size('1024x1024');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('quality returns new instance with quality', function () {
    $result = $this->request->quality('hd');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withProviderOptions returns new instance with options', function () {
    $result = $this->request->withProviderOptions(['style' => 'vivid']);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withMetadata returns new instance with metadata', function () {
    $result = $this->request->withMetadata(['user_id' => 123]);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withRetry returns new instance with retry config', function () {
    $result = $this->request->withRetry(3, 1000);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('generate calls service with empty config', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with provider', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('using')
        ->once()
        ->with('anthropic')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->using('anthropic')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with model', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('model')
        ->once()
        ->with('dall-e-2')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->model('dall-e-2')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with size', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('size')
        ->once()
        ->with('1024x1024')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->size('1024x1024')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with quality', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('quality')
        ->once()
        ->with('hd')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->quality('hd')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with provider options', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('withProviderOptions')
        ->once()
        ->with(['style' => 'vivid'])
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->withProviderOptions(['style' => 'vivid'])->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with metadata', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $metadata = ['user_id' => 123];

    $this->imageService
        ->shouldReceive('withMetadata')
        ->once()
        ->with($metadata)
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->withMetadata($metadata)->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate calls configured service with retry', function () {
    $configuredService = Mockery::mock(ImageService::class);
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('withRetry')
        ->once()
        ->with(3, 1000, null, true)
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->generate('A sunset');

    expect($result)->toBe($expected);
});

test('chaining preserves all config', function () {
    $step1 = Mockery::mock(ImageService::class);
    $step2 = Mockery::mock(ImageService::class);
    $step3 = Mockery::mock(ImageService::class);
    $step4 = Mockery::mock(ImageService::class);
    $step5 = Mockery::mock(ImageService::class);
    $step6 = Mockery::mock(ImageService::class);
    $step7 = Mockery::mock(ImageService::class);

    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $metadata = ['user_id' => 123];

    $this->imageService
        ->shouldReceive('using')
        ->with('openai')
        ->andReturn($step1);

    $step1->shouldReceive('model')->with('dall-e-3')->andReturn($step2);
    $step2->shouldReceive('size')->with('1024x1024')->andReturn($step3);
    $step3->shouldReceive('quality')->with('hd')->andReturn($step4);
    $step4->shouldReceive('withProviderOptions')->with(['style' => 'vivid'])->andReturn($step5);
    $step5->shouldReceive('withMetadata')->with($metadata)->andReturn($step6);
    $step6->shouldReceive('withRetry')->with(3, 1000, null, true)->andReturn($step7);

    $step7
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [])
        ->andReturn($expected);

    $result = $this->request
        ->using('openai')
        ->model('dall-e-3')
        ->size('1024x1024')
        ->quality('hd')
        ->withProviderOptions(['style' => 'vivid'])
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes options to service', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $options = ['extra_option' => true];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', $options)
        ->andReturn($expected);

    $result = $this->request->generate('A sunset', $options);

    expect($result)->toBe($expected);
});
