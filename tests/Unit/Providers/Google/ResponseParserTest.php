<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Google\GoogleToolCall;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeGoogleResponseParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

it('throws ProviderException on a mid-stream error payload', function () {
    makeGoogleResponseParser()->parseStreamChunk([
        'error' => ['code' => 429, 'message' => 'Resource exhausted', 'status' => 'RESOURCE_EXHAUSTED'],
    ]);
})->throws(ProviderException::class, 'Resource exhausted');

it('a mid-stream error carries the model passed to the parser', function () {
    $caught = null;

    try {
        makeGoogleResponseParser()->parseStreamChunk([
            'error' => ['code' => 429, 'message' => 'Resource exhausted'],
        ], 'gemini-2.5-flash');
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught?->model)->toBe('gemini-2.5-flash');
});

it('parses text from candidates parts', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseText([
        'candidates' => [
            ['content' => ['parts' => [['text' => 'Hello!']], 'role' => 'model'], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    expect($result)->toBeInstanceOf(TextResponse::class);
    expect($result->text)->toBe('Hello!');
});

it('parses function calls from candidates', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseText([
        'candidates' => [
            ['content' => ['parts' => [
                ['functionCall' => ['name' => 'search', 'args' => ['query' => 'test']]],
            ], 'role' => 'model'], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('search');
    expect($result->toolCalls[0]->arguments)->toBe(['query' => 'test']);
});

it('preserves function call thought signatures in assistant messages', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseText([
        'candidates' => [
            ['content' => ['parts' => [
                [
                    'functionCall' => ['name' => 'search', 'args' => ['query' => 'test']],
                    'thoughtSignature' => 'signature-from-gemini',
                ],
            ], 'role' => 'model'], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    $message = $result->toMessage();

    expect($message->toolCalls[0])->toBeInstanceOf(GoogleToolCall::class);
    expect($message->toolCalls[0]->thoughtSignature)->toBe('signature-from-gemini');
});

it('parses thinking parts as reasoning', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseText([
        'candidates' => [
            ['content' => ['parts' => [
                ['text' => 'Let me think...', 'thought' => true],
                ['text' => 'The answer is 42'],
            ], 'role' => 'model'], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    expect($result->reasoning)->toBe('Let me think...');
});

it('separates thinking from regular text', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseText([
        'candidates' => [
            ['content' => ['parts' => [
                ['text' => 'Internal reasoning', 'thought' => true],
                ['text' => 'Visible output'],
            ], 'role' => 'model'], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    expect($result->text)->toBe('Visible output');
    expect($result->reasoning)->toBe('Internal reasoning');
});

it('parses usage metadata', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseUsage([
        'usageMetadata' => [
            'promptTokenCount' => 100,
            'candidatesTokenCount' => 50,
            'thoughtsTokenCount' => 20,
            'cachedContentTokenCount' => 10,
        ],
    ]);

    expect($result)->toBeInstanceOf(Usage::class);
    expect($result->inputTokens)->toBe(100);
    expect($result->outputTokens)->toBe(50);
    expect($result->reasoningTokens)->toBe(20);
    expect($result->cachedTokens)->toBe(10);
});

it('maps STOP finish reason to Stop', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseFinishReason([
        'candidates' => [['content' => ['parts' => [['text' => 'ok']]], 'finishReason' => 'STOP']],
    ]);

    expect($result)->toBe(FinishReason::Stop);
});

it('maps MAX_TOKENS finish reason to Length', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseFinishReason([
        'candidates' => [['content' => ['parts' => [['text' => 'ok']]], 'finishReason' => 'MAX_TOKENS']],
    ]);

    expect($result)->toBe(FinishReason::Length);
});

it('maps SAFETY finish reason to ContentFilter', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseFinishReason([
        'candidates' => [['content' => ['parts' => [['text' => 'ok']]], 'finishReason' => 'SAFETY']],
    ]);

    expect($result)->toBe(FinishReason::ContentFilter);
});

it('maps functionCall parts to ToolCalls finish reason', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseFinishReason([
        'candidates' => [['content' => ['parts' => [
            ['functionCall' => ['name' => 'search', 'args' => []]],
        ]], 'finishReason' => 'STOP']],
    ]);

    expect($result)->toBe(FinishReason::ToolCalls);
});

it('parses stream chunk with text delta', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [['content' => ['parts' => [['text' => 'Hello']]]]],
    ]);

    expect($result)->toBeInstanceOf(StreamChunk::class);
    expect($result->type)->toBe(ChunkType::Text);
    expect($result->text)->toBe('Hello');
});

it('parses stream chunk with function call', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [['content' => ['parts' => [
            [
                'functionCall' => ['name' => 'search', 'args' => ['q' => 'test']],
                'thoughtSignature' => 'signature-from-gemini',
            ],
        ]]]],
    ]);

    expect($result->type)->toBe(ChunkType::ToolCall);
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0])->toBeInstanceOf(GoogleToolCall::class);
    expect($result->toolCalls[0]->name)->toBe('search');
    expect($result->toolCalls[0]->thoughtSignature)->toBe('signature-from-gemini');
});

it('carries finishReason on a streamed chunk that bundles a function call (regression)', function () {
    // Gemini bundles functionCall + finishReason + usageMetadata into one
    // terminal SSE chunk. The finishReason must survive so a tool-terminated
    // stream resolves to FinishReason::ToolCalls, not null.
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [[
            'content' => ['parts' => [
                ['functionCall' => ['name' => 'get_weather', 'args' => ['city' => 'Paris']]],
            ]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ]);

    expect($result->type)->toBe(ChunkType::ToolCall);
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->finishReason)->toBe(FinishReason::ToolCalls);
    expect($result->usage?->inputTokens)->toBe(10);
});

it('emits all function calls bundled in one streamed chunk (regression)', function () {
    // Parallel tool calls may arrive as multiple functionCall parts in a single
    // chunk; none may be dropped.
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [[
            'content' => ['parts' => [
                ['functionCall' => ['name' => 'get_weather', 'args' => ['city' => 'Paris']]],
                ['functionCall' => ['name' => 'get_weather', 'args' => ['city' => 'Tokyo']]],
            ]],
            'finishReason' => 'STOP',
        ]],
    ]);

    expect($result->type)->toBe(ChunkType::ToolCall);
    expect($result->toolCalls)->toHaveCount(2);
    expect($result->toolCalls[0]->arguments)->toBe(['city' => 'Paris']);
    expect($result->toolCalls[1]->arguments)->toBe(['city' => 'Tokyo']);
    expect($result->finishReason)->toBe(FinishReason::ToolCalls);
});

it('parses stream chunk with thinking', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [['content' => ['parts' => [
            ['text' => 'thinking...', 'thought' => true],
        ]]]],
    ]);

    expect($result->type)->toBe(ChunkType::Thinking);
    expect($result->reasoning)->toBe('thinking...');
});

it('parses stream chunk as done when finishReason present', function () {
    $parser = makeGoogleResponseParser();

    $result = $parser->parseStreamChunk([
        'candidates' => [['content' => ['parts' => []], 'finishReason' => 'STOP']],
    ]);

    expect($result->type)->toBe(ChunkType::Done);
});
