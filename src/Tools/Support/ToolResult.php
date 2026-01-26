<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Support;

/**
 * Result returned from tool execution.
 *
 * Encapsulates the tool output and tracks error status.
 * Use toText() for the string representation sent to the AI.
 * Use toArray() to access structured data when available.
 */
final readonly class ToolResult
{
    /**
     * @param  string|array<mixed>  $data  The result data (string or array).
     * @param  bool  $isError  Whether this represents an error.
     */
    public function __construct(
        private string|array $data,
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
     * Create a result from array data.
     *
     * @param  array<mixed>  $data  The structured data.
     */
    public static function json(array $data): self
    {
        return new self($data, false);
    }

    /**
     * Get the result as a string.
     *
     * Returns the text directly if stored as string,
     * or JSON encodes if stored as array.
     *
     * @throws \JsonException
     */
    public function toText(): string
    {
        if (is_array($this->data)) {
            return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        }

        return $this->data;
    }

    /**
     * Get the result as an array.
     *
     * Returns the array directly if stored as array,
     * or wraps string in ['text' => $data] for consistency.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        if (is_array($this->data)) {
            return $this->data;
        }

        return ['text' => $this->data];
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
}
