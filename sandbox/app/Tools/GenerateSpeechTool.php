<?php

declare(strict_types=1);

namespace App\Tools;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Tool for generating speech audio from text via xAI.
 *
 * Returns an HTML audio element that can be embedded in markdown.
 */
class GenerateSpeechTool extends Tool
{
    public function name(): string
    {
        return 'generate_speech';
    }

    public function description(): string
    {
        return 'Convert text to speech audio. Returns an embeddable audio player.';
    }

    /**
     * @return array<int, StringField>
     */
    public function parameters(): array
    {
        return [
            new StringField('text', 'The text to convert to speech'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $text = $args['text'];

        $response = Atlas::audio(Provider::xAI, 'tts-1')
            ->instructions($text)
            ->asAudio();

        if ($response->asset) {
            $url = $response->asset->url();

            return '<audio controls src="'.$url.'"></audio>';
        }

        return 'Speech audio generated but no asset was stored.';
    }
}
