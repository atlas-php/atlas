<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function () {
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
    $this->container = new Container;
    $this->registry = new PipelineRegistry;
    $this->pipelineRunner = new PipelineRunner($this->registry, $this->container);
    $this->service = new SpeechService($this->prismBuilder, $this->configService, $this->pipelineRunner);
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

test('it returns a new instance when setting speed', function () {
    $newService = $this->service->speed(1.5);

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it returns a new instance when setting provider options', function () {
    $newService = $this->service->withProviderOptions(['language' => 'en']);

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it speaks with custom speed', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::on(function ($options) {
            return isset($options['speed']) && $options['speed'] === 1.5;
        }), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service
        ->speed(1.5)
        ->speak('Hello');

    expect($result)->toHaveKey('audio');
});

test('it merges provider options', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::on(function ($options) {
            return isset($options['language']) && $options['language'] === 'en'
                && isset($options['timbre']) && $options['timbre'] === 'warm';
        }), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withProviderOptions(['language' => 'en'])
        ->withProviderOptions(['timbre' => 'warm'])
        ->speak('Hello');
});

test('it speaks text with defaults', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('decoded-audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello world', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->speak('Hello world');

    expect($result)->toBe([
        'audio' => 'decoded-audio-data',
        'format' => 'mp3',
    ]);
});

test('it speaks with custom voice', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::on(function ($options) {
            return isset($options['voice']) && $options['voice'] === 'nova';
        }), null)
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

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('anthropic', 'custom-tts', 'Hello', Mockery::type('array'), null)
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

test('it runs speech.before_speak pipeline', function () {
    $this->registry->define('speech.before_speak', 'Before speak pipeline');
    SpeechBeforeSpeakHandler::reset();
    $this->registry->register('speech.before_speak', SpeechBeforeSpeakHandler::class);

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andReturn($mockResponse);

    $this->service->speak('Hello world');

    expect(SpeechBeforeSpeakHandler::$called)->toBeTrue();
    expect(SpeechBeforeSpeakHandler::$data)->not->toBeNull();
    expect(SpeechBeforeSpeakHandler::$data['text'])->toBe('Hello world');
    expect(SpeechBeforeSpeakHandler::$data['provider'])->toBe('openai');
    expect(SpeechBeforeSpeakHandler::$data['model'])->toBe('tts-1');
    expect(SpeechBeforeSpeakHandler::$data['format'])->toBe('mp3');
});

test('it runs speech.after_speak pipeline', function () {
    $this->registry->define('speech.after_speak', 'After speak pipeline');
    SpeechAfterSpeakHandler::reset();
    $this->registry->register('speech.after_speak', SpeechAfterSpeakHandler::class);

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andReturn($mockResponse);

    $this->service
        ->voice('nova')
        ->format('wav')
        ->speak('Hello world');

    expect(SpeechAfterSpeakHandler::$called)->toBeTrue();
    expect(SpeechAfterSpeakHandler::$data)->not->toBeNull();
    expect(SpeechAfterSpeakHandler::$data['text'])->toBe('Hello world');
    expect(SpeechAfterSpeakHandler::$data['provider'])->toBe('openai');
    expect(SpeechAfterSpeakHandler::$data['model'])->toBe('tts-1');
    expect(SpeechAfterSpeakHandler::$data['voice'])->toBe('nova');
    expect(SpeechAfterSpeakHandler::$data['format'])->toBe('wav');
    expect(SpeechAfterSpeakHandler::$data['result'])->toHaveKeys(['audio', 'format']);
});

test('it allows before_speak pipeline to modify text', function () {
    $this->registry->define('speech.before_speak', 'Before speak pipeline');
    $this->registry->register('speech.before_speak', SpeechTextModifyingHandler::class);

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'MODIFIED: Hello', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andReturn($mockResponse);

    $this->service->speak('Hello');
});

test('it runs speech.on_error pipeline when speak fails', function () {
    $this->registry->define('speech.on_error', 'Error pipeline');
    SpeechErrorHandler::reset();
    $this->registry->register('speech.on_error', SpeechErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andThrow(new \RuntimeException('Speech API Error'));

    try {
        $this->service
            ->voice('nova')
            ->format('wav')
            ->speak('Hello');
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(SpeechErrorHandler::$called)->toBeTrue();
    expect(SpeechErrorHandler::$data)->not->toBeNull();
    expect(SpeechErrorHandler::$data['operation'])->toBe('speak');
    expect(SpeechErrorHandler::$data['text'])->toBe('Hello');
    expect(SpeechErrorHandler::$data['provider'])->toBe('openai');
    expect(SpeechErrorHandler::$data['model'])->toBe('tts-1');
    expect(SpeechErrorHandler::$data['voice'])->toBe('nova');
    expect(SpeechErrorHandler::$data['format'])->toBe('wav');
    expect(SpeechErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(SpeechErrorHandler::$data['exception']->getMessage())->toBe('Speech API Error');
});

test('it rethrows exception after running speak error pipeline', function () {
    $this->registry->define('speech.on_error', 'Error pipeline');
    SpeechErrorHandler::reset();
    $this->registry->register('speech.on_error', SpeechErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andThrow(new \RuntimeException('Speech API Error'));

    expect(fn () => $this->service->speak('Hello'))
        ->toThrow(\RuntimeException::class, 'Speech API Error');
});

test('it runs speech.before_transcribe pipeline', function () {
    $this->registry->define('speech.before_transcribe', 'Before transcribe pipeline');
    SpeechBeforeTranscribeHandler::reset();
    $this->registry->register('speech.before_transcribe', SpeechBeforeTranscribeHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->text = 'Transcribed text';
    $mockResponse->language = 'en';
    $mockResponse->duration = 5.5;

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->andReturn($mockResponse);

    // Use a mock Audio object to avoid file validation
    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);
    $this->service->transcribe($mockAudio);

    expect(SpeechBeforeTranscribeHandler::$called)->toBeTrue();
    expect(SpeechBeforeTranscribeHandler::$data)->not->toBeNull();
    expect(SpeechBeforeTranscribeHandler::$data['audio'])->toBe($mockAudio);
    expect(SpeechBeforeTranscribeHandler::$data['provider'])->toBe('openai');
    expect(SpeechBeforeTranscribeHandler::$data['model'])->toBe('whisper-1');
});

test('it runs speech.after_transcribe pipeline', function () {
    $this->registry->define('speech.after_transcribe', 'After transcribe pipeline');
    SpeechAfterTranscribeHandler::reset();
    $this->registry->register('speech.after_transcribe', SpeechAfterTranscribeHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->text = 'Transcribed text';
    $mockResponse->language = 'en';
    $mockResponse->duration = 5.5;

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->andReturn($mockResponse);

    // Use a mock Audio object to avoid file validation
    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);
    $this->service->transcribe($mockAudio, ['language' => 'en']);

    expect(SpeechAfterTranscribeHandler::$called)->toBeTrue();
    expect(SpeechAfterTranscribeHandler::$data)->not->toBeNull();
    expect(SpeechAfterTranscribeHandler::$data['audio'])->toBe($mockAudio);
    expect(SpeechAfterTranscribeHandler::$data['provider'])->toBe('openai');
    expect(SpeechAfterTranscribeHandler::$data['model'])->toBe('whisper-1');
    expect(SpeechAfterTranscribeHandler::$data['options'])->toBe(['language' => 'en']);
    expect(SpeechAfterTranscribeHandler::$data['result'])->toBe([
        'text' => 'Transcribed text',
        'language' => 'en',
        'duration' => 5.5,
    ]);
});

test('it runs speech.on_error pipeline when transcribe fails', function () {
    $this->registry->define('speech.on_error', 'Error pipeline');
    SpeechErrorHandler::reset();
    $this->registry->register('speech.on_error', SpeechErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->andThrow(new \RuntimeException('Transcription API Error'));

    // Use a mock Audio object to avoid file validation
    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);

    try {
        $this->service->transcribe($mockAudio, ['language' => 'en']);
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(SpeechErrorHandler::$called)->toBeTrue();
    expect(SpeechErrorHandler::$data)->not->toBeNull();
    expect(SpeechErrorHandler::$data['operation'])->toBe('transcribe');
    expect(SpeechErrorHandler::$data['audio'])->toBe($mockAudio);
    expect(SpeechErrorHandler::$data['provider'])->toBe('openai');
    expect(SpeechErrorHandler::$data['model'])->toBe('whisper-1');
    expect(SpeechErrorHandler::$data['options'])->toBe(['language' => 'en']);
    expect(SpeechErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(SpeechErrorHandler::$data['exception']->getMessage())->toBe('Transcription API Error');
});

test('it rethrows exception after running transcribe error pipeline', function () {
    $this->registry->define('speech.on_error', 'Error pipeline');
    SpeechErrorHandler::reset();
    $this->registry->register('speech.on_error', SpeechErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->andThrow(new \RuntimeException('Transcription API Error'));

    // Use a mock Audio object to avoid file validation
    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);

    expect(fn () => $this->service->transcribe($mockAudio))
        ->toThrow(\RuntimeException::class, 'Transcription API Error');
});

test('it includes voice in before_speak pipeline data', function () {
    $this->registry->define('speech.before_speak', 'Before speak pipeline');
    SpeechBeforeSpeakHandler::reset();
    $this->registry->register('speech.before_speak', SpeechBeforeSpeakHandler::class);

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andReturn($mockResponse);

    $this->service
        ->voice('nova')
        ->speak('Hello');

    expect(SpeechBeforeSpeakHandler::$data['voice'])->toBe('nova');
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it returns a new instance when setting retry', function () {
    $newService = $this->service->withRetry(3, 1000);

    expect($newService)->toBeInstanceOf(SpeechService::class);
    expect($newService)->not->toBe($this->service);
});

test('it passes retry to PrismBuilder for speak', function () {
    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $retryConfig = [3, 1000, null, true];

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::type('array'), $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry(3, 1000, null, true)
        ->speak('Hello');
});

test('it passes retry to PrismBuilder for transcribe', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->text = 'Transcribed text';
    $mockResponse->language = 'en';
    $mockResponse->duration = 5.5;

    $retryConfig = [3, 1000, null, true];

    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->with('openai', 'whisper-1', $mockAudio, [], $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry(3, 1000, null, true)
        ->transcribe($mockAudio);
});

test('it uses config retry when withRetry is not called', function () {
    // Create service with config that has retry enabled
    $configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'speech' => [
                'provider' => 'openai',
                'model' => 'tts-1',
                'transcription_model' => 'whisper-1',
            ],
            'retry' => [
                'enabled' => true,
                'times' => 2,
                'delay_ms' => 500,
            ],
        ],
    ]));

    $service = new SpeechService($this->prismBuilder, $configService, $this->pipelineRunner);

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::type('array'), Mockery::on(function ($retry) {
            return is_array($retry) && $retry[0] === 2 && $retry[1] === 500;
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $service->speak('Hello');
});

test('it passes retry with closure to PrismBuilder for speak', function () {
    $sleepFn = fn ($attempt) => $attempt * 100;

    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::type('array'), Mockery::on(function ($retry) use ($sleepFn) {
            return is_array($retry) && $retry[0] === 3 && $retry[1] === $sleepFn;
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry(3, $sleepFn)
        ->speak('Hello');
});

test('it passes retry with array of delays to PrismBuilder for speak', function () {
    $mockRequest = Mockery::mock();
    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::type('array'), Mockery::on(function ($retry) {
            return is_array($retry) && $retry[0] === [100, 200, 300];
        }))
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $this->service
        ->withRetry([100, 200, 300])
        ->speak('Hello');
});

test('it throws RuntimeException when base64 decoding fails', function () {
    $mockRequest = Mockery::mock();

    // Create audio object with invalid base64 data
    $mockAudio = new stdClass;
    $mockAudio->base64 = '!!!invalid-base64-data!!!';

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->andReturn($mockResponse);

    expect(fn () => $this->service->speak('Hello'))
        ->toThrow(\RuntimeException::class, 'Failed to decode audio base64 content: invalid base64 data');
});

test('it extracts audio content via content method when base64 not available', function () {
    $mockRequest = Mockery::mock();

    // Create audio object with content() method instead of base64
    $mockAudio = new class
    {
        public function content(): string
        {
            return 'audio-content-from-method';
        }
    };

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1', 'Hello', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->speak('Hello');

    expect($result['audio'])->toBe('audio-content-from-method');
    expect($result['format'])->toBe('mp3');
});

test('it transcribes audio from file path string', function () {
    // Create a temporary audio file for testing
    $tempFile = sys_get_temp_dir().'/test-audio-'.uniqid().'.mp3';
    file_put_contents($tempFile, 'fake audio content');

    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->text = 'Transcribed from file path';
    $mockResponse->language = 'en';
    $mockResponse->duration = 3.5;

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->with('openai', 'whisper-1', Mockery::type(\Prism\Prism\ValueObjects\Media\Audio::class), [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->transcribe($tempFile);

    // Clean up temp file
    unlink($tempFile);

    expect($result['text'])->toBe('Transcribed from file path');
    expect($result['language'])->toBe('en');
    expect($result['duration'])->toBe(3.5);
});

// Pipeline Handler Classes for Tests

class SpeechBeforeSpeakHandler implements PipelineContract
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

class SpeechAfterSpeakHandler implements PipelineContract
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

class SpeechTextModifyingHandler implements PipelineContract
{
    public function handle(mixed $data, \Closure $next): mixed
    {
        $data['text'] = 'MODIFIED: '.$data['text'];

        return $next($data);
    }
}

class SpeechBeforeTranscribeHandler implements PipelineContract
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

class SpeechAfterTranscribeHandler implements PipelineContract
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

class SpeechErrorHandler implements PipelineContract
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
