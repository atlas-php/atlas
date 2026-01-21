<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Illuminate\Config\Repository;

beforeEach(function () {
    // Mock the contract interface to avoid return type enforcement
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'image' => [
                'provider' => 'openai',
                'model' => 'dall-e-3',
            ],
        ],
    ]));
    $this->service = new ImageService($this->prismBuilder, $this->configService);
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

test('it generates image with defaults', function () {
    $mockRequest = Mockery::mock();

    $mockResponse = new stdClass;
    $mockResponse->url = 'https://example.com/image.png';
    $mockResponse->base64 = null;
    $mockResponse->revisedPrompt = 'A beautiful sunset over mountains';

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('openai', 'dall-e-3', 'A sunset', Mockery::type('array'))
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

    $mockResponse = new stdClass;
    $mockResponse->url = 'https://example.com/image.png';
    $mockResponse->base64 = null;
    $mockResponse->revisedPrompt = null;

    $this->prismBuilder
        ->shouldReceive('forImage')
        ->with('anthropic', 'custom-model', 'A sunset', Mockery::type('array'))
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
