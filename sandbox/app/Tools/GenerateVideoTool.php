<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating videos using the configured default video provider.
 *
 * Supports text-to-video generation and image-to-video with a reference image.
 * Returns an HTML video element that can be embedded in markdown.
 */
class GenerateVideoTool extends Tool
{
    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return 'Generate a short video from a text prompt. When a reference_image_url '
            .'is provided, the video will be based on that image. Returns an embeddable video player.';
    }

    /**
     * @return array<int, StringField|IntegerField>
     */
    public function parameters(): array
    {
        return [
            new StringField('prompt', 'A detailed description of the video to generate'),
            (new StringField('reference_image_url', 'URL of a reference image to animate into video (from conversation attachments)'))->optional(),
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
        $referenceUrl = $args['reference_image_url'] ?? null;
        $duration = $args['duration'] ?? 5;

        $request = Atlas::video()
            ->instructions($prompt)
            ->withDuration($duration);

        if ($referenceUrl !== null && $referenceUrl !== '') {
            $request->withMedia([Image::fromUrl($referenceUrl)]);
        }

        $response = $request->asVideo();

        if ($response->asset) {
            $url = $response->asset->url();

            return '<video autoplay muted playsinline loop src="'.$url.'"></video>';
        }

        $url = $response->url;

        return '<video autoplay muted playsinline loop src="'.$url.'"></video>';
    }
}
