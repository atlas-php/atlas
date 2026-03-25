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
 * @param  array<int, Message>  $storedMessages
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

it('attaches tool-generated asset to assistant message via step_id', function () {
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
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall->id,
            'tool_name' => 'generate_image',
            'provider' => 'openai',
            'model' => 'dall-e-3',
        ],
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
    expect($attachment->metadata['tool_call_id'])->toBe($toolCall->id);
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
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall1->id,
            'tool_name' => 'generate_image',
        ],
    ]);

    Asset::factory()->audio()->create([
        'execution_id' => $execution->id,
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall2->id,
            'tool_name' => 'generate_audio',
        ],
    ]);

    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
        'content' => 'Here are your files',
    ]);

    callAttachToolAssets($execution, [$message]);

    expect(ConversationMessageAsset::count())->toBe(2);
});

it('does not attach assets without tool_execution source', function () {
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
        'name' => 'some_tool',
    ]);

    // Asset with a different source — should NOT be attached
    Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'metadata' => [
            'source' => 'atlas_execution',
            'tool_call_id' => $toolCall->id,
        ],
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
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'name' => 'generate_image',
    ]);

    // Asset belongs to a DIFFERENT execution
    Asset::factory()->image()->create([
        'execution_id' => $otherExecution->id,
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall->id,
            'tool_name' => 'generate_image',
        ],
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
        'sequence' => 0,
    ]);

    $toolCall1 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step1->id,
        'name' => 'generate_image',
    ]);

    $asset1 = Asset::factory()->image()->create([
        'execution_id' => $execution->id,
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall1->id,
            'tool_name' => 'generate_image',
        ],
    ]);

    // Step 2 with a different tool call and asset
    $step2 = ExecutionStep::factory()->withToolCalls()->create([
        'execution_id' => $execution->id,
        'sequence' => 1,
    ]);

    $toolCall2 = ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step2->id,
        'name' => 'generate_audio',
    ]);

    $asset2 = Asset::factory()->audio()->create([
        'execution_id' => $execution->id,
        'metadata' => [
            'source' => 'tool_execution',
            'tool_call_id' => $toolCall2->id,
            'tool_name' => 'generate_audio',
        ],
    ]);

    // Single assistant message (final response) — all assets attach to it
    $message = ConversationMessage::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step2->id,
        'sequence' => 1,
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
