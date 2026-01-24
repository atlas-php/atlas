<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Usage;

test('MediaConverter is registered as singleton', function () {
    $converter1 = app(MediaConverter::class);
    $converter2 = app(MediaConverter::class);

    expect($converter1)->toBe($converter2);
    expect($converter1)->toBeInstanceOf(MediaConverter::class);
});

test('agent executor handles current attachments', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I can see the image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $context = new ExecutionContext(
        currentAttachments: [
            [
                'type' => 'image',
                'source' => 'url',
                'data' => 'https://example.com/image.jpg',
            ],
        ],
    );

    $response = $executor->execute($agent, 'What do you see?', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
    expect($response->text)->toBe('I can see the image');
});

test('agent executor handles messages with attachments', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I see both images')
            ->withUsage(new Usage(15, 10)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $context = new ExecutionContext(
        messages: [
            ['role' => 'user', 'content' => 'Hello'],
        ],
        currentAttachments: [
            [
                'type' => 'image',
                'source' => 'url',
                'data' => 'https://example.com/new-image.jpg',
            ],
        ],
    );

    $response = $executor->execute($agent, 'Now look at this one', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('history attachments are preserved in messages', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I can see the history image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $context = new ExecutionContext(
        messages: [
            [
                'role' => 'user',
                'content' => 'Look at this image',
                'attachments' => [
                    [
                        'type' => 'image',
                        'source' => 'url',
                        'data' => 'https://example.com/old-image.jpg',
                    ],
                ],
            ],
        ],
    );

    $response = $executor->execute($agent, 'What did you see earlier?', $context);

    expect($response)->toBeInstanceOf(PrismResponse::class);
});

test('execution context creates with currentAttachments', function () {
    $attachments = [
        [
            'type' => 'image',
            'source' => 'url',
            'data' => 'https://example.com/image.jpg',
        ],
    ];

    $context = new ExecutionContext(currentAttachments: $attachments);

    expect($context->currentAttachments)->toBe($attachments);
    expect($context->hasCurrentAttachments())->toBeTrue();
});

test('execution context without attachments reports hasCurrentAttachments false', function () {
    $context = new ExecutionContext;

    expect($context->currentAttachments)->toBe([]);
    expect($context->hasCurrentAttachments())->toBeFalse();
});

test('execution context creates with all parameters', function () {
    $attachments = [
        ['type' => 'image', 'source' => 'url', 'data' => 'https://example.com/image.jpg'],
    ];

    $context = new ExecutionContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['key' => 'value'],
        metadata: ['meta' => 'data'],
        providerOverride: 'openai',
        modelOverride: 'gpt-4',
        currentAttachments: $attachments,
    );

    expect($context->messages)->toBe([['role' => 'user', 'content' => 'Hello']]);
    expect($context->variables)->toBe(['key' => 'value']);
    expect($context->metadata)->toBe(['meta' => 'data']);
    expect($context->providerOverride)->toBe('openai');
    expect($context->modelOverride)->toBe('gpt-4');
    expect($context->currentAttachments)->toBe($attachments);
});
