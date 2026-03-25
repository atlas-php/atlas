<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Responses\VideoResponse;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Atlasphp\Atlas\Testing\ModerationResponseFake;
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\StructuredResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Testing\VideoResponseFake;

// ─── TextResponseFake ────────────────────────────────────────────────────────

it('creates TextResponse with defaults', function () {
    $response = TextResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('');
    expect($response->usage->inputTokens)->toBe(10);
    expect($response->usage->outputTokens)->toBe(20);
    expect($response->finishReason)->toBe(FinishReason::Stop);
    expect($response->toolCalls)->toBe([]);
    expect($response->reasoning)->toBeNull();
    expect($response->meta)->toBe([]);
});

it('overrides text on TextResponseFake', function () {
    $response = TextResponseFake::make()->withText('hello')->toResponse();

    expect($response->text)->toBe('hello');
});

it('auto-sets finishReason to ToolCalls when withToolCalls is called', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['query' => 'test']);
    $response = TextResponseFake::make()->withToolCalls([$toolCall])->toResponse();

    expect($response->finishReason)->toBe(FinishReason::ToolCalls);
    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]->name)->toBe('search');
});

it('overrides usage on TextResponseFake', function () {
    $usage = new Usage(100, 200);
    $response = TextResponseFake::make()->withUsage($usage)->toResponse();

    expect($response->usage->inputTokens)->toBe(100);
    expect($response->usage->outputTokens)->toBe(200);
});

it('overrides finishReason on TextResponseFake', function () {
    $response = TextResponseFake::make()->withFinishReason(FinishReason::Length)->toResponse();

    expect($response->finishReason)->toBe(FinishReason::Length);
});

it('overrides reasoning on TextResponseFake', function () {
    $response = TextResponseFake::make()->withReasoning('thinking...')->toResponse();

    expect($response->reasoning)->toBe('thinking...');
});

it('overrides meta on TextResponseFake', function () {
    $response = TextResponseFake::make()->withMeta(['key' => 'value'])->toResponse();

    expect($response->meta)->toBe(['key' => 'value']);
});

it('overrides providerToolCalls on TextResponseFake', function () {
    $providerToolCalls = [
        ['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'completed'],
    ];
    $response = TextResponseFake::make()->withProviderToolCalls($providerToolCalls)->toResponse();

    expect($response->providerToolCalls)->toHaveCount(1);
    expect($response->providerToolCalls[0]['type'])->toBe('web_search_call');
});

it('overrides annotations on TextResponseFake', function () {
    $annotations = [
        ['type' => 'url_citation', 'url' => 'https://example.com', 'title' => 'Example'],
    ];
    $response = TextResponseFake::make()->withAnnotations($annotations)->toResponse();

    expect($response->annotations)->toHaveCount(1);
    expect($response->annotations[0]['url'])->toBe('https://example.com');
});

it('defaults providerToolCalls and annotations to empty on TextResponseFake', function () {
    $response = TextResponseFake::make()->toResponse();

    expect($response->providerToolCalls)->toBe([]);
    expect($response->annotations)->toBe([]);
});

// ─── StreamResponseFake ──────────────────────────────────────────────────────

it('creates StreamResponse with text chunks', function () {
    $response = StreamResponseFake::make()->withText('Hello world')->toResponse();

    expect($response)->toBeInstanceOf(StreamResponse::class);

    $chunks = iterator_to_array($response);
    $textChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Text);
    $doneChunks = array_filter($chunks, fn ($c) => $c->type === ChunkType::Done);

    expect($textChunks)->not->toBeEmpty();
    expect($doneChunks)->toHaveCount(1);
    expect($response->getText())->toBe('Hello world');
});

it('chunks text at configurable size', function () {
    $response = StreamResponseFake::make()->withChunkSize(3)->withText('abcdef')->toResponse();

    $chunks = iterator_to_array($response);
    $textChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Text));

    expect($textChunks)->toHaveCount(2);
    expect($textChunks[0]->text)->toBe('abc');
    expect($textChunks[1]->text)->toBe('def');
});

it('yields only Done chunk for empty text', function () {
    $response = StreamResponseFake::make()->toResponse();

    $chunks = iterator_to_array($response);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->type)->toBe(ChunkType::Done);
});

// ─── StructuredResponseFake ──────────────────────────────────────────────────

it('creates StructuredResponse with defaults', function () {
    $response = StructuredResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(StructuredResponse::class);
    expect($response->structured)->toBe([]);
    expect($response->usage->inputTokens)->toBe(10);
    expect($response->finishReason)->toBe(FinishReason::Stop);
});

it('overrides structured data', function () {
    $response = StructuredResponseFake::make()->withStructured(['name' => 'John'])->toResponse();

    expect($response->structured)->toBe(['name' => 'John']);
});

// ─── ImageResponseFake ───────────────────────────────────────────────────────

it('creates ImageResponse with defaults', function () {
    $response = ImageResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(ImageResponse::class);
    expect($response->url)->toBe('https://fake.atlas/image.png');
    expect($response->revisedPrompt)->toBeNull();
    expect($response->meta)->toBe([]);
});

it('overrides url on ImageResponseFake', function () {
    $response = ImageResponseFake::make()->withUrl('https://example.com/img.jpg')->toResponse();

    expect($response->url)->toBe('https://example.com/img.jpg');
});

it('overrides revisedPrompt on ImageResponseFake', function () {
    $response = ImageResponseFake::make()->withRevisedPrompt('revised prompt')->toResponse();

    expect($response->revisedPrompt)->toBe('revised prompt');
});

it('overrides meta on ImageResponseFake', function () {
    $response = ImageResponseFake::make()->withMeta(['size' => '1024x1024'])->toResponse();

    expect($response->meta)->toBe(['size' => '1024x1024']);
});

// ─── AudioResponseFake ───────────────────────────────────────────────────────

it('creates AudioResponse with defaults', function () {
    $response = AudioResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(AudioResponse::class);
    expect($response->data)->toBe(base64_encode('fake-audio'));
    expect($response->format)->toBe('mp3');
    expect($response->meta)->toBe([]);
});

it('overrides data on AudioResponseFake', function () {
    $response = AudioResponseFake::make()->withData('custom-data')->toResponse();

    expect($response->data)->toBe('custom-data');
});

it('overrides format on AudioResponseFake', function () {
    $response = AudioResponseFake::make()->withFormat('wav')->toResponse();

    expect($response->format)->toBe('wav');
});

it('overrides meta on AudioResponseFake', function () {
    $response = AudioResponseFake::make()->withMeta(['duration' => 30])->toResponse();

    expect($response->meta)->toBe(['duration' => 30]);
});

// ─── VideoResponseFake ───────────────────────────────────────────────────────

it('creates VideoResponse with defaults', function () {
    $response = VideoResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(VideoResponse::class);
    expect($response->url)->toBe('https://fake.atlas/video.mp4');
    expect($response->duration)->toBeNull();
    expect($response->meta)->toBe([]);
});

it('overrides url on VideoResponseFake', function () {
    $response = VideoResponseFake::make()->withUrl('https://example.com/vid.mp4')->toResponse();

    expect($response->url)->toBe('https://example.com/vid.mp4');
});

it('overrides duration on VideoResponseFake', function () {
    $response = VideoResponseFake::make()->withDuration(60)->toResponse();

    expect($response->duration)->toBe(60);
});

it('overrides meta on VideoResponseFake', function () {
    $response = VideoResponseFake::make()->withMeta(['codec' => 'h264'])->toResponse();

    expect($response->meta)->toBe(['codec' => 'h264']);
});

// ─── EmbeddingsResponseFake ──────────────────────────────────────────────────

it('creates EmbeddingsResponse with defaults', function () {
    $response = EmbeddingsResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(EmbeddingsResponse::class);
    expect($response->embeddings)->toBe([[0.1, 0.2, 0.3]]);
    expect($response->usage->inputTokens)->toBe(5);
    expect($response->usage->outputTokens)->toBe(0);
});

it('overrides embeddings', function () {
    $embeddings = [[0.5, 0.6], [0.7, 0.8]];
    $response = EmbeddingsResponseFake::make()->withEmbeddings($embeddings)->toResponse();

    expect($response->embeddings)->toBe($embeddings);
});

it('overrides usage on EmbeddingsResponseFake', function () {
    $usage = new Usage(50, 10);
    $response = EmbeddingsResponseFake::make()->withUsage($usage)->toResponse();

    expect($response->usage->inputTokens)->toBe(50);
    expect($response->usage->outputTokens)->toBe(10);
});

// ─── ModerationResponseFake ──────────────────────────────────────────────────

it('creates ModerationResponse with defaults', function () {
    $response = ModerationResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(ModerationResponse::class);
    expect($response->flagged)->toBeFalse();
    expect($response->categories)->toBe([]);
    expect($response->meta)->toBe([]);
});

it('overrides flagged on ModerationResponseFake', function () {
    $response = ModerationResponseFake::make()->withFlagged(true)->toResponse();

    expect($response->flagged)->toBeTrue();
});

it('overrides categories on ModerationResponseFake', function () {
    $response = ModerationResponseFake::make()->withCategories(['hate' => true])->toResponse();

    expect($response->categories)->toBe(['hate' => true]);
});

it('overrides meta on ModerationResponseFake', function () {
    $response = ModerationResponseFake::make()->withMeta(['source' => 'openai'])->toResponse();

    expect($response->meta)->toBe(['source' => 'openai']);
});

// ─── RerankResponseFake ────────────────────────────────────────────────────

it('creates RerankResponse with defaults', function () {
    $response = RerankResponseFake::make()->toResponse();

    expect($response)->toBeInstanceOf(RerankResponse::class);
    expect($response->results)->toHaveCount(3);
    expect($response->results[0])->toBeInstanceOf(RerankResult::class);
    expect($response->results[0]->index)->toBe(0);
    expect($response->results[0]->score)->toBe(0.95);
    expect($response->results[0]->document)->toBe('Document 1');
    expect($response->meta)->toBe([]);
});

it('creates RerankResponse with specific count', function () {
    $response = RerankResponseFake::withCount(5)->toResponse();

    expect($response->results)->toHaveCount(5);
    expect($response->results[0]->score)->toBe(1.0);
    expect($response->results[4]->score)->toBe(0.6);
});

it('creates RerankResponse with custom scores', function () {
    $response = RerankResponseFake::withCount(3, [0.9, 0.7, 0.5])->toResponse();

    expect($response->results[0]->score)->toBe(0.9);
    expect($response->results[1]->score)->toBe(0.7);
    expect($response->results[2]->score)->toBe(0.5);
});

it('overrides results on RerankResponseFake', function () {
    $results = [
        new RerankResult(0, 0.99, 'Top doc'),
    ];
    $response = RerankResponseFake::make()->withResults($results)->toResponse();

    expect($response->results)->toHaveCount(1);
    expect($response->results[0]->document)->toBe('Top doc');
});

it('overrides meta on RerankResponseFake', function () {
    $response = RerankResponseFake::make()->withMeta(['provider' => 'cohere'])->toResponse();

    expect($response->meta)->toBe(['provider' => 'cohere']);
});

// ─── StreamResponseFake (additional methods) ────────────────────────────────

it('overrides usage on StreamResponseFake', function () {
    $usage = new Usage(100, 200);
    $response = StreamResponseFake::make()->withText('test')->withUsage($usage)->toResponse();

    $chunks = iterator_to_array($response);
    $doneChunk = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Done));

    expect($doneChunk[0]->usage->inputTokens)->toBe(100);
    expect($doneChunk[0]->usage->outputTokens)->toBe(200);
});

it('overrides finishReason on StreamResponseFake', function () {
    $response = StreamResponseFake::make()->withText('test')->withFinishReason(FinishReason::Length)->toResponse();

    $chunks = iterator_to_array($response);
    $doneChunk = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Done));

    expect($doneChunk[0]->finishReason)->toBe(FinishReason::Length);
});

it('generates thinking chunks on StreamResponseFake', function () {
    $response = StreamResponseFake::make()
        ->withThinking('Let me think...')
        ->withText('Answer')
        ->toResponse();

    $chunks = iterator_to_array($response);
    $thinkingChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Thinking));
    $textChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Text && $c->text !== null));

    expect($thinkingChunks)->toHaveCount(1);
    expect($thinkingChunks[0]->reasoning)->toBe('Let me think...');
    expect($textChunks)->not->toBeEmpty();
});

it('generates tool call chunks on StreamResponseFake', function () {
    $toolCalls = [
        new ToolCall('tc-1', 'search', ['q' => 'test']),
        new ToolCall('tc-2', 'calc', ['x' => 42]),
    ];

    $response = StreamResponseFake::make()
        ->withToolCalls($toolCalls)
        ->withText('Result')
        ->toResponse();

    $chunks = iterator_to_array($response);
    $toolCallChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::ToolCall));

    expect($toolCallChunks)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls)->toHaveCount(2);
    expect($toolCallChunks[0]->toolCalls[0]->name)->toBe('search');
    expect($toolCallChunks[0]->toolCalls[1]->name)->toBe('calc');
});

it('emits chunks in order: thinking → tool calls → text → done', function () {
    $response = StreamResponseFake::make()
        ->withThinking('Reasoning...')
        ->withToolCalls([new ToolCall('tc-1', 'search', ['q' => 'test'])])
        ->withText('Answer')
        ->toResponse();

    $chunks = iterator_to_array($response);
    $types = array_map(fn ($c) => $c->type, $chunks);

    // Filter out null-text Text chunks and verify order
    expect($types[0])->toBe(ChunkType::Thinking);
    expect($types[1])->toBe(ChunkType::ToolCall);
    expect($types[2])->toBe(ChunkType::Text);
    expect(end($types))->toBe(ChunkType::Done);
});

// ─── StructuredResponseFake (additional methods) ────────────────────────────

it('overrides usage on StructuredResponseFake', function () {
    $usage = new Usage(100, 200);
    $response = StructuredResponseFake::make()->withUsage($usage)->toResponse();

    expect($response->usage->inputTokens)->toBe(100);
    expect($response->usage->outputTokens)->toBe(200);
});

it('overrides finishReason on StructuredResponseFake', function () {
    $response = StructuredResponseFake::make()->withFinishReason(FinishReason::Length)->toResponse();

    expect($response->finishReason)->toBe(FinishReason::Length);
});
