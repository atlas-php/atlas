<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\ChatCompletions\Handlers\Text;
use Atlasphp\Atlas\Providers\ChatCompletions\MediaResolver;
use Atlasphp\Atlas\Providers\ChatCompletions\MessageFactory;
use Atlasphp\Atlas\Providers\ChatCompletions\ResponseParser;
use Atlasphp\Atlas\Providers\ChatCompletions\ToolMapper;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Responses\StreamChunk;
use GuzzleHttp\Psr7\Stream;

function makeCcTextHandler(): Text
{
    $toolMapper = new ToolMapper;

    return new Text(
        config: new ProviderConfig(apiKey: 'test', baseUrl: 'http://localhost/v1'),
        http: app(HttpClient::class),
        messages: new MessageFactory,
        media: new MediaResolver,
        toolMapper: $toolMapper,
        parser: new ResponseParser($toolMapper),
    );
}

function makeCcSseStream(string $content): object
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $body = new Stream($stream);

    return new class($body)
    {
        public function __construct(private readonly Stream $body) {}

        public function getBody(): Stream
        {
            return $this->body;
        }
    };
}

/**
 * Invoke the protected parseSSE method on the Text handler.
 *
 * @return array<int, StreamChunk>
 */
function invokeCcParseSSE(string $sseContent): array
{
    $handler = makeCcTextHandler();
    $raw = makeCcSseStream($sseContent);

    $ref = new ReflectionMethod($handler, 'parseSSE');

    return iterator_to_array($ref->invoke($handler, $raw));
}

// ─── Tool Call Accumulation ──────────────────────────────────────────────────

it('accumulates fragmented tool call arguments across deltas', function () {
    $sseContent = ''
        // First delta: tool call start with id, name, partial arguments
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'id' => 'call_abc',
                    'function' => ['name' => 'get_weather', 'arguments' => '{"loc'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Second delta: more argument fragment
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'function' => ['arguments' => 'ation":"'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Third delta: final argument fragment
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'function' => ['arguments' => 'Paris"}'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Finish with tool_calls reason
        .'data: '.json_encode([
            'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
        ])."\n"
        ."data: [DONE]\n";

    $chunks = invokeCcParseSSE($sseContent);

    // Should have: ToolCall chunk + Done chunk
    $toolCallChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::ToolCall));
    $doneChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Done));

    expect($toolCallChunks)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls[0]->id)->toBe('call_abc');
    expect($toolCallChunks[0]->toolCalls[0]->name)->toBe('get_weather');
    expect($toolCallChunks[0]->toolCalls[0]->arguments)->toBe(['location' => 'Paris']);

    expect($doneChunks)->toHaveCount(1);
    expect($doneChunks[0]->finishReason)->toBe(FinishReason::ToolCalls);
});

it('accumulates multiple concurrent tool calls', function () {
    $sseContent = ''
        // First tool call start
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'id' => 'call_1',
                    'function' => ['name' => 'search', 'arguments' => '{"q":'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Second tool call start
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 1,
                    'id' => 'call_2',
                    'function' => ['name' => 'calc', 'arguments' => '{"x":'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // First tool arguments continued
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'function' => ['arguments' => '"test"}'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Second tool arguments continued
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 1,
                    'function' => ['arguments' => '42}'],
                ]],
            ], 'finish_reason' => null]],
        ])."\n"
        // Finish
        .'data: '.json_encode([
            'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']],
            'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 8],
        ])."\n";

    $chunks = invokeCcParseSSE($sseContent);

    $toolCallChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::ToolCall));

    expect($toolCallChunks)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls)->toHaveCount(2);
    expect($toolCallChunks[0]->toolCalls[0]->name)->toBe('search');
    expect($toolCallChunks[0]->toolCalls[0]->arguments)->toBe(['q' => 'test']);
    expect($toolCallChunks[0]->toolCalls[1]->name)->toBe('calc');
    expect($toolCallChunks[0]->toolCalls[1]->arguments)->toBe(['x' => 42]);
});

it('handles combined tool_calls and finish_reason in same delta', function () {
    $sseContent = ''
        // Tool call with complete arguments + finish_reason in same delta
        .'data: '.json_encode([
            'choices' => [['delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'id' => 'call_combined',
                    'function' => ['name' => 'lookup', 'arguments' => '{"id":123}'],
                ]],
            ], 'finish_reason' => 'tool_calls']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])."\n";

    $chunks = invokeCcParseSSE($sseContent);

    $toolCallChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::ToolCall));
    $doneChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Done));

    expect($toolCallChunks)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls)->toHaveCount(1);
    expect($toolCallChunks[0]->toolCalls[0]->id)->toBe('call_combined');
    expect($toolCallChunks[0]->toolCalls[0]->name)->toBe('lookup');
    expect($toolCallChunks[0]->toolCalls[0]->arguments)->toBe(['id' => 123]);

    expect($doneChunks)->toHaveCount(1);
    expect($doneChunks[0]->finishReason)->toBe(FinishReason::ToolCalls);
});

it('handles text-only stream without tool calls', function () {
    $sseContent = ''
        .'data: '.json_encode([
            'choices' => [['delta' => ['content' => 'Hello'], 'finish_reason' => null]],
        ])."\n"
        .'data: '.json_encode([
            'choices' => [['delta' => ['content' => ' world'], 'finish_reason' => null]],
        ])."\n"
        .'data: '.json_encode([
            'choices' => [['delta' => [], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
        ])."\n";

    $chunks = invokeCcParseSSE($sseContent);

    $textChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Text && $c->text !== null));
    $doneChunks = array_values(array_filter($chunks, fn ($c) => $c->type === ChunkType::Done));

    expect($textChunks)->toHaveCount(2);
    expect($textChunks[0]->text)->toBe('Hello');
    expect($textChunks[1]->text)->toBe(' world');

    expect($doneChunks)->toHaveCount(1);
    expect($doneChunks[0]->finishReason)->toBe(FinishReason::Stop);
});
