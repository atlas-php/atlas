<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

it('stores all properties', function () {
    $usage = new Usage(100, 50);
    $steps = [
        new Step('text', [], [], new Usage(50, 25)),
        new Step('done', [], [], new Usage(50, 25)),
    ];

    $result = new ExecutorResult(
        text: 'Final answer',
        reasoning: 'Because...',
        steps: $steps,
        usage: $usage,
        finishReason: FinishReason::Stop,
        meta: ['id' => 'resp-1'],
    );

    expect($result->text)->toBe('Final answer');
    expect($result->reasoning)->toBe('Because...');
    expect($result->steps)->toBe($steps);
    expect($result->usage)->toBe($usage);
    expect($result->finishReason)->toBe(FinishReason::Stop);
    expect($result->meta)->toBe(['id' => 'resp-1']);
});

it('counts total steps', function () {
    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [
            new Step('a', [], [], new Usage(10, 10)),
            new Step('b', [], [], new Usage(10, 10)),
            new Step('c', [], [], new Usage(10, 10)),
        ],
        usage: new Usage(30, 30),
        finishReason: FinishReason::Stop,
        meta: [],
    );

    expect($result->totalSteps())->toBe(3);
});

it('sums tool calls across steps', function () {
    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [
            new Step(null, [
                new ToolCall('tc-1', 'search', []),
                new ToolCall('tc-2', 'calc', []),
            ], [], new Usage(10, 10)),
            new Step(null, [
                new ToolCall('tc-3', 'search', []),
            ], [], new Usage(10, 10)),
            new Step('done', [], [], new Usage(10, 10)),
        ],
        usage: new Usage(30, 30),
        finishReason: FinishReason::Stop,
        meta: [],
    );

    expect($result->totalToolCalls())->toBe(3);
});

it('returns flat array of all tool calls', function () {
    $tc1 = new ToolCall('tc-1', 'search', []);
    $tc2 = new ToolCall('tc-2', 'calc', []);
    $tc3 = new ToolCall('tc-3', 'search', []);

    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [
            new Step(null, [$tc1, $tc2], [], new Usage(10, 10)),
            new Step(null, [$tc3], [], new Usage(10, 10)),
            new Step('done', [], [], new Usage(10, 10)),
        ],
        usage: new Usage(30, 30),
        finishReason: FinishReason::Stop,
        meta: [],
    );

    expect($result->allToolCalls())->toBe([$tc1, $tc2, $tc3]);
});

it('defaults providerToolCalls and annotations to empty arrays', function () {
    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [],
        usage: new Usage(0, 0),
        finishReason: FinishReason::Stop,
        meta: [],
    );

    expect($result->providerToolCalls)->toBe([]);
    expect($result->annotations)->toBe([]);
});

it('stores provider tool calls and annotations', function () {
    $providerToolCalls = [
        ['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'completed'],
    ];
    $annotations = [
        ['type' => 'url_citation', 'url' => 'https://example.com', 'title' => 'Example'],
    ];

    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [],
        usage: new Usage(0, 0),
        finishReason: FinishReason::Stop,
        meta: [],
        providerToolCalls: $providerToolCalls,
        annotations: $annotations,
    );

    expect($result->providerToolCalls)->toHaveCount(1);
    expect($result->providerToolCalls[0]['type'])->toBe('web_search_call');
    expect($result->annotations)->toHaveCount(1);
    expect($result->annotations[0]['url'])->toBe('https://example.com');
});

it('converts to TextResponse via toTextResponse', function () {
    $tc = new ToolCall('tc-1', 'search', ['q' => 'test']);
    $step = new Step(null, [$tc], [], new Usage(10, 10));

    $result = new ExecutorResult(
        text: 'Done',
        reasoning: 'Thought about it',
        steps: [$step],
        usage: new Usage(10, 10),
        finishReason: FinishReason::Stop,
        meta: ['id' => 'resp-1'],
        providerToolCalls: [['type' => 'web_search_call', 'id' => 'ws_1']],
        annotations: [['type' => 'url_citation', 'url' => 'https://example.com']],
    );

    $response = $result->toTextResponse();

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Done');
    expect($response->reasoning)->toBe('Thought about it');
    expect($response->finishReason)->toBe(FinishReason::Stop);
    expect($response->meta)->toBe(['id' => 'resp-1']);
    expect($response->toolCalls)->toHaveCount(1);
    expect($response->providerToolCalls)->toHaveCount(1);
    expect($response->annotations)->toHaveCount(1);
});

it('merges extra meta in toTextResponse', function () {
    $result = new ExecutorResult(
        text: 'Done',
        reasoning: null,
        steps: [],
        usage: new Usage(0, 0),
        finishReason: FinishReason::Stop,
        meta: ['id' => 'resp-1'],
    );

    $response = $result->toTextResponse(['conversation_id' => 42, 'execution_id' => 7]);

    expect($response->meta)->toBe(['id' => 'resp-1', 'conversation_id' => 42, 'execution_id' => 7]);
});

it('handles empty steps', function () {
    $result = new ExecutorResult(
        text: 'done',
        reasoning: null,
        steps: [],
        usage: new Usage(0, 0),
        finishReason: FinishReason::Stop,
        meta: [],
    );

    expect($result->totalSteps())->toBe(0);
    expect($result->totalToolCalls())->toBe(0);
    expect($result->allToolCalls())->toBe([]);
});
