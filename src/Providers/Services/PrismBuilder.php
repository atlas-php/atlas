<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Prism\Prism\Audio\PendingRequest as AudioPendingRequest;
use Prism\Prism\Embeddings\PendingRequest as EmbeddingsPendingRequest;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Images\PendingRequest as ImagePendingRequest;
use Prism\Prism\ValueObjects\Media\Audio;

/**
 * Builder for creating Prism requests across all modalities.
 *
 * Internal service used by capability services to build API requests.
 * Provides methods for embeddings, images, and speech operations.
 */
class PrismBuilder implements PrismBuilderContract
{
    /**
     * Build an embeddings request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string|array<string>  $input  Single text or array of texts.
     */
    public function forEmbeddings(
        string $provider,
        string $model,
        string|array $input,
    ): EmbeddingsPendingRequest {
        $request = Prism::embeddings()
            ->using($this->mapProvider($provider), $model);

        if (is_array($input)) {
            return $request->fromArray($input);
        }

        return $request->fromInput($input);
    }

    /**
     * Build an image generation request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $prompt  The image prompt.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forImage(
        string $provider,
        string $model,
        string $prompt,
        array $options = [],
    ): ImagePendingRequest {
        return Prism::image()
            ->using($this->mapProvider($provider), $model)
            ->withPrompt($prompt);
    }

    /**
     * Build a text-to-speech request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forSpeech(
        string $provider,
        string $model,
        string $text,
        array $options = [],
    ): AudioPendingRequest {
        $request = Prism::audio()
            ->using($this->mapProvider($provider), $model)
            ->withInput($text);

        if (isset($options['voice'])) {
            $request = $request->withVoice($options['voice']);
        }

        return $request;
    }

    /**
     * Build a speech-to-text (transcription) request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  Audio  $audio  The audio to transcribe.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forTranscription(
        string $provider,
        string $model,
        Audio $audio,
        array $options = [],
    ): AudioPendingRequest {
        return Prism::audio()
            ->using($this->mapProvider($provider), $model)
            ->withInput($audio);
    }

    /**
     * Map provider name to Prism Provider enum.
     */
    protected function mapProvider(string $provider): Provider
    {
        return Provider::from($provider);
    }
}
