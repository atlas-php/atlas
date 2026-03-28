<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Pending\Concerns\NormalizesMessages;

class NormalizesMessagesTestHelper
{
    use NormalizesMessages;

    /** @return array<int, Message> */
    public function normalize(array $messages): array
    {
        return $this->normalizeMessages($messages);
    }
}

it('passes typed messages through unchanged', function () {
    $helper = new NormalizesMessagesTestHelper;
    $msg = new UserMessage('hello');

    $result = $helper->normalize([$msg]);

    expect($result[0])->toBe($msg);
});

it('converts user array to UserMessage', function () {
    $helper = new NormalizesMessagesTestHelper;

    $result = $helper->normalize([['role' => 'user', 'content' => 'hi']]);

    expect($result[0])->toBeInstanceOf(UserMessage::class);
    expect($result[0]->content)->toBe('hi');
});

it('converts assistant array to AssistantMessage', function () {
    $helper = new NormalizesMessagesTestHelper;

    $result = $helper->normalize([['role' => 'assistant', 'content' => 'hello']]);

    expect($result[0])->toBeInstanceOf(AssistantMessage::class);
    expect($result[0]->content)->toBe('hello');
});

it('converts system array to SystemMessage', function () {
    $helper = new NormalizesMessagesTestHelper;

    $result = $helper->normalize([['role' => 'system', 'content' => 'you are helpful']]);

    expect($result[0])->toBeInstanceOf(SystemMessage::class);
    expect($result[0]->content)->toBe('you are helpful');
});

it('converts tool array to ToolResultMessage', function () {
    $helper = new NormalizesMessagesTestHelper;

    $result = $helper->normalize([
        ['role' => 'tool', 'content' => 'result data', 'toolCallId' => 'tc_1', 'toolName' => 'search'],
    ]);

    expect($result[0])->toBeInstanceOf(ToolResultMessage::class);
    expect($result[0]->toolCallId)->toBe('tc_1');
    expect($result[0]->content)->toBe('result data');
    expect($result[0]->toolName)->toBe('search');
});

it('normalizes mixed typed and array messages', function () {
    $helper = new NormalizesMessagesTestHelper;
    $typed = new UserMessage('typed');

    $result = $helper->normalize([
        $typed,
        ['role' => 'assistant', 'content' => 'from array'],
    ]);

    expect($result)->toHaveCount(2);
    expect($result[0])->toBe($typed);
    expect($result[1])->toBeInstanceOf(AssistantMessage::class);
});

it('throws for invalid role string', function () {
    $helper = new NormalizesMessagesTestHelper;

    $helper->normalize([['role' => 'invalid', 'content' => 'test']]);
})->throws(InvalidArgumentException::class);

it('throws for missing role key', function () {
    $helper = new NormalizesMessagesTestHelper;

    $helper->normalize([['content' => 'no role']]);
})->throws(InvalidArgumentException::class, '(missing)');
