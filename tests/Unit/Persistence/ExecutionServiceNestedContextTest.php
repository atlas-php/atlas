<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\Usage;

beforeEach(function () {
    $this->service = app(ExecutionService::class);
});

/** Drive a parent execution up to an active delegation tool call. */
function startParentWithDelegationCall(ExecutionService $service): array
{
    $parent = $service->createExecution(provider: 'openai', model: 'gpt-4o', agent: 'orchestrator');
    $service->beginExecution();
    $service->createStep();
    $service->beginStep();

    $toolCall = new ToolCall(id: 'call_1', name: 'specialist', arguments: ['task' => 'do it']);
    $delegation = $service->createToolCall($toolCall, ToolCallType::Agent);
    $service->beginToolCall($delegation);

    return [$parent, $delegation];
}

it('links a nested execution to its parent and delegating tool call', function () {
    [$parent, $delegation] = startParentWithDelegationCall($this->service);

    $child = $this->service->createExecution(provider: 'openai', model: 'gpt-4o-mini', agent: 'specialist');

    expect($child->parent_execution_id)->toBe($parent->id)
        ->and($child->parent_tool_call_id)->toBe($delegation->id)
        ->and($child->depth)->toBe(1)
        // The child is now the active execution.
        ->and($this->service->getExecution()->id)->toBe($child->id);
});

it('restores the parent context after the child completes', function () {
    [$parent, $delegation] = startParentWithDelegationCall($this->service);

    $this->service->createExecution(provider: 'openai', model: 'gpt-4o-mini', agent: 'specialist');
    $this->service->beginExecution();
    $this->service->completeExecution(new Usage(inputTokens: 5, outputTokens: 7));

    // Parent execution, step, and the delegating tool call are restored.
    expect($this->service->getExecution()->id)->toBe($parent->id)
        ->and($this->service->getCurrentToolCall()?->id)->toBe($delegation->id)
        ->and($this->service->currentStep())->not->toBeNull();
});

it('restores the parent context when the child fails', function () {
    [$parent] = startParentWithDelegationCall($this->service);

    $this->service->createExecution(provider: 'openai', model: 'gpt-4o-mini', agent: 'specialist');
    $this->service->beginExecution();
    $this->service->failExecution(new RuntimeException('child boom'));

    expect($this->service->getExecution()->id)->toBe($parent->id);
});

it('tracks multi-level nesting and unwinds in order', function () {
    [$parent, $delegation] = startParentWithDelegationCall($this->service);

    // Level 1 child, itself delegating.
    $child = $this->service->createExecution(provider: 'openai', model: 'gpt-4o', agent: 'specialist');
    $this->service->beginExecution();
    $this->service->createStep();
    $this->service->beginStep();
    $childCall = $this->service->createToolCall(
        new ToolCall(id: 'call_2', name: 'researcher', arguments: ['task' => 'dig']),
        ToolCallType::Agent,
    );
    $this->service->beginToolCall($childCall);

    // Level 2 grandchild.
    $grandchild = $this->service->createExecution(provider: 'openai', model: 'gpt-4o-mini', agent: 'researcher');

    expect($grandchild->parent_execution_id)->toBe($child->id)
        ->and($grandchild->parent_tool_call_id)->toBe($childCall->id)
        ->and($grandchild->depth)->toBe(2);

    // Unwind grandchild → child restored.
    $this->service->beginExecution();
    $this->service->completeExecution();
    expect($this->service->getExecution()->id)->toBe($child->id);

    // Unwind child → parent restored.
    $this->service->completeExecution();
    expect($this->service->getExecution()->id)->toBe($parent->id)
        ->and($this->service->getCurrentToolCall()?->id)->toBe($delegation->id);
});

it('totalUsage sums the whole subtree across multiple children and grandchildren', function () {
    $make = function (array $usage, ?int $parentId, int $depth): Execution {
        return Execution::create([
            'parent_execution_id' => $parentId,
            'depth' => $depth,
            'type' => ExecutionType::Text,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'status' => ExecutionStatus::Completed,
            'usage' => $usage,
        ]);
    };

    $root = $make(['input_tokens' => 10, 'output_tokens' => 5], null, 0);
    $childA = $make(['input_tokens' => 3, 'output_tokens' => 2], $root->id, 1);
    $make(['input_tokens' => 4, 'output_tokens' => 1], $root->id, 1);       // childB
    $make(['input_tokens' => 2, 'output_tokens' => 2], $childA->id, 2);     // grandchild under A

    $total = $root->fresh()->totalUsage();

    // input: 10+3+4+2 = 19 · output: 5+2+1+2 = 10 · total: 29
    expect($total['input_tokens'])->toBe(19)
        ->and($total['output_tokens'])->toBe(10)
        ->and($total['total_tokens'])->toBe(29);
});

it('strips internal _-prefixed meta keys from stored execution metadata', function () {
    $execution = $this->service->createExecution(
        provider: 'openai',
        model: 'gpt-4o',
        meta: ['_atlas_delegation_depth' => 2, '_atlas_delegation_chain' => ['a'], 'tenant_id' => 7],
    );

    expect($execution->metadata)->toBe(['tenant_id' => 7]);
});

it('restores the parent context after a nested completeDirectExecution', function () {
    [$parent, $delegation] = startParentWithDelegationCall($this->service);

    // A nested direct-mode call (e.g. a sub-agent image/embed) finalises via completeDirectExecution.
    $this->service->createExecution(provider: 'openai', model: 'gpt-image-1', agent: 'image-sub');
    $this->service->completeDirectExecution(new Usage(inputTokens: 1, outputTokens: 1));

    expect($this->service->getExecution()->id)->toBe($parent->id)
        ->and($this->service->getCurrentToolCall()?->id)->toBe($delegation->id);
});
