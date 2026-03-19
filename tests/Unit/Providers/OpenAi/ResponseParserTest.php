<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\OpenAi\ResponseParser;
use Atlasphp\Atlas\Providers\OpenAi\ToolMapper;

function makeParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

it('parses text from message output item', function () {
    $parser = makeParser();

    $data = [
        'status' => 'completed',
        'output' => [
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hello world']]],
        ],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ];

    $response = $parser->parseText($data);

    expect($response->text)->toBe('Hello world');
    expect($response->finishReason)->toBe(FinishReason::Stop);
    expect($response->toolCalls)->toBe([]);
    expect($response->reasoning)->toBeNull();
});

it('parses function calls from output', function () {
    $parser = makeParser();

    $data = [
        'status' => 'completed',
        'output' => [
            ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'search', 'arguments' => '{"q":"test"}'],
        ],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ];

    $response = $parser->parseText($data);

    expect($response->finishReason)->toBe(FinishReason::ToolCalls);
    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls[0]->id)->toBe('call_1');
    expect($response->toolCalls[0]->name)->toBe('search');
    expect($response->toolCalls[0]->arguments)->toBe(['q' => 'test']);
});

it('parses reasoning from output', function () {
    $parser = makeParser();

    $data = [
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'summary' => [['type' => 'summary_text', 'text' => 'Thinking about it...']]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Answer']]],
        ],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ];

    $response = $parser->parseText($data);

    expect($response->text)->toBe('Answer');
    expect($response->reasoning)->toBe('Thinking about it...');
});

it('parses usage with reasoning and cached tokens', function () {
    $parser = makeParser();

    $data = [
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 50,
            'output_tokens_details' => ['reasoning_tokens' => 20],
            'input_tokens_details' => ['cached_tokens' => 30],
        ],
    ];

    $usage = $parser->parseUsage($data);

    expect($usage->inputTokens)->toBe(100);
    expect($usage->outputTokens)->toBe(50);
    expect($usage->reasoningTokens)->toBe(20);
    expect($usage->cachedTokens)->toBe(30);
});

it('parses usage without details', function () {
    $parser = makeParser();

    $data = ['usage' => ['input_tokens' => 10, 'output_tokens' => 5]];

    $usage = $parser->parseUsage($data);

    expect($usage->reasoningTokens)->toBeNull();
    expect($usage->cachedTokens)->toBeNull();
});

it('maps completed status to Stop', function () {
    $parser = makeParser();

    $result = $parser->parseFinishReason(['status' => 'completed', 'output' => []]);

    expect($result)->toBe(FinishReason::Stop);
});

it('maps incomplete with max_output_tokens to Length', function () {
    $parser = makeParser();

    $result = $parser->parseFinishReason([
        'status' => 'incomplete',
        'incomplete_details' => ['reason' => 'max_output_tokens'],
        'output' => [],
    ]);

    expect($result)->toBe(FinishReason::Length);
});

it('maps incomplete with content_filter to ContentFilter', function () {
    $parser = makeParser();

    $result = $parser->parseFinishReason([
        'status' => 'incomplete',
        'incomplete_details' => ['reason' => 'content_filter'],
        'output' => [],
    ]);

    expect($result)->toBe(FinishReason::ContentFilter);
});

it('maps function_call output to ToolCalls regardless of status', function () {
    $parser = makeParser();

    $result = $parser->parseFinishReason([
        'status' => 'completed',
        'output' => [['type' => 'function_call']],
    ]);

    expect($result)->toBe(FinishReason::ToolCalls);
});

it('parses text delta stream chunk', function () {
    $parser = makeParser();

    $chunk = $parser->parseStreamChunk([
        'event' => 'response.output_text.delta',
        'data' => ['delta' => 'Hello'],
    ]);

    expect($chunk->type)->toBe(ChunkType::Text);
    expect($chunk->text)->toBe('Hello');
});

it('parses function call done stream chunk', function () {
    $parser = makeParser();

    $chunk = $parser->parseStreamChunk([
        'event' => 'response.function_call_arguments.done',
        'data' => ['call_id' => 'call_1', 'name' => 'search', 'arguments' => '{"q":"test"}'],
    ]);

    expect($chunk->type)->toBe(ChunkType::ToolCall);
    expect($chunk->toolCalls)->toHaveCount(1);
    expect($chunk->toolCalls[0]->name)->toBe('search');
});

it('parses response completed stream chunk', function () {
    $parser = makeParser();

    $chunk = $parser->parseStreamChunk([
        'event' => 'response.completed',
        'data' => [],
    ]);

    expect($chunk->type)->toBe(ChunkType::Done);
});

it('throws ProviderException on response.failed event', function () {
    $parser = makeParser();

    $parser->parseStreamChunk([
        'event' => 'response.failed',
        'data' => [
            'response' => [
                'error' => ['message' => 'Server error during generation'],
            ],
        ],
    ]);
})->throws(ProviderException::class, 'Server error during generation');

it('returns empty text chunk for unknown events', function () {
    $parser = makeParser();

    $chunk = $parser->parseStreamChunk([
        'event' => 'response.created',
        'data' => [],
    ]);

    expect($chunk->type)->toBe(ChunkType::Text);
    expect($chunk->text)->toBeNull();
});
