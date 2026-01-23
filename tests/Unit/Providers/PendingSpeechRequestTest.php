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

test('withProvider returns new instance with provider', function () {
    $result = $this->request->withProvider('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withModel returns new instance with model', function () {
    $result = $this->request->withModel('tts-1');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withProvider with model returns new instance with both', function () {
    $result = $this->request->withProvider('openai', 'tts-1-hd');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('transcriptionModel returns new instance with transcription model', function () {
    $result = $this->request->transcriptionModel('whisper-1');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withVoice returns new instance with voice', function () {
    $result = $this->request->withVoice('alloy');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('format returns new instance with format', function () {
    $result = $this->request->format('mp3');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('withSpeed returns new instance with speed', function () {
    $result = $this->request->withSpeed(1.5);

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

test('generate calls service with empty options when no config', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', [], null)
        ->andReturn($expected);

    $result = $this->request->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes provider to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['provider'] === 'openai';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('openai')->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes model to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['model'] === 'tts-1-hd';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withModel('tts-1-hd')->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes provider and model together to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['provider'] === 'openai' && $options['model'] === 'tts-1-hd';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('openai', 'tts-1-hd')->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes voice to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['voice'] === 'nova';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withVoice('nova')->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes format to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'wav'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['format'] === 'wav';
        }), null)
        ->andReturn($expected);

    $result = $this->request->format('wav')->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes speed to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['speed'] === 1.5;
        }), null)
        ->andReturn($expected);

    $result = $this->request->withSpeed(1.5)->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes provider options to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['provider_options']) && $options['provider_options']['language'] === 'en';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProviderOptions(['language' => 'en'])->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes metadata to service options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $metadata = ['user_id' => 123];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) use ($metadata) {
            return isset($options['metadata']) && $options['metadata'] === $metadata;
        }), null)
        ->andReturn($expected);

    $result = $this->request->withMetadata($metadata)->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate passes retry config to service', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $retryConfig = [3, 1000, null, true];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', [], $retryConfig)
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->generate('Hello world');

    expect($result)->toBe($expected);
});

test('transcribe calls service with empty options when no config', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', [], null)
        ->andReturn($expected);

    $result = $this->request->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('transcribe passes provider to service options', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', Mockery::on(function ($options) {
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($expected);

    $result = $this->request->withProvider('anthropic')->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('transcribe passes transcription_model to service options', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', Mockery::on(function ($options) {
            return $options['transcription_model'] === 'whisper-1';
        }), null)
        ->andReturn($expected);

    $result = $this->request->transcriptionModel('whisper-1')->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('transcribe passes retry config to service', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];
    $retryConfig = [3, 1000, null, true];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', [], $retryConfig)
        ->andReturn($expected);

    $result = $this->request->withRetry(3, 1000)->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('chaining speak preserves all config', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $metadata = ['user_id' => 123];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) use ($metadata) {
            return $options['provider'] === 'openai'
                && $options['model'] === 'tts-1-hd'
                && $options['voice'] === 'alloy'
                && $options['format'] === 'mp3'
                && $options['speed'] === 1.25
                && isset($options['provider_options']) && $options['provider_options']['language'] === 'en'
                && isset($options['metadata']) && $options['metadata'] === $metadata;
        }), [3, 1000, null, true])
        ->andReturn($expected);

    $result = $this->request
        ->withProvider('openai', 'tts-1-hd')
        ->withVoice('alloy')
        ->format('mp3')
        ->withSpeed(1.25)
        ->withProviderOptions(['language' => 'en'])
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->generate('Hello world');

    expect($result)->toBe($expected);
});

test('generate merges additional options', function () {
    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];
    $additionalOptions = ['extra_option' => true];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['extra_option']) && $options['extra_option'] === true;
        }), null)
        ->andReturn($expected);

    $result = $this->request->generate('Hello world', $additionalOptions);

    expect($result)->toBe($expected);
});

test('transcribe merges additional options', function () {
    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];
    $additionalOptions = ['extra_option' => true];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', Mockery::on(function ($options) {
            return isset($options['extra_option']) && $options['extra_option'] === true;
        }), null)
        ->andReturn($expected);

    $result = $this->request->transcribe('/path/to/audio.mp3', $additionalOptions);

    expect($result)->toBe($expected);
});

test('whenProvider returns new instance with callback', function () {
    $result = $this->request->whenProvider('openai', fn ($r) => $r);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingSpeechRequest::class);
});

test('whenProvider applies callback on generate when provider matches', function () {
    config(['atlas.speech.provider' => 'openai']);

    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['provider_options']) && $options['provider_options']['language'] === 'en';
        }), null)
        ->andReturn($expected);

    $result = $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['language' => 'en']))
        ->generate('Hello world');

    expect($result)->toBe($expected);
});

test('whenProvider applies callback on transcribe when provider matches', function () {
    config(['atlas.speech.provider' => 'openai']);

    $expected = ['text' => 'Hello world', 'language' => 'en', 'duration' => 1.5];

    $this->speechService
        ->shouldReceive('transcribe')
        ->once()
        ->with('/path/to/audio.mp3', Mockery::on(function ($options) {
            return isset($options['provider_options']) && $options['provider_options']['language'] === 'en';
        }), null)
        ->andReturn($expected);

    $result = $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['language' => 'en']))
        ->transcribe('/path/to/audio.mp3');

    expect($result)->toBe($expected);
});

test('whenProvider does not apply callback when provider does not match', function () {
    config(['atlas.speech.provider' => 'anthropic']);

    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return ! isset($options['provider_options']) || ! isset($options['provider_options']['language']);
        }), null)
        ->andReturn($expected);

    $result = $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['language' => 'en']))
        ->generate('Hello world');

    expect($result)->toBe($expected);
});

test('whenProvider uses provider override for matching', function () {
    config(['atlas.speech.provider' => 'anthropic']);

    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return $options['provider'] === 'openai'
                && isset($options['provider_options'])
                && $options['provider_options']['language'] === 'en';
        }), null)
        ->andReturn($expected);

    $result = $this->request
        ->withProvider('openai')
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['language' => 'en']))
        ->generate('Hello world');

    expect($result)->toBe($expected);
});

test('whenProvider chains multiple provider configs', function () {
    config(['atlas.speech.provider' => 'openai']);

    $expected = ['audio' => 'audio_data', 'format' => 'mp3'];

    $this->speechService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello world', Mockery::on(function ($options) {
            return isset($options['provider_options'])
                && $options['provider_options']['language'] === 'en'
                && ! isset($options['provider_options']['cacheType']);
        }), null)
        ->andReturn($expected);

    $result = $this->request
        ->whenProvider('openai', fn ($r) => $r->withProviderOptions(['language' => 'en']))
        ->whenProvider('anthropic', fn ($r) => $r->withProviderOptions(['cacheType' => 'ephemeral']))
        ->generate('Hello world');

    expect($result)->toBe($expected);
});
