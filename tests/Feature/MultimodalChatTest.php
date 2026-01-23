<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\MediaConverter;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;

test('MediaConverter is registered as singleton', function () {
    $converter1 = app(MediaConverter::class);
    $converter2 = app(MediaConverter::class);

    expect($converter1)->toBe($converter2);
    expect($converter1)->toBeInstanceOf(MediaConverter::class);
});

test('PrismBuilder is injected with MediaConverter', function () {
    $builder = app(PrismBuilder::class);

    expect($builder)->toBeInstanceOf(PrismBuilder::class);
});

test('agent executor passes current attachments to forPrompt', function () {
    $mockResponse = createMockPrismResponse('I can see the image');

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forPrompt')
        ->with(
            Mockery::any(),
            Mockery::any(),
            Mockery::any(),
            Mockery::any(),
            Mockery::any(),
            Mockery::any(),
            Mockery::on(function ($attachments) {
                return count($attachments) === 1
                    && $attachments[0]['type'] === 'image'
                    && $attachments[0]['source'] === 'url'
                    && $attachments[0]['data'] === 'https://example.com/image.jpg';
            })
        )
        ->once()
        ->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

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

    expect($response)->toBeInstanceOf(AgentResponse::class);
    expect($response->text)->toBe('I can see the image');
});

test('agent executor merges current attachments into messages', function () {
    $mockResponse = createMockPrismResponse('I see both images');

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forMessages')
        ->with(
            Mockery::any(),
            Mockery::any(),
            Mockery::on(function ($messages) {
                // Should have 2 messages: previous and current with attachments
                if (count($messages) !== 2) {
                    return false;
                }

                // Current message should have attachments
                $currentMessage = $messages[1];

                return $currentMessage['role'] === 'user'
                    && $currentMessage['content'] === 'Now look at this one'
                    && isset($currentMessage['attachments'])
                    && count($currentMessage['attachments']) === 1
                    && $currentMessage['attachments'][0]['type'] === 'image';
            }),
            Mockery::any(),
            Mockery::any(),
            Mockery::any()
        )
        ->once()
        ->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

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

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('history attachments are passed through in messages', function () {
    $mockResponse = createMockPrismResponse('I can see both images from history');

    $mockPendingRequest = Mockery::mock();
    $mockPendingRequest->shouldReceive('withTemperature')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxSteps')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withProviderTool')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($mockResponse);

    $prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $prismBuilder->shouldReceive('forMessages')
        ->with(
            Mockery::any(),
            Mockery::any(),
            Mockery::on(function ($messages) {
                // First message should have attachments from history
                if (! isset($messages[0]['attachments'])) {
                    return false;
                }

                return $messages[0]['attachments'][0]['type'] === 'image'
                    && $messages[0]['attachments'][0]['data'] === 'https://example.com/old-image.jpg';
            }),
            Mockery::any(),
            Mockery::any(),
            Mockery::any()
        )
        ->once()
        ->andReturn($mockPendingRequest);

    app()->instance(PrismBuilderContract::class, $prismBuilder);

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

    expect($response)->toBeInstanceOf(AgentResponse::class);
});

test('execution context preserves current attachments through with methods', function () {
    $attachments = [
        [
            'type' => 'image',
            'source' => 'url',
            'data' => 'https://example.com/image.jpg',
        ],
    ];

    $context = new ExecutionContext(currentAttachments: $attachments);

    // Test withMessages preserves attachments
    $newContext = $context->withMessages([['role' => 'user', 'content' => 'Hello']]);
    expect($newContext->currentAttachments)->toBe($attachments);

    // Test withVariables preserves attachments
    $newContext = $context->withVariables(['key' => 'value']);
    expect($newContext->currentAttachments)->toBe($attachments);

    // Test withMetadata preserves attachments
    $newContext = $context->withMetadata(['meta' => 'data']);
    expect($newContext->currentAttachments)->toBe($attachments);

    // Test mergeVariables preserves attachments
    $newContext = $context->mergeVariables(['key' => 'value']);
    expect($newContext->currentAttachments)->toBe($attachments);

    // Test mergeMetadata preserves attachments
    $newContext = $context->mergeMetadata(['meta' => 'data']);
    expect($newContext->currentAttachments)->toBe($attachments);
});

test('execution context hasCurrentAttachments returns correct value', function () {
    $emptyContext = new ExecutionContext;
    expect($emptyContext->hasCurrentAttachments())->toBeFalse();

    $attachedContext = new ExecutionContext(
        currentAttachments: [
            ['type' => 'image', 'source' => 'url', 'data' => 'https://example.com/image.jpg'],
        ],
    );
    expect($attachedContext->hasCurrentAttachments())->toBeTrue();
});

test('execution context withCurrentAttachments creates new context', function () {
    $context = new ExecutionContext;
    $attachments = [
        ['type' => 'image', 'source' => 'url', 'data' => 'https://example.com/image.jpg'],
    ];

    $newContext = $context->withCurrentAttachments($attachments);

    expect($newContext)->not->toBe($context);
    expect($context->currentAttachments)->toBe([]);
    expect($newContext->currentAttachments)->toBe($attachments);
});

/**
 * Helper function to create a mock Prism response.
 */
function createMockPrismResponse(string $text): object
{
    return new class($text)
    {
        public ?string $text;

        public array $toolCalls = [];

        public object $finishReason;

        public function __construct(string $text)
        {
            $this->text = $text;
            $this->finishReason = new class
            {
                public string $value = 'stop';
            };
        }
    };
}
