<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating videos.
 *
 * When the model sets reference_last_image to true, the tool
 * finds the last image the user shared and uses it as a
 * reference frame for the video.
 */
class GenerateVideoTool extends Tool
{
    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return 'Generate a short video from a text prompt. '
            .'Set reference_last_image to true to animate an image '
            .'the user previously shared in the conversation.';
    }

    /**
     * @return array<int, mixed>
     */
    public function parameters(): array
    {
        return [
            Schema::string('prompt', 'A detailed description of the video to generate'),
            Schema::boolean('reference_last_image', 'Set to true to use the last image the user shared as a reference frame')->optional(),
            (new IntegerField('duration', 'Video duration in seconds (default: 5)'))->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $prompt = $args['prompt'];
        $duration = $args['duration'] ?? 5;

        $request = Atlas::video()
            ->instructions($prompt)
            ->withDuration($duration);

        // Use the last user-uploaded image as reference if requested
        if ($args['reference_last_image'] ?? false) {
            $conversationId = $context['conversation_id'] ?? null;

            if ($conversationId !== null) {
                $attachment = MessageAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conversationId)->where('role', MessageRole::User))
                    ->whereHas('asset', fn ($q) => $q->where('type', AssetType::Image))
                    ->with('asset')
                    ->latest('id')
                    ->first();

                if ($attachment?->asset) {
                    try {
                        $request->withMedia([Image::fromStorage($attachment->asset->path, $attachment->asset->disk, $attachment->asset->mime_type)]);
                    } catch (\RuntimeException) {
                        // Provider may not support reference images
                    }
                }
            }
        }

        $response = $request->asVideo();

        if ($response->asset) {
            return '<video autoplay muted playsinline loop src="'.$response->asset->url().'"></video>';
        }

        return '<video autoplay muted playsinline loop src="'.$response->url.'"></video>';
    }
}
