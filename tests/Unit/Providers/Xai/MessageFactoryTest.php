<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\Xai\MessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;

function makeXaiMediaResolver(): MediaResolver
{
    return new class implements MediaResolver
    {
        public function resolve(Input $input): array
        {
            return ['type' => 'input_image', 'image_url' => 'https://resolved.test/image.png'];
        }
    };
}

function makeXaiTextRequest(array $overrides = []): TextRequest
{
    return new TextRequest(
        model: $overrides['model'] ?? 'grok-3',
        instructions: $overrides['instructions'] ?? null,
        message: $overrides['message'] ?? 'Hello',
        messageMedia: $overrides['messageMedia'] ?? [],
        messages: $overrides['messages'] ?? [],
        maxTokens: $overrides['maxTokens'] ?? null,
        temperature: $overrides['temperature'] ?? null,
        schema: $overrides['schema'] ?? null,
        tools: $overrides['tools'] ?? [],
        providerTools: $overrides['providerTools'] ?? [],
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

it('uses system role instead of developer', function () {
    $factory = new MessageFactory;
    $message = new SystemMessage('Be helpful');

    $result = $factory->system($message);

    expect($result)->toBe(['role' => 'system', 'content' => 'Be helpful']);
});

it('buildAll puts instructions as system message in input', function () {
    $factory = new MessageFactory;
    $media = makeXaiMediaResolver();

    $request = makeXaiTextRequest(['instructions' => 'Be concise']);

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBeNull();
    expect($result['input'][0])->toBe(['role' => 'system', 'content' => 'Be concise']);
    expect($result['input'][1]['role'])->toBe('user');
    expect($result['input'][1]['content'])->toBe('Hello');
});

it('buildAll with no instructions does not prepend system message', function () {
    $factory = new MessageFactory;
    $media = makeXaiMediaResolver();

    $request = makeXaiTextRequest();

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBeNull();
    expect($result['input'])->toHaveCount(1);
    expect($result['input'][0]['role'])->toBe('user');
});

it('buildAll with system message in history uses it as instructions', function () {
    $factory = new MessageFactory;
    $media = makeXaiMediaResolver();

    $request = makeXaiTextRequest([
        'messages' => [new SystemMessage('Be helpful')],
    ]);

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBeNull();
    expect($result['input'][0])->toBe(['role' => 'system', 'content' => 'Be helpful']);
});

it('inherits user message handling from OpenAI', function () {
    $factory = new MessageFactory;
    $media = makeXaiMediaResolver();
    $message = new UserMessage('Hello');

    $result = $factory->user($message, $media);

    expect($result)->toBe(['role' => 'user', 'content' => 'Hello']);
});

it('inherits assistant message handling from OpenAI', function () {
    $factory = new MessageFactory;
    $message = new AssistantMessage('I can help');

    $result = $factory->assistant($message);

    expect($result)->toBe(['role' => 'assistant', 'content' => 'I can help']);
});

it('inherits tool result handling from OpenAI', function () {
    $factory = new MessageFactory;
    $message = new ToolResultMessage('call_123', 'result');

    $result = $factory->toolResult($message);

    expect($result)->toBe([
        'type' => 'function_call_output',
        'call_id' => 'call_123',
        'output' => 'result',
    ]);
});

it('buildAll expands assistant with tool calls', function () {
    $factory = new MessageFactory;
    $media = makeXaiMediaResolver();

    $assistant = new AssistantMessage(
        'Let me search.',
        [new ToolCall('call_1', 'search', ['q' => 'test'])],
    );

    $toolResult = new ToolResultMessage('call_1', 'found it');

    $request = makeXaiTextRequest([
        'instructions' => 'Be helpful',
        'messages' => [$assistant, $toolResult],
        'message' => 'Thanks',
    ]);

    $result = $factory->buildAll($request, $media);

    // system + assistant text + function_call + function_call_output + user
    expect($result['input'])->toHaveCount(5);
    expect($result['input'][0]['role'])->toBe('system');
    expect($result['input'][1]['role'])->toBe('assistant');
    expect($result['input'][2]['type'])->toBe('function_call');
    expect($result['input'][3]['type'])->toBe('function_call_output');
    expect($result['input'][4]['role'])->toBe('user');
});
