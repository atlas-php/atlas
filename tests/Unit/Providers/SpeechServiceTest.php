<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Illuminate\Config\Repository;

beforeEach(function () {
    // Mock the contract interface to avoid return type enforcement
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'speech' => [
                'provider' => 'openai',
                'model' => 'tts-1',
                'transcription_model' => 'whisper-1',
            ],
        ],
    ]));
    $this->service = new SpeechService($this->prismBuilder, $this->configService);
});

test('it returns a new instance when using provider', function () {
    $newService = $this->service->using('anthropic');

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting model', function () {
    $newService = $this->service->model('tts-1-hd');

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting transcription model', function () {
    $newService = $this->service->transcriptionModel('whisper-1');

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting voice', function () {
    $newService = $this->service->voice('alloy');

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting format', function () {
    $newService = $this->service->format('wav');

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it speaks text with defaults', function () {
    $mockRequest = Mockery::mock();

    $mockResponse = new stdClass;
    $mockResponse->audio = 'base64-encoded-audio-data';

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello world', Mockery::type('array'))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->speak('Hello world');

    expect($result)->toBe([
        'audio' => 'base64-encoded-audio-data',
        'format' => 'mp3',
    ]);
});

test('it speaks with custom voice', function () {
    $mockRequest = Mockery::mock();

    $mockResponse = new stdClass;
    $mockResponse->audio = 'audio-data';

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::on(function ($options) {
            return isset($options['voice']) && $options['voice'] === 'nova';
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service
        ->voice('nova')
        ->speak('Hello');

    expect($result)->toHaveKey('audio');
});

test('it chains fluent methods for speak', function () {
    $mockRequest = Mockery::mock();

    $mockResponse = new stdClass;
    $mockResponse->audio = 'audio-data';

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('anthropic', 'custom-tts', 'Hello', Mockery::type('array'))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service
        ->using('anthropic')
        ->model('custom-tts')
        ->voice('alloy')
        ->format('wav')
        ->speak('Hello');

    expect($result['format'])->toBe('wav');
});
