<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Anthropic\MediaResolver;
use Atlasphp\Atlas\Providers\Anthropic\MessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;

it('converts system message to text array', function () {
    $factory = new MessageFactory;

    $result = $factory->system(new SystemMessage('Be helpful'));

    expect($result)->toBe(['text' => 'Be helpful']);
});

it('converts user message with text only', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $result = $factory->user(new UserMessage('Hello'), $media);

    expect($result)->toBe([
        'role' => 'user',
        'content' => 'Hello',
    ]);
});

it('converts user message with media uses content blocks', function () {
    $factory = new MessageFactory;
    $media = Mockery::mock(MediaResolver::class);
    $media->shouldReceive('resolve')->once()->andReturn(['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'abc123']]);

    $input = Mockery::mock(Input::class);
    $message = new UserMessage('Describe this', [$input]);

    $result = $factory->user($message, $media);

    expect($result['role'])->toBe('user');
    expect($result['content'])->toBeArray();
    expect($result['content'])->toHaveCount(2);
    expect($result['content'][0]['type'])->toBe('image');
    expect($result['content'][1])->toBe(['type' => 'text', 'text' => 'Describe this']);
});

it('converts user message with media only and empty content', function () {
    $factory = new MessageFactory;
    $media = Mockery::mock(MediaResolver::class);
    $media->shouldReceive('resolve')->once()->andReturn(['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'abc']]);

    $input = Mockery::mock(Input::class);
    $message = new UserMessage('', [$input]);

    $result = $factory->user($message, $media);

    expect($result['role'])->toBe('user');
    expect($result['content'])->toBeArray();
    expect($result['content'])->toHaveCount(1);
    expect($result['content'][0]['type'])->toBe('image');
});

it('converts assistant message with text', function () {
    $factory = new MessageFactory;

    $result = $factory->assistant(new AssistantMessage('I can help'));

    expect($result)->toBe([
        'role' => 'assistant',
        'content' => [
            ['type' => 'text', 'text' => 'I can help'],
        ],
    ]);
});

it('converts assistant message with tool calls', function () {
    $factory = new MessageFactory;

    $result = $factory->assistant(new AssistantMessage(null, [
        new ToolCall('toolu_123', 'search', ['query' => 'test']),
    ]));

    expect($result['role'])->toBe('assistant');
    expect($result['content'])->toHaveCount(1);
    expect($result['content'][0]['type'])->toBe('tool_use');
    expect($result['content'][0]['id'])->toBe('toolu_123');
    expect($result['content'][0]['name'])->toBe('search');
    expect($result['content'][0]['input'])->toBe(['query' => 'test']);
});

it('converts tool result message', function () {
    $factory = new MessageFactory;

    $result = $factory->toolResult(new ToolResultMessage('toolu_123', 'The weather is sunny', 'get_weather'));

    expect($result['role'])->toBe('user');
    expect($result['content'][0]['type'])->toBe('tool_result');
    expect($result['content'][0]['tool_use_id'])->toBe('toolu_123');
    expect($result['content'][0]['content'])->toBe('The weather is sunny');
    expect($result['content'][0])->not->toHaveKey('is_error');
});

it('includes is_error flag on tool result when isError is true', function () {
    $factory = new MessageFactory;

    $result = $factory->toolResult(new ToolResultMessage('toolu_456', 'Tool failed', 'broken_tool', isError: true));

    expect($result['content'][0]['is_error'])->toBeTrue();
});

it('buildAll extracts system from instructions', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'claude-sonnet-4-5-20250514',
        instructions: 'Be helpful',
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['system'])->toBe('Be helpful');
});

it('buildAll with null instructions returns null system', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'claude-sonnet-4-5-20250514',
        instructions: null,
        message: 'Hi',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['system'])->toBeNull();
});

it('buildAll merges system messages from history', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'claude-sonnet-4-5-20250514',
        instructions: 'Be helpful',
        message: 'Hi',
        messageMedia: [],
        messages: [
            new SystemMessage('Extra system context'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['system'])->toBe("Be helpful\n\nExtra system context");
});

it('buildAll appends current message to messages', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'claude-sonnet-4-5-20250514',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [
            new UserMessage('Previous message'),
            new AssistantMessage('Previous response'),
        ],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['messages'])->toHaveCount(3);
    expect($result['messages'][0]['role'])->toBe('user');
    expect($result['messages'][0]['content'])->toBe('Previous message');
    expect($result['messages'][1]['role'])->toBe('assistant');
    expect($result['messages'][2]['role'])->toBe('user');
    expect($result['messages'][2]['content'])->toBe('Hello');
});
