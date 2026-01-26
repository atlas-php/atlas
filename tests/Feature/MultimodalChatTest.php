<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\MediaConverter;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Usage;

test('MediaConverter is registered as singleton', function () {
    $converter1 = app(MediaConverter::class);
    $converter2 = app(MediaConverter::class);

    expect($converter1)->toBe($converter2);
    expect($converter1)->toBeInstanceOf(MediaConverter::class);
});

test('agent executor handles prism media attachments', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I can see the image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $image = Image::fromUrl('https://example.com/image.jpg');

    $context = new AgentContext(
        prismMedia: [$image],
    );

    $response = $executor->execute($agent, 'What do you see?', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('I can see the image');
});

test('agent executor handles messages with prism media', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I see both images')
            ->withUsage(new Usage(15, 10)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $image = Image::fromUrl('https://example.com/new-image.jpg');

    $context = new AgentContext(
        messages: [
            ['role' => 'user', 'content' => 'Hello'],
        ],
        prismMedia: [$image],
    );

    $response = $executor->execute($agent, 'Now look at this one', $context);

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('history attachments are preserved in messages', function () {
    Prism::fake([
        TextResponseFake::make()
            ->withText('I can see the history image')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;

    $context = new AgentContext(
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

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('execution context creates with prismMedia', function () {
    $image = Image::fromUrl('https://example.com/image.jpg');

    $context = new AgentContext(prismMedia: [$image]);

    expect($context->prismMedia)->toBe([$image]);
    expect($context->hasAttachments())->toBeTrue();
});

test('execution context without attachments reports hasAttachments false', function () {
    $context = new AgentContext;

    expect($context->prismMedia)->toBe([]);
    expect($context->hasAttachments())->toBeFalse();
});

test('execution context creates with all parameters', function () {
    $image = Image::fromUrl('https://example.com/image.jpg');

    $context = new AgentContext(
        messages: [['role' => 'user', 'content' => 'Hello']],
        variables: ['key' => 'value'],
        metadata: ['meta' => 'data'],
        providerOverride: 'openai',
        modelOverride: 'gpt-4',
        prismMedia: [$image],
    );

    expect($context->messages)->toBe([['role' => 'user', 'content' => 'Hello']]);
    expect($context->variables)->toBe(['key' => 'value']);
    expect($context->metadata)->toBe(['meta' => 'data']);
    expect($context->providerOverride)->toBe('openai');
    expect($context->modelOverride)->toBe('gpt-4');
    expect($context->prismMedia)->toBe([$image]);
});
