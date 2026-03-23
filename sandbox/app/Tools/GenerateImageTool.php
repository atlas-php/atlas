<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating or editing images using the configured default image provider.
 *
 * Supports text-to-image generation and image editing with a reference image.
 */
class GenerateImageTool extends Tool
{
    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return 'Generate or edit an image. When a reference_image_url is provided, '
            .'the image will be edited based on the prompt. Otherwise, a new image '
            .'is generated from scratch. Returns a markdown image tag.';
    }

    /**
     * @return array<int, StringField>
     */
    public function parameters(): array
    {
        return [
            new StringField('prompt', 'A detailed description of the image to generate or how to edit the reference image'),
            (new StringField('reference_image_url', 'URL of a reference image to edit (from conversation attachments)'))->optional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $prompt = $args['prompt'];
        $referenceUrl = $args['reference_image_url'] ?? null;

        $request = Atlas::image()->instructions($prompt);

        if ($referenceUrl !== null && $referenceUrl !== '') {
            $request->withMedia([Image::fromUrl($referenceUrl)]);
        }

        $response = $request->asImage();

        if ($response->asset) {
            return "Result:\n![{$prompt}]({$response->asset->url()})";
        }

        $url = is_array($response->url) ? $response->url[0] : $response->url;

        return "Result:\n![{$prompt}]({$url})";
    }
}
