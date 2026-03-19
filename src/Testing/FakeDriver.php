<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;

/**
 * A fake driver that records requests and returns responses from a sequence.
 *
 * Used by AtlasFake to intercept all provider calls during testing.
 * Each provider gets its own FakeDriver with its own copy of the response sequence.
 */
class FakeDriver extends Driver
{
    /** @var array<int, TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake> */
    private array $responses;

    private int $responseIndex = 0;

    /** @var array<int, RecordedRequest> */
    private array $recorded = [];

    /**
     * @param  array<int, TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake>  $responses
     */
    public function __construct(
        private readonly string $providerName,
        array $responses = [],
    ) {
        // Skip parent constructor — no ProviderConfig/HttpClient needed
        $this->responses = $responses;
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            stream: true,
            structured: true,
            image: true,
            imageToText: true,
            audio: true,
            audioToText: true,
            video: true,
            videoToText: true,
            embed: true,
            moderate: true,
            vision: true,
            toolCalling: true,
            providerTools: true,
        );
    }

    public function text(TextRequest $request): TextResponse
    {
        $this->record('text', $request);

        return $this->nextResponseFor('text')->toResponse();
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $this->record('stream', $request);

        $fake = $this->nextResponseFor('stream');

        if ($fake instanceof TextResponseFake) {
            $fake = StreamResponseFake::make()->withText($fake->toResponse()->text);
        }

        return $fake->toResponse();
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        $this->record('structured', $request);

        return $this->nextResponseFor('structured')->toResponse();
    }

    public function image(ImageRequest $request): ImageResponse
    {
        $this->record('image', $request);

        return $this->nextResponseFor('image')->toResponse();
    }

    public function imageToText(ImageRequest $request): TextResponse
    {
        $this->record('imageToText', $request);

        return $this->nextResponseFor('imageToText')->toResponse();
    }

    public function audio(AudioRequest $request): AudioResponse
    {
        $this->record('audio', $request);

        return $this->nextResponseFor('audio')->toResponse();
    }

    public function audioToText(AudioRequest $request): TextResponse
    {
        $this->record('audioToText', $request);

        return $this->nextResponseFor('audioToText')->toResponse();
    }

    public function video(VideoRequest $request): VideoResponse
    {
        $this->record('video', $request);

        return $this->nextResponseFor('video')->toResponse();
    }

    public function videoToText(VideoRequest $request): TextResponse
    {
        $this->record('videoToText', $request);

        return $this->nextResponseFor('videoToText')->toResponse();
    }

    public function embed(EmbedRequest $request): EmbeddingsResponse
    {
        $this->record('embed', $request);

        return $this->nextResponseFor('embed')->toResponse();
    }

    public function moderate(ModerateRequest $request): ModerationResponse
    {
        $this->record('moderate', $request);

        return $this->nextResponseFor('moderate')->toResponse();
    }

    /**
     * @return array<int, RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    private function record(string $method, TextRequest|ImageRequest|AudioRequest|VideoRequest|EmbedRequest|ModerateRequest $request): void
    {
        $this->recorded[] = new RecordedRequest(
            method: $method,
            provider: $this->providerName,
            model: $request->model,
            request: $request,
        );
    }

    private function nextResponseFor(string $method): TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake
    {
        if ($this->responses === []) {
            return $this->defaultFakeFor($method);
        }

        if ($this->responseIndex < count($this->responses)) {
            return $this->responses[$this->responseIndex++];
        }

        // Repeat last response when sequence is exhausted
        return $this->responses[count($this->responses) - 1];
    }

    private function defaultFakeFor(string $method): TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake
    {
        return match ($method) {
            'text', 'imageToText', 'audioToText', 'videoToText' => TextResponseFake::make(),
            'stream' => StreamResponseFake::make(),
            'structured' => StructuredResponseFake::make(),
            'image' => ImageResponseFake::make(),
            'audio' => AudioResponseFake::make(),
            'video' => VideoResponseFake::make(),
            'embed' => EmbeddingsResponseFake::make(),
            'moderate' => ModerationResponseFake::make(),
            default => TextResponseFake::make(),
        };
    }
}
