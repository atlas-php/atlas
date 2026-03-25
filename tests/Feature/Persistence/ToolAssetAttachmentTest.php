<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;

/**
 * Helper to call the protected attachToolAssets method via reflection.
 *
 * @param  array<int, ConversationMessage>  $storedMessages
 */
function callAttachToolAssets(Execution $execution, array $storedMessages): void
{
    $middleware = new PersistConversation(
        app(ConversationService::class),
        app(ExecutionService::class),
    );

    $method = new ReflectionMethod($middleware, 'attachToolAssets');
    $method->invoke($middleware, $execution, $storedMessages);
}

it('attaches tool-generated asset to assistant message via tool_call_id column', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    $step = ExecutionStep::factory()->completed()->create([
        'execution_id' => $execution->id,
    ]);

    $toolCall = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'name' => 'generate_image',
    ]);

    $asset = Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall->id,
        'metadata' => null,
    ]);

    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
        'content' => 'Here is your image',
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(1);

    $attachment = ConversationMessageAsset::first();
    expect($attachment->message_id)->toBe($message->id);
    expect($attachment->asset_id)->toBe($asset->id);
    expect($attachment->metadata['tool_call_id'])->toBe($toolCall->tool_call_id);
    expect($attachment->metadata['tool_name'])->toBe('generate_image');
});

it('handles multiple assets from different tool calls in same step', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    $step = ExecutionStep::factory()->withToolCalls()->create([
        'execution_id' => $execution->id,
    ]);

    $toolCall1 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'name' => 'generate_image',
    ]);

    $toolCall2 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'name' => 'generate_audio',
    ]);

    Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall1->id,
        'metadata' => null,
    ]);

    Asset::factory()->audio()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall2->id,
        'metadata' => null,
    ]);

    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
        'content' => 'Here are your files',
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(2);
});

it('does not attach assets without tool_call_id', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    $step = ExecutionStep::factory()->completed()->create([
        'execution_id' => $execution->id,
    ]);

    // Asset without tool_call_id — should NOT be attached (direct modality call)
    Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => null,
        'metadata' => null,
    ]);

    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(0);
});

it('does not attach assets from different execution', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    $otherExecution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Image,
    ]);

    $step = ExecutionStep::factory()->completed()->create([
        'execution_id' => $execution->id,
    ]);

    $toolCall = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $otherExecution->id,
        'step_id' => $step->id,
        'name' => 'generate_image',
    ]);

    // Asset belongs to a DIFFERENT execution
    Asset::factory()->image()->create([
        'execution_id' => $otherExecution->id,
        'tool_call_id' => $toolCall->id,
        'metadata' => null,
    ]);

    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(0);
});

it('attaches to correct message when multiple steps exist', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    // Step 1 with a tool call and asset
    $step1 = ExecutionStep::factory()->withToolCalls()->create([
        'execution_id' => $execution->id,
        'sequence' => 1,
    ]);

    $toolCall1 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step1->id,
        'name' => 'generate_image',
    ]);

    $asset1 = Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall1->id,
        'metadata' => null,
    ]);

    // Step 2 with a different tool call and asset
    $step2 = ExecutionStep::factory()->withToolCalls()->create([
        'execution_id' => $execution->id,
        'sequence' => 2,
    ]);

    $toolCall2 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step2->id,
        'name' => 'generate_audio',
    ]);

    $asset2 = Asset::factory()->audio()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall2->id,
        'metadata' => null,
    ]);

    // Single assistant message (final response) — all assets attach to it
    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step2->id,
        'sequence' => 2,
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(2);

    // Both assets attached to the single assistant message
    $attachments = ConversationMessageAsset::where('message_id', $message->id)
        ->orderBy('id')
        ->get();

    expect($attachments[0]->asset_id)->toBe($asset1->id);
    expect($attachments[1]->asset_id)->toBe($asset2->id);
});

it('stores consumer metadata only in asset metadata column', function () {
    $conversation = Conversation::factory()->create();

    $execution = Execution::factory()->processing()->create([
        'type' => ExecutionType::Text,
    ]);

    $step = ExecutionStep::factory()->completed()->create([
        'execution_id' => $execution->id,
    ]);

    $toolCall = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'name' => 'generate_report',
    ]);

    // Asset with consumer metadata and tool_call_id column
    $asset = Asset::factory()->create([
        'execution_id' => $execution->id,
        'tool_call_id' => $toolCall->id,
        'metadata' => ['custom_key' => 'consumer_value'],
    ]);

    // Metadata should only contain consumer data, no internal keys
    expect($asset->metadata)->toBe(['custom_key' => 'consumer_value']);
    expect($asset->metadata)->not->toHaveKey('source');
    expect($asset->metadata)->not->toHaveKey('tool_call_id');
    expect($asset->metadata)->not->toHaveKey('tool_name');
    expect($asset->metadata)->not->toHaveKey('provider');
    expect($asset->metadata)->not->toHaveKey('model');

    // Tool call info is in the relationship, not metadata
    expect($asset->tool_call_id)->toBe($toolCall->id);
    expect($asset->toolCall->name)->toBe('generate_report');
});
