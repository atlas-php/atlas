<?php

declare(strict_types=1);

use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Providers\Concerns\BuildsResponsesMessages;
use Atlasphp\Atlas\Providers\Contracts\MediaResolverContract;

function makeTestResponsesMessageBuilder(): object
{
    return new class
    {
        use BuildsResponsesMessages {
            expandAssistant as public;
        }
    };
}

function makeTestMediaResolver(): MediaResolverContract
{
    return new class implements MediaResolverContract
    {
        public function resolve(Input $input): array
        {
            return ['type' => 'input_image', 'image_url' => 'https://resolved.test/image.png'];
        }
    };
}

it('expandAssistant encodes tool call arguments as JSON', function () {
    $builder = makeTestResponsesMessageBuilder();

    $message = new AssistantMessage(
        content: null,
        toolCalls: [
            new ToolCall('call-1', 'get_weather', ['location' => 'Paris']),
        ],
    );

    $input = [];
    $builder->expandAssistant($message, $input);

    expect($input)->toHaveCount(1);
    expect($input[0]['type'])->toBe('function_call');
    expect($input[0]['call_id'])->toBe('call-1');
    expect($input[0]['name'])->toBe('get_weather');
    expect($input[0]['arguments'])->toBe('{"location":"Paris"}');
});

it('expandAssistant includes both content and tool calls', function () {
    $builder = makeTestResponsesMessageBuilder();

    $message = new AssistantMessage(
        content: 'Let me check that.',
        toolCalls: [
            new ToolCall('call-1', 'search', ['query' => 'test']),
        ],
    );

    $input = [];
    $builder->expandAssistant($message, $input);

    expect($input)->toHaveCount(2);
    expect($input[0]['role'])->toBe('assistant');
    expect($input[0]['content'])->toBe('Let me check that.');
    expect($input[1]['type'])->toBe('function_call');
});

it('expandAssistant throws JsonException on unencodable arguments', function () {
    $builder = makeTestResponsesMessageBuilder();

    // Create a string with invalid UTF-8 bytes
    $invalidUtf8 = "invalid \xB1\x31 bytes";

    $message = new AssistantMessage(
        content: null,
        toolCalls: [
            new ToolCall('call-1', 'test_tool', ['data' => $invalidUtf8]),
        ],
    );

    $input = [];
    $builder->expandAssistant($message, $input);
})->throws(JsonException::class);
