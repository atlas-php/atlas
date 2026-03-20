<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\ChatCompletions\MessageFactory;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Requests\TextRequest;

function makeCcMediaResolver(): MediaResolver
{
    return new class implements MediaResolver
    {
        public function resolve(Input $input): array
        {
            return ['type' => 'image_url', 'image_url' => ['url' => 'https://resolved.test/image.png']];
        }
    };
}

it('converts system message to system role', function () {
    $factory = new MessageFactory;
    $result = $factory->system(new SystemMessage('Be helpful'));

    expect($result)->toBe(['role' => 'system', 'content' => 'Be helpful']);
});

it('converts text-only user message', function () {
    $factory = new MessageFactory;
    $result = $factory->user(new UserMessage('Hello'), makeCcMediaResolver());

    expect($result)->toBe(['role' => 'user', 'content' => 'Hello']);
});

it('converts user message with media to content array', function () {
    $factory = new MessageFactory;
    $result = $factory->user(
        new UserMessage('Describe this', [Image::fromUrl('https://example.com/img.jpg')]),
        makeCcMediaResolver(),
    );

    expect($result['role'])->toBe('user');
    expect($result['content'])->toBeArray();
    expect($result['content'][0])->toBe(['type' => 'text', 'text' => 'Describe this']);
    expect($result['content'][1])->toBe(['type' => 'image_url', 'image_url' => ['url' => 'https://resolved.test/image.png']]);
});

it('converts assistant message with tool calls', function () {
    $factory = new MessageFactory;
    $result = $factory->assistant(new AssistantMessage(
        'Let me check.',
        [new ToolCall('call_1', 'search', ['q' => 'test'])],
    ));

    expect($result['role'])->toBe('assistant');
    expect($result['content'])->toBe('Let me check.');
    expect($result['tool_calls'])->toHaveCount(1);
    expect($result['tool_calls'][0]['id'])->toBe('call_1');
    expect($result['tool_calls'][0]['type'])->toBe('function');
    expect($result['tool_calls'][0]['function']['name'])->toBe('search');
    expect($result['tool_calls'][0]['function']['arguments'])->toBe('{"q":"test"}');
});

it('converts tool result to tool role', function () {
    $factory = new MessageFactory;
    $result = $factory->toolResult(new ToolResultMessage('call_123', 'search result'));

    expect($result)->toBe([
        'role' => 'tool',
        'tool_call_id' => 'call_123',
        'content' => 'search result',
    ]);
});

it('buildAll returns messages key with instructions as system message', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: 'Be concise',
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    expect($result)->toHaveKey('messages');
    expect($result['messages'])->toHaveCount(2);
    expect($result['messages'][0])->toBe(['role' => 'system', 'content' => 'Be concise']);
    expect($result['messages'][1])->toBe(['role' => 'user', 'content' => 'Hello']);
});

it('buildAll with no instructions skips system message', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    expect($result['messages'])->toHaveCount(1);
    expect($result['messages'][0]['role'])->toBe('user');
});

it('buildAll includes SystemMessage from messages array', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [
            new SystemMessage('You are a pirate.'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    expect($result['messages'])->toHaveCount(2);
    expect($result['messages'][0])->toBe(['role' => 'system', 'content' => 'You are a pirate.']);
    expect($result['messages'][1]['role'])->toBe('user');
});

it('buildAll includes UserMessage from messages array', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: null,
        message: null,
        messageMedia: [],
        messages: [
            new UserMessage('First question'),
            new UserMessage('Second question'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    expect($result['messages'])->toHaveCount(2);
    expect($result['messages'][0])->toBe(['role' => 'user', 'content' => 'First question']);
    expect($result['messages'][1])->toBe(['role' => 'user', 'content' => 'Second question']);
});

it('buildAll includes all message types in correct order', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: 'Be helpful',
        message: 'Follow-up',
        messageMedia: [],
        messages: [
            new SystemMessage('Extra context'),
            new UserMessage('First'),
            new AssistantMessage('Response'),
            new ToolResultMessage('call_1', 'result'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    // instructions(system) + SystemMessage + UserMessage + AssistantMessage + ToolResult + message(user)
    expect($result['messages'])->toHaveCount(6);
    expect($result['messages'][0]['role'])->toBe('system');
    expect($result['messages'][0]['content'])->toBe('Be helpful');
    expect($result['messages'][1]['role'])->toBe('system');
    expect($result['messages'][1]['content'])->toBe('Extra context');
    expect($result['messages'][2]['role'])->toBe('user');
    expect($result['messages'][3]['role'])->toBe('assistant');
    expect($result['messages'][4]['role'])->toBe('tool');
    expect($result['messages'][5]['role'])->toBe('user');
    expect($result['messages'][5]['content'])->toBe('Follow-up');
});

it('buildAll includes assistant with tool calls and tool results', function () {
    $factory = new MessageFactory;

    $request = new TextRequest(
        model: 'llama3.2',
        instructions: null,
        message: 'Thanks',
        messageMedia: [],
        messages: [
            new AssistantMessage('Let me search.', [new ToolCall('call_1', 'search', ['q' => 'test'])]),
            new ToolResultMessage('call_1', 'found it'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, makeCcMediaResolver());

    // Assistant + tool result + new user message
    expect($result['messages'])->toHaveCount(3);
    expect($result['messages'][0]['role'])->toBe('assistant');
    expect($result['messages'][0]['tool_calls'])->toHaveCount(1);
    expect($result['messages'][1]['role'])->toBe('tool');
    expect($result['messages'][1]['tool_call_id'])->toBe('call_1');
    expect($result['messages'][2]['role'])->toBe('user');
    expect($result['messages'][2]['content'])->toBe('Thanks');
});
