<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Google\ResponseParser;
use Atlasphp\Atlas\Providers\Google\ToolMapper;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeGoogleResponseParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

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
            ['functionCall' => ['name' => 'search', 'args' => ['q' => 'test']]],
        ]]]],
    ]);

    expect($result->type)->toBe(ChunkType::ToolCall);
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('search');
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
