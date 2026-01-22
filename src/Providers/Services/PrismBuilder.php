<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Prism\Prism\Audio\PendingRequest as AudioPendingRequest;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Embeddings\PendingRequest as EmbeddingsPendingRequest;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Images\PendingRequest as ImagePendingRequest;
use Prism\Prism\Structured\PendingRequest as StructuredPendingRequest;
use Prism\Prism\Text\PendingRequest as TextPendingRequest;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Builder for creating Prism requests across all modalities.
 *
 * Internal service used by capability services to build API requests.
 * Provides methods for embeddings, images, speech, and text operations.
 */
class PrismBuilder implements PrismBuilderContract
{
    /**
     * Build an embeddings request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string|array<string>  $input  Single text or array of texts.
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     */
    public function forEmbeddings(
        string $provider,
        string $model,
        string|array $input,
        array $options = [],
    ): EmbeddingsPendingRequest {
        $request = Prism::embeddings()
            ->using($this->mapProvider($provider), $model);

        if (is_array($input)) {
            $request = $request->fromArray($input);
        } else {
            $request = $request->fromInput($input);
        }

        if ($options !== []) {
            $request = $request->withProviderOptions($options);
        }

        return $request;
    }

    /**
     * Build an image generation request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $prompt  The image prompt.
     * @param  array<string, mixed>  $options  Additional options (size, quality, style, etc.).
     */
    public function forImage(
        string $provider,
        string $model,
        string $prompt,
        array $options = [],
    ): ImagePendingRequest {
        $request = Prism::image()
            ->using($this->mapProvider($provider), $model)
            ->withPrompt($prompt);

        if ($options !== []) {
            $request = $request->withProviderOptions($options);
        }

        return $request;
    }

    /**
     * Build a text-to-speech request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Additional options (voice, speed, language, etc.).
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

        // Apply voice via dedicated method
        if (isset($options['voice'])) {
            $request = $request->withVoice($options['voice']);
            unset($options['voice']);
        }

        // Pass remaining options (speed, language, etc.) to provider
        if ($options !== []) {
            $request = $request->withProviderOptions($options);
        }

        return $request;
    }

    /**
     * Build a speech-to-text (transcription) request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  Audio  $audio  The audio to transcribe.
     * @param  array<string, mixed>  $options  Additional options (language, prompt, etc.).
     */
    public function forTranscription(
        string $provider,
        string $model,
        Audio $audio,
        array $options = [],
    ): AudioPendingRequest {
        $request = Prism::audio()
            ->using($this->mapProvider($provider), $model)
            ->withInput($audio);

        if ($options !== []) {
            $request = $request->withProviderOptions($options);
        }

        return $request;
    }

    /**
     * Build a text request for a single prompt.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $input  The user input.
     * @param  string  $systemPrompt  The system prompt.
     * @param  array<int, mixed>  $tools  Optional tools.
     */
    public function forPrompt(
        string $provider,
        string $model,
        string $input,
        string $systemPrompt,
        array $tools = [],
    ): TextPendingRequest {
        $request = Prism::text()
            ->using($this->mapProvider($provider), $model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($input);

        if ($tools !== []) {
            $request = $request->withTools($tools);
        }

        return $request;
    }

    /**
     * Build a text request for multi-turn conversation.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  array<int, array{role: string, content: string}>  $messages  The conversation messages.
     * @param  string  $systemPrompt  The system prompt.
     * @param  array<int, mixed>  $tools  Optional tools.
     */
    public function forMessages(
        string $provider,
        string $model,
        array $messages,
        string $systemPrompt,
        array $tools = [],
    ): TextPendingRequest {
        $request = Prism::text()
            ->using($this->mapProvider($provider), $model)
            ->withSystemPrompt($systemPrompt)
            ->withMessages($this->convertMessages($messages));

        if ($tools !== []) {
            $request = $request->withTools($tools);
        }

        return $request;
    }

    /**
     * Build a structured output request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  Schema  $schema  The output schema.
     * @param  string  $input  The user input.
     * @param  string  $systemPrompt  The system prompt.
     */
    public function forStructured(
        string $provider,
        string $model,
        Schema $schema,
        string $input,
        string $systemPrompt,
    ): StructuredPendingRequest {
        return Prism::structured()
            ->using($this->mapProvider($provider), $model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($input)
            ->withSchema($schema);
    }

    /**
     * Convert message arrays to Prism message objects.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, UserMessage|AssistantMessage|SystemMessage>
     */
    protected function convertMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $message) {
            $converted[] = match ($message['role']) {
                'user' => new UserMessage($message['content']),
                'assistant' => new AssistantMessage($message['content']),
                'system' => new SystemMessage($message['content']),
                default => throw new \InvalidArgumentException(
                    sprintf('Unknown message role: %s. Valid roles are: user, assistant, system.', $message['role'])
                ),
            };
        }

        return $converted;
    }

    /**
     * Map provider name to Prism Provider enum.
     */
    protected function mapProvider(string $provider): Provider
    {
        return Provider::from($provider);
    }
}
