<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeTextReq(string $model = 'model'): TextRequest
{
    return new TextRequest($model, null, null, [], [], null, null, null, [], [], []);
}

function makeHandlerTestAudioReq(string $model = 'model'): AudioRequest
{
    return new AudioRequest($model, null, [], null, null, null, null, null, null);
}

function makeTestConfig(): ProviderConfig
{
    return new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');
}

function makeBareDriver(): Driver
{
    return new class(makeTestConfig(), Mockery::mock(HttpClient::class)) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities;
        }

        public function name(): string
        {
            return 'bare';
        }
    };
}

// ─── Concrete driver with built-in text handler ─────────────────────────────

function createConcreteDriverWithText(): Driver
{
    return new class(makeTestConfig(), Mockery::mock(HttpClient::class)) extends Driver
    {
        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true, stream: true);
        }

        public function name(): string
        {
            return 'concrete-test';
        }

        protected function textHandler(): TextHandler
        {
            return new class implements TextHandler
            {
                public function text(TextRequest $request): TextResponse
                {
                    return new TextResponse(
                        text: 'built-in response',
                        usage: new Usage(10, 5),
                        finishReason: FinishReason::Stop,
                    );
                }

                public function stream(TextRequest $request): StreamResponse
                {
                    return new StreamResponse((function () {
                        yield new StreamChunk(type: ChunkType::Text, text: 'built-in chunk');
                        yield new StreamChunk(type: ChunkType::Done, finishReason: FinishReason::Stop, usage: new Usage(10, 5));
                    })());
                }

                public function structured(TextRequest $request): StructuredResponse
                {
                    throw new RuntimeException('Not implemented');
                }
            };
        }
    };
}

// ─── withHandler on concrete driver ─────────────────────────────────────────

it('overrides built-in text handler on a concrete driver', function () {
    $overrideResponse = new TextResponse(
        text: 'override response',
        usage: new Usage(20, 10),
        finishReason: FinishReason::Stop,
    );

    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('text')->once()->andReturn($overrideResponse);

    $driver = createConcreteDriverWithText()->withHandler('text', $handler);
    $response = $driver->text(makeTextReq());

    expect($response->text)->toBe('override response');
    expect($response->usage->inputTokens)->toBe(20);
});

// ─── Handler adds previously unsupported modality ───────────────────────────

it('adds audio handler to a text-only driver', function () {
    $audioHandler = Mockery::mock(AudioHandler::class);
    $audioHandler->shouldReceive('audio')->once()->andReturn(
        new AudioResponse(data: base64_encode('test audio'), format: 'mp3')
    );
    $audioHandler->shouldReceive('audioToText')->once()->andReturn(
        new TextResponse(text: 'transcribed text', usage: new Usage(5, 3), finishReason: FinishReason::Stop)
    );

    $driver = createConcreteDriverWithText()->withHandler('audio', $audioHandler);

    $audioResponse = $driver->audio(makeHandlerTestAudioReq());
    expect($audioResponse->format)->toBe('mp3');

    $textResponse = $driver->audioToText(makeHandlerTestAudioReq());
    expect($textResponse->text)->toBe('transcribed text');
});

// ─── Custom streaming handler ───────────────────────────────────────────────

it('custom streaming handler yields chunks correctly through StreamResponse', function () {
    $streamResponse = new StreamResponse((function () {
        yield new StreamChunk(type: ChunkType::Text, text: 'Hello');
        yield new StreamChunk(type: ChunkType::Text, text: ' world');
        yield new StreamChunk(
            type: ChunkType::Done,
            finishReason: FinishReason::Stop,
            usage: new Usage(15, 8),
        );
    })());

    $handler = Mockery::mock(TextHandler::class);
    $handler->shouldReceive('stream')->once()->andReturn($streamResponse);

    $driver = createConcreteDriverWithText()->withHandler('text', $handler);
    $response = $driver->stream(makeTextReq());

    $chunks = [];
    foreach ($response as $chunk) {
        $chunks[] = $chunk;
    }

    expect($chunks)->toHaveCount(3);
    expect($chunks[0]->text)->toBe('Hello');
    expect($chunks[1]->text)->toBe(' world');
    expect($chunks[2]->type)->toBe(ChunkType::Done);
    expect($chunks[2]->finishReason)->toBe(FinishReason::Stop);
});

// ─── Custom handler returns correct response types ──────────────────────────

it('custom handler returns correct response types for each modality', function () {
    $textHandler = Mockery::mock(TextHandler::class);
    $textHandler->shouldReceive('text')->once()->andReturn(
        new TextResponse(text: 'hello', usage: new Usage(1, 1), finishReason: FinishReason::Stop)
    );

    $audioHandler = Mockery::mock(AudioHandler::class);
    $audioHandler->shouldReceive('audio')->once()->andReturn(
        new AudioResponse(data: 'audio-data', format: 'wav')
    );

    $customDriver = makeBareDriver()
        ->withHandler('text', $textHandler)
        ->withHandler('audio', $audioHandler);

    $textResponse = $customDriver->text(makeTextReq());
    expect($textResponse)->toBeInstanceOf(TextResponse::class);

    $audioResponse = $customDriver->audio(makeHandlerTestAudioReq());
    expect($audioResponse)->toBeInstanceOf(AudioResponse::class);
});
