<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\PendingSpeechRequest;

beforeEach(function () {
    $this->speechService = Mockery::mock(SpeechService::class);

    $this->request = new PendingSpeechRequest($this->speechService);
});

afterEach(function () {
    Mockery::close();
});

test('using returns new instance with provider', function () {
    $result = $this->request->using('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('model returns new instance with model', function () {
    $result = $this->request->model('tts-1');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('transcriptionModel returns new instance with transcription model', function () {
    $result = $this->request->transcriptionModel('whisper-1');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('voice returns new instance with voice', function () {
    $result = $this->request->voice('alloy');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('format returns new instance with format', function () {
    $result = $this->request->format('mp3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('speed returns new instance with speed', function () {
    $result = $this->request->speed(1.5);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withProviderOptions returns new instance with options', function () {
    $result = $this->request->withProviderOptions(['language' => 'en']);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withMetadata returns new instance with metadata', function () {
    $result = $this->request->withMetadata(['user_id' => 123]);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withRetry returns new instance with retry config', function () {
    $result = $this->request->withRetry(3, 1000);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('speak calls service with empty config', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with provider', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('using')
        ->once()
        ->with('openai')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->using('openai')->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with model', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('model')
        ->once()
        ->with('tts-1-hd')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->model('tts-1-hd')->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with voice', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('voice')
        ->once()
        ->with('nova')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->voice('nova')->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with format', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'wav'];

    $this->speechService
        ->shouldReceive('format')
        ->once()
        ->with('wav')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->format('wav')->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with speed', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('speed')
        ->once()
        ->with(1.5)
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->speed(1.5)->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with provider options', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('withProviderOptions')
        ->once()
        ->with(['language' => 'en'])
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->withProviderOptions(['language' => 'en'])->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with metadata', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $metadata = ['user_id' => 123];

    $this->speechService
        ->shouldReceive('withMetadata')
        ->once()
        ->with($metadata)
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->withMetadata($metadata)->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak calls configured service with retry', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('withRetry')
        ->once()
        ->with(3, 1000, null, true)
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->speak('Hello world');

    expect($result)->toBe($expected);
});

test('transcribe calls service with empty config', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', [])
        ->andReturn($expected);

    $result = $this->request->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('transcribe calls configured service with transcription model', function () {
    $configuredService = Mockery::mock(SpeechService::class);
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcriptionModel')
        ->once()
        ->with('whisper-1')
        ->andReturn($configuredService);

    $configuredService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', [])
        ->andReturn($expected);

    $result = $this->request->transcriptionModel('whisper-1')->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('chaining speak preserves all config', function () {
    $step1 = Mockery::mock(SpeechService::class);
    $step2 = Mockery::mock(SpeechService::class);
    $step3 = Mockery::mock(SpeechService::class);
    $step4 = Mockery::mock(SpeechService::class);
    $step5 = Mockery::mock(SpeechService::class);
    $step6 = Mockery::mock(SpeechService::class);
    $step7 = Mockery::mock(SpeechService::class);

    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $metadata = ['user_id' => 123];

    $this->speechService
        ->shouldReceive('using')
        ->with('openai')
        ->andReturn($step1);

    $step1->shouldReceive('model')->with('tts-1-hd')->andReturn($step2);
    $step2->shouldReceive('voice')->with('alloy')->andReturn($step3);
    $step3->shouldReceive('format')->with('mp3')->andReturn($step4);
    $step4->shouldReceive('speed')->with(1.25)->andReturn($step5);
    $step5->shouldReceive('withProviderOptions')->with(['language' => 'en'])->andReturn($step6);
    $step6->shouldReceive('withMetadata')->with($metadata)->andReturn($step7);

    // No transcriptionModel in speak chain, so withRetry is on step7
    $step7
        ->shouldReceive('withRetry')
        ->with(3, 1000, null, true)
        ->andReturn($step7);

    $step7
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', [])
        ->andReturn($expected);

    $result = $this->request
        ->using('openai')
        ->model('tts-1-hd')
        ->voice('alloy')
        ->format('mp3')
        ->speed(1.25)
        ->withProviderOptions(['language' => 'en'])
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->speak('Hello world');

    expect($result)->toBe($expected);
});

test('speak passes options to service', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $options = ['extra_option' => true];

    $this->speechService
        ->shouldReceive('speak')
        ->once()
        ->with('Hello world', $options)
        ->andReturn($expected);

    $result = $this->request->speak('Hello world', $options);

    expect($result)->toBe($expected);
});

test('transcribe passes options to service', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];
    $options = ['extra_option' => true];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', $options)
        ->andReturn($expected);

    $result = $this->request->transcribe('/path/to/audio.mp3', $options);

    expect($result)->toBe($expected);
});
