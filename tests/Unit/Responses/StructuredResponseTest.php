<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\Usage;

it('serializes to array with structured, usage, finish_reason, and meta', function () {
    $response = new StructuredResponse(
        structured: ['name' => 'Ada', 'age' => 36],
        usage: new Usage(inputTokens: 10, outputTokens: 5),
        finishReason: FinishReason::Stop,
        meta: ['id' => 'resp_1', 'model' => 'gpt-4o-mini'],
    );

    expect($response->toArray())->toBe([
        'structured' => ['name' => 'Ada', 'age' => 36],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        'finish_reason' => 'stop',
        'meta' => ['id' => 'resp_1', 'model' => 'gpt-4o-mini'],
    ]);
});

it('json_encodes via JsonSerializable producing the same shape as toArray', function () {
    $response = new StructuredResponse(
        structured: ['ok' => true],
        usage: new Usage(inputTokens: 3, outputTokens: 1),
        finishReason: FinishReason::ToolCalls,
    );

    $json = json_encode($response);

    expect($json)->not->toBeFalse();
    expect(json_decode((string) $json, true))->toBe([
        'structured' => ['ok' => true],
        'usage' => ['input_tokens' => 3, 'output_tokens' => 1],
        'finish_reason' => 'tool_calls',
        'meta' => [],
    ]);
});
