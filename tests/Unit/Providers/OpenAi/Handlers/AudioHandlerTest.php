<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Input\Audio as AudioInput;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Audio;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\RequestConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Illuminate\Support\Facades\Http;

function makeAudioHandler(?HttpClient $http = null): Audio
{
    return new Audio(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: $http ?? app(HttpClient::class),
    );
}

it('forwards the request config to postRaw (TTS) and postMultipart (transcription)', function () {
    $config = (new RequestConfig(30, 5, 2))->withoutRetry();

    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('postRaw')->once()
        ->withArgs(fn (string $url, array $headers, array $body, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn('audio-bytes');
    $http->shouldReceive('postMultipart')->once()
        ->withArgs(fn (string $url, array $headers, array $data, array $attachments, int $timeout, ?RequestConfig $cfg) => $cfg === $config)
        ->andReturn(['text' => 'transcribed']);

    $handler = makeAudioHandler($http);

    $handler->audio(new AudioRequest(
        model: 'tts-1', instructions: 'Hi', media: [], voice: 'nova', speed: null,
        language: null, duration: null, format: 'mp3', voiceClone: null, requestConfig: $config,
    ));

    $handler->audioToText(new AudioRequest(
        model: 'whisper-1', instructions: null, media: [AudioInput::fromBase64('YXVkaW8=', 'audio/mpeg')],
        voice: null, speed: null, language: null, duration: null, format: 'mp3', voiceClone: null,
        requestConfig: $config,
    ));
});

it('sends TTS request to /v1/audio/speech', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-audio-binary', 200),
    ]);

    $request = new AudioRequest(
        model: 'tts-1',
        instructions: 'Hello world',
        media: [],
        voice: 'nova',
        speed: null,
        language: null,
        duration: null,
        format: 'mp3',
        voiceClone: null,
    );

    $handler = makeAudioHandler();
    $response = $handler->audio($request);

    expect($response)->toBeInstanceOf(AudioResponse::class);
    expect($response->data)->toBe(base64_encode('fake-audio-binary'));
    expect($response->format)->toBe('mp3');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/audio/speech'
            && $request['model'] === 'tts-1'
            && $request['voice'] === 'nova';
    });
});

it('sends STT request to /v1/audio/transcriptions', function () {
    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'Transcribed speech content',
        ]),
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'atlas_audio_');
    file_put_contents($tempFile, 'fake-audio-data');

    $request = new AudioRequest(
        model: 'whisper-1',
        instructions: null,
        media: [AudioInput::fromPath($tempFile)],
        voice: null,
        speed: null,
        language: 'en',
        duration: null,
        format: null,
        voiceClone: null,
    );

    $handler = makeAudioHandler();
    $response = $handler->audioToText($request);

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Transcribed speech content');

    unlink($tempFile);
});
