<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Providers\Concerns\ResolvesAudioFile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->resolver = new class
    {
        use ResolvesAudioFile {
            resolveAudioFile as public;
        }
    };
});

it('resolves audio from local file path', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_audio_');
    file_put_contents($tmpFile, 'fake-audio-bytes');

    $input = Audio::fromPath($tmpFile);

    $result = $this->resolver->resolveAudioFile($input);

    expect($result)->toBe('fake-audio-bytes');

    unlink($tmpFile);
});

it('throws when local file path cannot be read', function () {
    $input = Audio::fromPath('/nonexistent/audio.wav');

    $this->resolver->resolveAudioFile($input);
})->throws(ErrorException::class);

it('resolves audio from base64 data', function () {
    $original = 'fake-audio-data-bytes';
    $encoded = base64_encode($original);

    $input = Audio::fromBase64($encoded, 'audio/wav');

    $result = $this->resolver->resolveAudioFile($input);

    expect($result)->toBe($original);
});

it('throws on invalid base64 encoding', function () {
    // Create an Input with isBase64() true but invalid data
    $input = new class extends Input
    {
        public function mimeType(): string
        {
            return 'audio/wav';
        }

        public function isBase64(): bool
        {
            return true;
        }

        public function data(): string
        {
            return '!!!invalid-base64!!!';
        }

        protected function defaultExtension(): string
        {
            return 'wav';
        }
    };

    $this->resolver->resolveAudioFile($input);
})->throws(InvalidArgumentException::class, 'Cannot decode base64 audio data');

it('resolves audio from URL using HTTP client', function () {
    Http::fake([
        'https://example.com/audio.wav' => Http::response('fake-audio-from-url'),
    ]);

    $input = Audio::fromUrl('https://example.com/audio.wav');

    $result = $this->resolver->resolveAudioFile($input);

    expect($result)->toBe('fake-audio-from-url');
    Http::assertSentCount(1);
});

it('throws when URL fetch fails', function () {
    Http::fake([
        'https://example.com/missing.wav' => Http::response('Not Found', 404),
    ]);

    $input = Audio::fromUrl('https://example.com/missing.wav');

    $this->resolver->resolveAudioFile($input);
})->throws(RequestException::class);

it('throws when no supported source is set', function () {
    $input = new class extends Input
    {
        public function mimeType(): string
        {
            return 'audio/wav';
        }

        protected function defaultExtension(): string
        {
            return 'wav';
        }
    };

    $this->resolver->resolveAudioFile($input);
})->throws(InvalidArgumentException::class, 'Cannot resolve audio input');
