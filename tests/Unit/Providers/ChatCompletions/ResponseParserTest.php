<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\ChatCompletions\ResponseParser;
use Atlasphp\Atlas\Providers\ChatCompletions\ToolMapper;

function makeCcParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

it('parses text from choices message', function () {
    $parser = makeCcParser();

    $result = $parser->parseText([
        'id' => 'chatcmpl-123',
        'model' => 'llama3.2',
        'choices' => [['message' => ['content' => 'Hello!'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    expect($result->text)->toBe('Hello!');
    expect($result->meta['id'])->toBe('chatcmpl-123');
    expect($result->meta['model'])->toBe('llama3.2');
});

it('parses tool calls from choices message', function () {
    $parser = makeCcParser();

    $result = $parser->parseText([
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_abc',
                    'function' => ['name' => 'search', 'arguments' => '{"q":"test"}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->id)->toBe('call_abc');
    expect($result->toolCalls[0]->name)->toBe('search');
    expect($result->finishReason)->toBe(FinishReason::ToolCalls);
});

it('parses usage with prompt_tokens and completion_tokens', function () {
    $parser = makeCcParser();

    $usage = $parser->parseUsage([
        'usage' => [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'completion_tokens_details' => ['reasoning_tokens' => 20],
            'prompt_tokens_details' => ['cached_tokens' => 30],
        ],
    ]);

    expect($usage->inputTokens)->toBe(100);
    expect($usage->outputTokens)->toBe(50);
    expect($usage->reasoningTokens)->toBe(20);
    expect($usage->cachedTokens)->toBe(30);
});

it('maps finish_reason strings', function () {
    $parser = makeCcParser();

    expect($parser->parseFinishReason(['choices' => [['finish_reason' => 'stop']]]))->toBe(FinishReason::Stop);
    expect($parser->parseFinishReason(['choices' => [['finish_reason' => 'tool_calls']]]))->toBe(FinishReason::ToolCalls);
    expect($parser->parseFinishReason(['choices' => [['finish_reason' => 'length']]]))->toBe(FinishReason::Length);
    expect($parser->parseFinishReason(['choices' => [['finish_reason' => 'content_filter']]]))->toBe(FinishReason::ContentFilter);
});

it('parses reasoning_content from message', function () {
    $parser = makeCcParser();

    $result = $parser->parseText([
        'choices' => [['message' => ['content' => 'Answer', 'reasoning_content' => 'Thinking...'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    expect($result->reasoning)->toBe('Thinking...');
});

it('parses text delta stream chunk', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [['delta' => ['content' => 'Hello'], 'finish_reason' => null]],
    ]);

    expect($chunk->type)->toBe(ChunkType::Text);
    expect($chunk->text)->toBe('Hello');
});

it('returns fallback chunk for tool call deltas (accumulation handled by handler)', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [[
            'delta' => [
                'tool_calls' => [[
                    'id' => 'call_x',
                    'function' => ['name' => 'search', 'arguments' => '{"q":"hi"}'],
                ]],
            ],
            'finish_reason' => null,
        ]],
    ]);

    // Tool call deltas are now accumulated by the handler (Text::parseSSE),
    // so the parser returns a null-text fallback for unrecognized deltas.
    expect($chunk->type)->toBe(ChunkType::Text);
    expect($chunk->text)->toBeNull();
});

it('parses done stream chunk with usage', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [['delta' => [], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    expect($chunk->type)->toBe(ChunkType::Done);
    expect($chunk->finishReason)->toBe(FinishReason::Stop);
    expect($chunk->usage)->not->toBeNull();
    expect($chunk->usage->inputTokens)->toBe(10);
});

it('parses done stream chunk without usage', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [['delta' => [], 'finish_reason' => 'stop']],
    ]);

    expect($chunk->type)->toBe(ChunkType::Done);
    expect($chunk->finishReason)->toBe(FinishReason::Stop);
    expect($chunk->usage)->toBeNull();
});

it('parses empty delta as text chunk with null text', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [['delta' => [], 'finish_reason' => null]],
    ]);

    expect($chunk->type)->toBe(ChunkType::Text);
    expect($chunk->text)->toBeNull();
});

it('parses trailing usage-only chunk without finish_reason', function () {
    $parser = makeCcParser();

    $chunk = $parser->parseStreamChunk([
        'choices' => [['delta' => [], 'finish_reason' => null]],
        'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 8],
    ]);

    expect($chunk->type)->toBe(ChunkType::Done);
    expect($chunk->usage)->not->toBeNull();
    expect($chunk->usage->inputTokens)->toBe(15);
    expect($chunk->usage->outputTokens)->toBe(8);
    expect($chunk->finishReason)->toBeNull();
});

it('defaults unknown finish_reason to stop', function () {
    $parser = makeCcParser();

    $reason = $parser->parseFinishReason(['choices' => [['finish_reason' => 'unknown_reason']]]);

    expect($reason)->toBe(FinishReason::Stop);
});

it('handles missing usage gracefully', function () {
    $parser = makeCcParser();

    $usage = $parser->parseUsage([]);

    expect($usage->inputTokens)->toBe(0);
    expect($usage->outputTokens)->toBe(0);
    expect($usage->reasoningTokens)->toBeNull();
    expect($usage->cachedTokens)->toBeNull();
});
