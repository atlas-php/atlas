<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasMessagesSupport;

/**
 * Test class that uses the HasMessagesSupport trait.
 */
class TestMessagesClass
{
    use HasMessagesSupport;

    /**
     * Expose the protected method for testing.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function exposeGetMessages(): array
    {
        return $this->getMessages();
    }
}

test('withMessages returns a clone with messages', function () {
    $instance = new TestMessagesClass;
    $messages = [['role' => 'user', 'content' => 'Hello']];
    $clone = $instance->withMessages($messages);

    expect($clone)->not->toBe($instance);
    expect($clone)->toBeInstanceOf(TestMessagesClass::class);
});

test('withMessages stores messages', function () {
    $instance = new TestMessagesClass;
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ];
    $clone = $instance->withMessages($messages);

    expect($clone->exposeGetMessages())->toBe($messages);
});

test('getMessages returns empty array when no messages configured', function () {
    $instance = new TestMessagesClass;

    expect($instance->exposeGetMessages())->toBe([]);
});

test('original instance is not modified by withMessages', function () {
    $instance = new TestMessagesClass;
    $instance->withMessages([['role' => 'user', 'content' => 'Hello']]);

    expect($instance->exposeGetMessages())->toBe([]);
});

test('chained withMessages calls replace messages', function () {
    $instance = new TestMessagesClass;
    $messages1 = [['role' => 'user', 'content' => 'First']];
    $messages2 = [['role' => 'user', 'content' => 'Second']];

    $clone1 = $instance->withMessages($messages1);
    $clone2 = $clone1->withMessages($messages2);

    expect($clone1->exposeGetMessages())->toBe($messages1);
    expect($clone2->exposeGetMessages())->toBe($messages2);
});

test('withMessages handles long conversation history', function () {
    $instance = new TestMessagesClass;
    $messages = [];
    for ($i = 0; $i < 100; $i++) {
        $messages[] = [
            'role' => $i % 2 === 0 ? 'user' : 'assistant',
            'content' => "Message {$i}",
        ];
    }

    $clone = $instance->withMessages($messages);

    expect($clone->exposeGetMessages())->toBe($messages);
    expect(count($clone->exposeGetMessages()))->toBe(100);
});

test('withMessages handles empty messages array', function () {
    $instance = new TestMessagesClass;
    $instance = $instance->withMessages([['role' => 'user', 'content' => 'Hello']]);
    $clone = $instance->withMessages([]);

    expect($clone->exposeGetMessages())->toBe([]);
});

test('withMessages preserves message structure', function () {
    $instance = new TestMessagesClass;
    $messages = [
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi!'],
    ];

    $clone = $instance->withMessages($messages);
    $result = $clone->exposeGetMessages();

    expect($result[0]['role'])->toBe('system');
    expect($result[1]['role'])->toBe('user');
    expect($result[2]['role'])->toBe('assistant');
});
