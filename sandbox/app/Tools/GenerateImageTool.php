<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating images using the configured default image provider.
 *
 * The response carries the stored asset directly via asset.
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
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $prompt = $args['prompt'];

        $response = Atlas::image()
            ->instructions($prompt)
            ->asImage();

        if ($response->asset) {
            return "![{$prompt}]({$response->asset->url()})";
        }

        $url = is_array($response->url) ? $response->url[0] : $response->url;

        return "![{$prompt}]({$url})";
    }
}
