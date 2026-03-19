<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Providers\Contracts\MediaResolver;
use Atlasphp\Atlas\Providers\OpenAi\MessageFactory;
use Atlasphp\Atlas\Requests\TextRequest;

function makeOpenAiMediaResolver(): MediaResolver
{
    return new class implements MediaResolver
    {
        public function resolve(Input $input): array
        {
            return ['type' => 'input_image', 'image_url' => 'https://resolved.test/image.png'];
        }
    };
}

it('converts system message to developer role', function () {
    $factory = new MessageFactory;
    $message = new SystemMessage('Be helpful');

    $result = $factory->system($message);

    expect($result)->toBe(['role' => 'developer', 'content' => 'Be helpful']);
});

it('converts text-only user message', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();
    $message = new UserMessage('Hello');

    $result = $factory->user($message, $media);

    expect($result)->toBe(['role' => 'user', 'content' => 'Hello']);
});

it('converts user message with media to content array', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();
    $message = new UserMessage('Describe this', [Image::fromUrl('https://example.com/img.jpg')]);

    $result = $factory->user($message, $media);

    expect($result['role'])->toBe('user');
    expect($result['content'])->toBeArray();
    expect($result['content'][0])->toBe(['type' => 'input_text', 'text' => 'Describe this']);
    expect($result['content'][1])->toBe(['type' => 'input_image', 'image_url' => 'https://resolved.test/image.png']);
});

it('converts assistant message', function () {
    $factory = new MessageFactory;
    $message = new AssistantMessage('I can help');

    $result = $factory->assistant($message);

    expect($result)->toBe(['role' => 'assistant', 'content' => 'I can help']);
});

it('converts tool result to function_call_output', function () {
    $factory = new MessageFactory;
    $message = new ToolResultMessage('call_123', 'search result');

    $result = $factory->toolResult($message);

    expect($result)->toBe([
        'type' => 'function_call_output',
        'call_id' => 'call_123',
        'output' => 'search result',
    ]);
});

it('buildAll separates instructions from input', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();

    $request = new TextRequest(
        model: 'gpt-4o',
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

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBe('Be concise');
    expect($result['input'])->toHaveCount(1);
    expect($result['input'][0]['role'])->toBe('user');
    expect($result['input'][0]['content'])->toBe('Hello');
});

it('buildAll with null instructions returns null', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();

    $request = new TextRequest(
        model: 'gpt-4o',
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

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBeNull();
});

it('buildAll with null message skips current user message', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();

    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: null,
        message: null,
        messageMedia: [],
        messages: [new UserMessage('Previous message')],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['input'])->toHaveCount(1);
    expect($result['input'][0]['content'])->toBe('Previous message');
});

it('buildAll expands assistant with tool calls to function_call items', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();

    $assistant = new AssistantMessage(
        'Let me search.',
        [new ToolCall('call_1', 'search', ['q' => 'test'])],
    );

    $toolResult = new ToolResultMessage('call_1', 'found it');

    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: null,
        message: 'Thanks',
        messageMedia: [],
        messages: [$assistant, $toolResult],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    // Assistant text + function_call + function_call_output + new user message
    expect($result['input'])->toHaveCount(4);

    // Assistant text
    expect($result['input'][0])->toBe(['role' => 'assistant', 'content' => 'Let me search.']);

    // Function call
    expect($result['input'][1]['type'])->toBe('function_call');
    expect($result['input'][1]['call_id'])->toBe('call_1');
    expect($result['input'][1]['name'])->toBe('search');
    expect($result['input'][1]['arguments'])->toBe('{"q":"test"}');

    // Tool result
    expect($result['input'][2]['type'])->toBe('function_call_output');
    expect($result['input'][2]['call_id'])->toBe('call_1');

    // New user message
    expect($result['input'][3]['content'])->toBe('Thanks');
});

it('buildAll uses system message as instructions fallback', function () {
    $factory = new MessageFactory;
    $media = makeOpenAiMediaResolver();

    $request = new TextRequest(
        model: 'gpt-4o',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [new SystemMessage('Be helpful')],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $result = $factory->buildAll($request, $media);

    expect($result['instructions'])->toBe('Be helpful');
    // System message should not appear in input
    expect($result['input'])->toHaveCount(1);
    expect($result['input'][0]['content'])->toBe('Hello');
});
