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

test('it generates speech with defaults', function () {
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

    $result = $this->service->generate('Hello world');

    expect($result)->toBe([
        'audio' => 'decoded-audio-data',
        'format' => 'mp3',
    ]);
});

test('it generates with provider override', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('anthropic', 'tts-1', 'Hello', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('Hello', ['provider' => 'anthropic']);

    expect($result)->toHaveKey('audio');
});

test('it generates with model override', function () {
    $mockRequest = Mockery::mock();

    $mockAudio = new stdClass;
    $mockAudio->base64 = base64_encode('audio-data');

    $mockResponse = new stdClass;
    $mockResponse->audio = $mockAudio;

    $this->prismBuilder
        ->shouldReceive('forSpeech')
        ->with('openai', 'tts-1-hd', 'Hello', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->generate('Hello', ['model' => 'tts-1-hd']);

    expect($result)->toHaveKey('audio');
});

test('it generates with custom voice', function () {
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

    $result = $this->service->generate('Hello', ['voice' => 'nova']);

    expect($result)->toHaveKey('audio');
});

test('it generates with custom speed', function () {
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

    $result = $this->service->generate('Hello', ['speed' => 1.5]);

    expect($result)->toHaveKey('audio');
});

test('it generates with custom format', function () {
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

    $result = $this->service->generate('Hello', ['format' => 'wav']);

    expect($result['format'])->toBe('wav');
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
            return isset($options['language']) && $options['language'] === 'en';
        }), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asAudio')
        ->once()
        ->andReturn($mockResponse);

    $this->service->generate('Hello', ['provider_options' => ['language' => 'en']]);
});

test('it runs speech.before_generate pipeline', function () {
    $this->registry->define('speech.before_generate', 'Before speak pipeline');
    SpeechBeforeGenerateHandler::reset();
    $this->registry->register('speech.before_generate', SpeechBeforeGenerateHandler::class);

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

    $this->service->generate('Hello world');

    expect(SpeechBeforeGenerateHandler::$called)->toBeTrue();
    expect(SpeechBeforeGenerateHandler::$data)->not->toBeNull();
    expect(SpeechBeforeGenerateHandler::$data['text'])->toBe('Hello world');
    expect(SpeechBeforeGenerateHandler::$data['provider'])->toBe('openai');
    expect(SpeechBeforeGenerateHandler::$data['model'])->toBe('tts-1');
    expect(SpeechBeforeGenerateHandler::$data['format'])->toBe('mp3');
});

test('it runs speech.after_generate pipeline', function () {
    $this->registry->define('speech.after_generate', 'After speak pipeline');
    SpeechAfterGenerateHandler::reset();
    $this->registry->register('speech.after_generate', SpeechAfterGenerateHandler::class);

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

    $this->service->generate('Hello world', ['voice' => 'nova', 'format' => 'wav']);

    expect(SpeechAfterGenerateHandler::$called)->toBeTrue();
    expect(SpeechAfterGenerateHandler::$data)->not->toBeNull();
    expect(SpeechAfterGenerateHandler::$data['text'])->toBe('Hello world');
    expect(SpeechAfterGenerateHandler::$data['provider'])->toBe('openai');
    expect(SpeechAfterGenerateHandler::$data['model'])->toBe('tts-1');
    expect(SpeechAfterGenerateHandler::$data['voice'])->toBe('nova');
    expect(SpeechAfterGenerateHandler::$data['format'])->toBe('wav');
    expect(SpeechAfterGenerateHandler::$data['result'])->toHaveKeys(['audio', 'format']);
});

test('it allows before_speak pipeline to modify text', function () {
    $this->registry->define('speech.before_generate', 'Before speak pipeline');
    $this->registry->register('speech.before_generate', SpeechTextModifyingHandler::class);

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

    $this->service->generate('Hello');
});

test('it runs speech.on_error pipeline when generate fails', function () {
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
        $this->service->generate('Hello', ['voice' => 'nova', 'format' => 'wav']);
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(SpeechErrorHandler::$called)->toBeTrue();
    expect(SpeechErrorHandler::$data)->not->toBeNull();
    expect(SpeechErrorHandler::$data['operation'])->toBe('generate');
    expect(SpeechErrorHandler::$data['text'])->toBe('Hello');
    expect(SpeechErrorHandler::$data['provider'])->toBe('openai');
    expect(SpeechErrorHandler::$data['model'])->toBe('tts-1');
    expect(SpeechErrorHandler::$data['voice'])->toBe('nova');
    expect(SpeechErrorHandler::$data['format'])->toBe('wav');
    expect(SpeechErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(SpeechErrorHandler::$data['exception']->getMessage())->toBe('Speech API Error');
});

test('it rethrows exception after running generate error pipeline', function () {
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

    expect(fn () => $this->service->generate('Hello'))
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
    $this->registry->define('speech.before_generate', 'Before speak pipeline');
    SpeechBeforeGenerateHandler::reset();
    $this->registry->register('speech.before_generate', SpeechBeforeGenerateHandler::class);

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

    $this->service->generate('Hello', ['voice' => 'nova']);

    expect(SpeechBeforeGenerateHandler::$data['voice'])->toBe('nova');
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it passes retry to PrismBuilder for generate', function () {
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

    $this->service->generate('Hello', [], $retryConfig);
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
        ->with('openai', 'whisper-1', $mockAudio, Mockery::type('array'), $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->once()
        ->andReturn($mockResponse);

    $this->service->transcribe($mockAudio, [], $retryConfig);
});

test('it uses config retry when explicit retry is null', function () {
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

    $service->generate('Hello');
});

test('it passes retry with closure to PrismBuilder for generate', function () {
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

    $this->service->generate('Hello', [], [3, $sleepFn, null, true]);
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

    expect(fn () => $this->service->generate('Hello'))
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

    $result = $this->service->generate('Hello');

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
        ->with('openai', 'whisper-1', Mockery::type(\Prism\Prism\ValueObjects\Media\Audio::class), Mockery::type('array'), null)
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

test('it transcribes with transcription_model override', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = new stdClass;
    $mockResponse->text = 'Transcribed text';
    $mockResponse->language = 'en';
    $mockResponse->duration = 5.5;

    $mockAudio = Mockery::mock(\Prism\Prism\ValueObjects\Media\Audio::class);

    $this->prismBuilder
        ->shouldReceive('forTranscription')
        ->with('openai', 'whisper-2', $mockAudio, Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asText')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->transcribe($mockAudio, ['transcription_model' => 'whisper-2']);

    expect($result['text'])->toBe('Transcribed text');
});

// Pipeline Handler Classes for Tests

class SpeechBeforeGenerateHandler implements PipelineContract
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

class SpeechAfterGenerateHandler implements PipelineContract
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
