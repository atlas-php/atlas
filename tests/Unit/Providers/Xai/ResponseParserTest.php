<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Xai\ResponseParser;
use Atlasphp\Atlas\Providers\Xai\ToolMapper;

function makeXaiParser(): ResponseParser
{
    return new ResponseParser(new ToolMapper);
}

it('reads reasoning from the OpenAI summary shape when present', function () {
    $response = makeXaiParser()->parseText([
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'summary' => [['type' => 'summary_text', 'text' => 'From summary']]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Answer']]],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ]);

    expect($response->reasoning)->toBe('From summary');
});

it('falls back to the content parts for grok reasoning output', function () {
    // xAI emits reasoning text under `content` rather than `summary`.
    $response = makeXaiParser()->parseText([
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'content' => [['text' => 'Grok is thinking'], ['text' => 'step two']]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Answer']]],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ]);

    expect($response->reasoning)->toBe("Grok is thinking\nstep two");
});
