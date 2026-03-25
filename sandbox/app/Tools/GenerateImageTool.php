<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating or editing images.
 *
 * When the model sets reference_last_image to true, the tool
 * finds the last image the user shared in the conversation
 * and uses it as a reference for editing.
 */
class GenerateImageTool extends Tool
{
    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate a new image from a text prompt, or edit a reference image. '
            .'Set reference_last_image to true when the user wants to edit or modify '
            .'an image they previously shared in the conversation.';
    }

    public function parameters(): array
    {
        return [
            Schema::string('prompt', 'A detailed description of the image to generate or how to edit the reference image'),
            Schema::boolean('reference_last_image', 'Set to true to use the last image the user shared as a reference for editing')->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $prompt = $args['prompt'];
        $request = Atlas::image()->instructions($prompt);

        // Use the last user-uploaded image as reference if requested
        if ($args['reference_last_image'] ?? false) {
            $conversationId = $context['conversation_id'] ?? null;

            if ($conversationId !== null) {
                $attachment = ConversationMessageAsset::whereHas('message', fn ($q) => $q->where('conversation_id', $conversationId)->where('role', MessageRole::User))
                    ->whereHas('asset', fn ($q) => $q->where('type', AssetType::Image))
                    ->with('asset')
                    ->latest('id')
                    ->first();

                if ($attachment?->asset) {
                    try {
                        $request->withMedia([Image::fromStorage($attachment->asset->path, $attachment->asset->disk, $attachment->asset->mime_type)]);
                    } catch (\RuntimeException) {
                        // Provider may not support reference images — generate from scratch
                    }
                }
            }
        }

        $response = $request->asImage();

        if ($response->asset) {
            return "Image generated successfully. Display it with: ![image]({$response->asset->url()})";
        }

        $url = is_array($response->url) ? $response->url[0] : $response->url;

        return "Image generated successfully. Display it with: ![image]({$url})";
    }
}
