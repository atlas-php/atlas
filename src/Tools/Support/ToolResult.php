<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Result returned from tool execution.
 *
 * Encapsulates the tool output as text and tracks error status.
 * The text is returned to the AI as the tool response.
 */
final readonly class ToolResult
{
    /**
     * @param  string  $text  The result text to return to the AI.
     * @param  bool  $isError  Whether this represents an error.
     */
    public function __construct(
        public string $text,
        public bool $isError = false,
    ) {}

    /**
     * Create a successful text result.
     *
     * @param  string  $text  The result text.
     */
    public static function text(string $text): self
    {
        return new self($text, false);
    }

    /**
     * Create an error result.
     *
     * @param  string  $message  The error message.
     */
    public static function error(string $message): self
    {
        return new self($message, true);
    }

    /**
     * Create a JSON result from array data.
     *
     * @param  array<mixed>  $data  The data to encode as JSON.
     */
    public static function json(array $data): self
    {
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException $e) {
            return self::error('Failed to encode tool result as JSON: '.$e->getMessage());
        }

        return new self($encoded, false);
    }

    /**
     * Check if this result is an error.
     */
    public function failed(): bool
    {
        return $this->isError;
    }

    /**
     * Check if this result is successful.
     */
    public function succeeded(): bool
    {
        return ! $this->isError;
    }

    /**
     * Convert the result to an array.
     *
     * @return array{text: string, is_error: bool}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'is_error' => $this->isError,
        ];
    }
}
