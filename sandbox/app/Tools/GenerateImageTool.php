<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\ToolAssets;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating images using the configured default image provider.
 *
 * Asset storage is handled automatically by TrackProviderCall middleware.
 * Uses ToolAssets::lastStored() to get the stored asset for the proxy URL.
 */
class GenerateImageTool extends Tool
{
    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate an image from a text prompt using AI. Returns a markdown image tag.';
    }

    /**
     * @return array<int, StringField>
     */
    public function parameters(): array
    {
        return [
            new StringField('prompt', 'A detailed description of the image to generate'),
            (new StringField('size', 'Image dimensions (e.g. 1024x1024, 1024x768)'))->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $prompt = $args['prompt'];
        $size = $args['size'] ?? '1024x1024';

        Atlas::image()
            ->instructions($prompt)
            ->withSize($size)
            ->asImage();

        $asset = ToolAssets::lastStored();

        return $asset
            ? "![{$prompt}](/api/assets/{$asset->id})"
            : "Image generated for: {$prompt}";
    }
}
