<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\ValueObjects\Media\Audio;

/**
 * Contract for building Prism requests.
 *
 * This contract allows for flexible mocking in tests while maintaining
 * type safety in production code.
 */
interface PrismBuilderContract
{
    /**
     * Build an embeddings request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string|array<string>  $input  Single text or array of texts.
     * @param  array<string, mixed>  $options  Additional options (dimensions, encoding_format, etc.).
     */
    public function forEmbeddings(string $provider, string $model, string|array $input, array $options = []): mixed;

    /**
     * Build an image generation request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $prompt  The image prompt.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forImage(string $provider, string $model, string $prompt, array $options = []): mixed;

    /**
     * Build a text-to-speech request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  string  $text  The text to convert.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forSpeech(string $provider, string $model, string $text, array $options = []): mixed;

    /**
     * Build a speech-to-text (transcription) request.
     *
     * @param  string  $provider  The provider name.
     * @param  string  $model  The model name.
     * @param  Audio  $audio  The audio to transcribe.
     * @param  array<string, mixed>  $options  Additional options.
     */
    public function forTranscription(string $provider, string $model, Audio $audio, array $options = []): mixed;

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
    ): mixed;

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
    ): mixed;

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
    ): mixed;
}
