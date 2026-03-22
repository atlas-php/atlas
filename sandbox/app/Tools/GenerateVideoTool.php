<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Schema\Fields\IntegerField;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating videos using the configured default video provider.
 *
 * The response carries the stored asset directly via asset.
 */
class GenerateVideoTool extends Tool
{
    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return 'Generate a short video from a text prompt. Returns a link to the video.';
    }

    /**
     * @return array<int, StringField|IntegerField>
     */
    public function parameters(): array
    {
        return [
            new StringField('prompt', 'A detailed description of the video to generate'),
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

        $response = Atlas::video()
            ->instructions($prompt)
            ->withDuration($duration)
            ->asVideo();

        if ($response->asset) {
            return "[Video: {$prompt}]({$response->asset->url()})";
        }

        return "[Video: {$prompt}]({$response->url})";
    }
}
