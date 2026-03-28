<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Anthropic\ResponseParser;
use Atlasphp\Atlas\Providers\Anthropic\ToolMapper;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeAnthropicResponseParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

it('parses text from content blocks', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseText([
        'id' => 'msg_123',
        'model' => 'claude-sonnet-4-5-20250514',
        'content' => [
            ['type' => 'text', 'text' => 'Hello!'],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ]);

    expect($result)->toBeInstanceOf(TextResponse::class);
    expect($result->text)->toBe('Hello!');
});

it('parses tool_use blocks as tool calls', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseText([
        'id' => 'msg_123',
        'model' => 'claude-sonnet-4-5-20250514',
        'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'search', 'input' => ['query' => 'test']],
        ],
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ]);

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('search');
    expect($result->toolCalls[0]->id)->toBe('toolu_123');
    expect($result->toolCalls[0]->arguments)->toBe(['query' => 'test']);
});

it('parses thinking blocks as reasoning', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseText([
        'id' => 'msg_123',
        'model' => 'claude-sonnet-4-5-20250514',
        'content' => [
            ['type' => 'thinking', 'thinking' => 'Let me think...'],
            ['type' => 'text', 'text' => 'The answer is 42'],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
    ]);

    expect($result->text)->toBe('The answer is 42');
    expect($result->reasoning)->toBe('Let me think...');
});

it('parses usage with cached tokens', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseUsage([
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cache_read_input_tokens' => 80,
        ],
    ]);

    expect($result)->toBeInstanceOf(Usage::class);
    expect($result->inputTokens)->toBe(100);
    expect($result->outputTokens)->toBe(50);
    expect($result->cachedTokens)->toBe(80);
});

it('maps end_turn stop reason to Stop', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseFinishReason(['stop_reason' => 'end_turn']);

    expect($result)->toBe(FinishReason::Stop);
});

it('maps max_tokens stop reason to Length', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseFinishReason(['stop_reason' => 'max_tokens']);

    expect($result)->toBe(FinishReason::Length);
});

it('maps tool_use stop reason to ToolCalls', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseFinishReason(['stop_reason' => 'tool_use']);

    expect($result)->toBe(FinishReason::ToolCalls);
});

it('maps stop_sequence stop reason to Stop', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseFinishReason(['stop_reason' => 'stop_sequence']);

    expect($result)->toBe(FinishReason::Stop);
});

it('parses stream chunk with text delta', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseStreamChunk([
        'event' => 'content_block_delta',
        'data' => ['delta' => ['type' => 'text_delta', 'text' => 'Hello']],
    ]);

    expect($result)->toBeInstanceOf(StreamChunk::class);
    expect($result->type)->toBe(ChunkType::Text);
    expect($result->text)->toBe('Hello');
});

it('parses stream chunk with thinking delta', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseStreamChunk([
        'event' => 'content_block_delta',
        'data' => ['delta' => ['type' => 'thinking_delta', 'thinking' => 'reasoning...']],
    ]);

    expect($result->type)->toBe(ChunkType::Thinking);
    expect($result->reasoning)->toBe('reasoning...');
});

it('parses stream chunk with message_delta as done', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseStreamChunk([
        'event' => 'message_delta',
        'data' => [
            'delta' => ['stop_reason' => 'end_turn'],
            'usage' => ['output_tokens' => 50],
        ],
    ]);

    expect($result->type)->toBe(ChunkType::Done);
    expect($result->finishReason)->toBe(FinishReason::Stop);
});

it('returns null-text chunk for unknown event type', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseStreamChunk([
        'event' => 'message_start',
        'data' => ['type' => 'message'],
    ]);

    expect($result->type)->toBe(ChunkType::Text);
    expect($result->text)->toBeNull();
});

it('parses usage with missing usage key as zeros', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseUsage([]);

    expect($result)->toBeInstanceOf(Usage::class);
    expect($result->inputTokens)->toBe(0);
    expect($result->outputTokens)->toBe(0);
    expect($result->cachedTokens)->toBeNull();
});

it('includes meta with id and model', function () {
    $parser = makeAnthropicResponseParser();

    $result = $parser->parseText([
        'id' => 'msg_abc123',
        'model' => 'claude-sonnet-4-5-20250514',
        'content' => [['type' => 'text', 'text' => 'Hi']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
    ]);

    expect($result->meta['id'])->toBe('msg_abc123');
    expect($result->meta['model'])->toBe('claude-sonnet-4-5-20250514');
});
