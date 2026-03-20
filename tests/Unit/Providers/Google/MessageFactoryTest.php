<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Google\MediaResolver;
use Atlasphp\Atlas\Providers\Google\MessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;

it('converts user message with text only', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $result = $factory->user(new UserMessage('Hello'), $media);

    expect($result)->toBe([
        'role' => 'user',
        'parts' => [['text' => 'Hello']],
    ]);
});

it('converts user message with empty content and media uses only media parts', function () {
    $factory = new MessageFactory;
    $media = Mockery::mock(MediaResolver::class);
    $media->shouldReceive('resolve')->once()->andReturn(['inline_data' => ['mime_type' => 'image/png', 'data' => 'abc123']]);

    $input = Mockery::mock(Input::class);
    $message = new UserMessage('', [$input]);

    $result = $factory->user($message, $media);

    expect($result['role'])->toBe('user');
    expect($result['parts'])->toHaveCount(1);
    expect($result['parts'][0])->toBe(['inline_data' => ['mime_type' => 'image/png', 'data' => 'abc123']]);
});

it('converts assistant message with text', function () {
    $factory = new MessageFactory;

    $result = $factory->assistant(new AssistantMessage('I can help'));

    expect($result)->toBe([
        'role' => 'model',
        'parts' => [['text' => 'I can help']],
    ]);
});

it('converts assistant message with tool calls', function () {
    $factory = new MessageFactory;

    $result = $factory->assistant(new AssistantMessage(null, [
        new ToolCall('call_1', 'search', ['query' => 'test']),
    ]));

    expect($result['role'])->toBe('model');
    expect($result['parts'])->toHaveCount(1);
    expect($result['parts'][0]['functionCall']['name'])->toBe('search');
    expect($result['parts'][0]['functionCall']['args'])->toBe(['query' => 'test']);
});

it('converts tool result message', function () {
    $factory = new MessageFactory;

    $result = $factory->toolResult(new ToolResultMessage('call_1', 'plain text result', 'search'));

    expect($result['role'])->toBe('user');
    expect($result['parts'][0]['functionResponse']['name'])->toBe('search');
    expect($result['parts'][0]['functionResponse']['response'])->toBe(['result' => 'plain text result']);
});

it('converts tool result message with JSON content', function () {
    $factory = new MessageFactory;

    $result = $factory->toolResult(new ToolResultMessage('call_1', '{"found": true}', 'search'));

    expect($result['role'])->toBe('user');
    expect($result['parts'][0]['functionResponse']['response'])->toBe(['found' => true]);
});

it('buildAll extracts system_instruction from instructions', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'gemini-2.5-flash',
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

    expect($result['system_instruction'])->toBe([
        'parts' => [['text' => 'Be helpful']],
    ]);
});

it('buildAll with null instructions returns null system_instruction', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'gemini-2.5-flash',
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

    expect($result['system_instruction'])->toBeNull();
});

it('buildAll merges system messages from history into system_instruction', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'gemini-2.5-flash',
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

    expect($result['system_instruction']['parts'])->toHaveCount(2);
    expect($result['system_instruction']['parts'][0]['text'])->toBe('Be helpful');
    expect($result['system_instruction']['parts'][1]['text'])->toBe('Extra system context');
});

it('buildAll appends current message to contents', function () {
    $factory = new MessageFactory;
    $media = new MediaResolver;

    $request = new TextRequest(
        model: 'gemini-2.5-flash',
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

    expect($result['contents'])->toHaveCount(3);
    expect($result['contents'][0]['role'])->toBe('user');
    expect($result['contents'][0]['parts'][0]['text'])->toBe('Previous message');
    expect($result['contents'][1]['role'])->toBe('model');
    expect($result['contents'][2]['role'])->toBe('user');
    expect($result['contents'][2]['parts'][0]['text'])->toBe('Hello');
});
