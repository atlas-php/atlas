<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;

/**
 * Formats a message with its full execution trace for the API.
 *
 * Assembles: message → execution → steps → tool calls → assets.
 * The UI uses this to render the message, typing indicators,
 * tool call details, and media attachments.
 */
class MessageResource
{
    /**
     * Format a message with its execution trace.
     *
     * @return array<string, mixed>
     */
    public static function make(Message $msg): array
    {
        $data = [
            'id' => $msg->id,
            'role' => $msg->role->value,
            'status' => $msg->status->value,
            'content' => $msg->content,
            'author' => $msg->authorInfo(),
            'parent_id' => $msg->parent_id,
            'sequence' => $msg->sequence,
            'created_at' => $msg->created_at,
            'read_at' => $msg->read_at,
            'metadata' => $msg->metadata,
        ];

        if ($msg->isFromAssistant()) {
            $data['execution'] = self::buildExecution($msg);

            if ($msg->parent_id !== null) {
                $data['sibling_count'] = $msg->siblingCount();
                $data['sibling_index'] = $msg->siblingIndex();
            }
        }

        if ($msg->relationLoaded('attachments') && $msg->attachments->isNotEmpty()) {
            $data['attachments'] = $msg->attachments->map(fn ($att) => [
                'id' => $att->asset->id,
                'type' => $att->asset->type->value,
                'url' => $att->asset->url(),
                'mime_type' => $att->asset->mime_type,
            ])->all();
        }

        return $data;
    }

    /**
     * Build the execution trace for an assistant message.
     *
     * @return array<string, mixed>|null
     */
    protected static function buildExecution(Message $msg): ?array
    {
        $execution = Execution::where('message_id', $msg->id)->first();

        if ($execution === null) {
            return null;
        }

        $steps = $execution->steps()
            ->orderBy('sequence')
            ->with('toolCalls')
            ->get();

        return [
            'id' => $execution->id,
            'status' => $execution->status->label(),
            'provider' => $execution->provider,
            'model' => $execution->model,
            'duration_ms' => $execution->duration_ms,
            'tokens' => [
                'input' => $execution->total_input_tokens,
                'output' => $execution->total_output_tokens,
            ],
            'steps' => $steps->map(fn ($step) => [
                'id' => $step->id,
                'sequence' => $step->sequence,
                'status' => $step->status->label(),
                'finish_reason' => $step->finish_reason,
                'tokens' => [
                    'input' => $step->input_tokens,
                    'output' => $step->output_tokens,
                ],
                'duration_ms' => $step->duration_ms,
                'tool_calls' => $step->toolCalls->map(fn ($tc) => [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'arguments' => $tc->arguments,
                    'result' => $tc->result,
                    'status' => $tc->status->label(),
                    'duration_ms' => $tc->duration_ms,
                ])->all(),
            ])->all(),
        ];
    }
}
