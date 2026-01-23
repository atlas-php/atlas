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

test('withProvider returns new instance with provider', function () {
    $result = $this->request->withProvider('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withModel returns new instance with model', function () {
    $result = $this->request->withModel('dall-e-3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withProvider with model returns new instance with both', function () {
    $result = $this->request->withProvider('openai', 'dall-e-3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withSize returns new instance with size', function () {
    $result = $this->request->withSize('1024x1024');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingImageRequest::class);
});

test('withQuality returns new instance with quality', function () {
    $result = $this->request->withQuality('hd');

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

test('generate calls service with empty options when no config', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [], null)
        ->andReturn($expected);

    $result = $this->request->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes provider to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('anthropic')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes model to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return $options['model'] === 'dall-e-2';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withModel('dall-e-2')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes provider and model together to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return $options['provider'] === 'openai' && $options['model'] === 'dall-e-3';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('openai', 'dall-e-3')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes size to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return $options['size'] === '1024x1024';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withSize('1024x1024')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes quality to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return $options['quality'] === 'hd';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withQuality('hd')->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes provider options to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return isset($options['provider_options']) && $options['provider_options']['style'] === 'vivid';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProviderOptions(['style' => 'vivid'])->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes metadata to service options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $metadata = ['user_id' => 123];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) use ($metadata) {
            return isset($options['metadata']) && $options['metadata'] === $metadata;
        }), null)
        ->andReturn($expected);

    $result = $this->request->withMetadata($metadata)->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate passes retry config to service', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $retryConfig = [3, 1000, null, true];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', [], $retryConfig)
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->generate('A sunset');

    expect($result)->toBe($expected);
});

test('chaining preserves all config', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $metadata = ['user_id' => 123];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) use ($metadata) {
            return $options['provider'] === 'openai'
                && $options['model'] === 'dall-e-3'
                && $options['size'] === '1024x1024'
                && $options['quality'] === 'hd'
                && isset($options['provider_options']) && $options['provider_options']['style'] === 'vivid'
                && isset($options['metadata']) && $options['metadata'] === $metadata;
        }), [3, 1000, null, true])
        ->andReturn($expected);

    $result = $this->request
        ->withProvider('openai', 'dall-e-3')
        ->withSize('1024x1024')
        ->withQuality('hd')
        ->withProviderOptions(['style' => 'vivid'])
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->generate('A sunset');

    expect($result)->toBe($expected);
});

test('generate merges additional options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];
    $additionalOptions = ['extra_option' => true];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            return isset($options['extra_option']) && $options['extra_option'] === true;
        }), null)
        ->andReturn($expected);

    $result = $this->request->generate('A sunset', $additionalOptions);

    expect($result)->toBe($expected);
});

test('fluent config overrides additional options', function () {
    $expected = ['url' => 'https://example.com/image.png', 'base64' => null, 'revised_prompt' => null];

    $this->imageService
        ->shouldReceive('generate')
        ->once()
        ->with('A sunset', Mockery::on(function ($options) {
            // Fluent config should override additional options
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($expected);

    // Provider from fluent should override provider from additional options
    $result = $this->request
        ->withProvider('anthropic')
        ->generate('A sunset', ['provider' => 'openai']);

    expect($result)->toBe($expected);
});
