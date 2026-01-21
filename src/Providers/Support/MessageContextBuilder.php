<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Builder for constructing message context for AI providers.
 *
 * Phase 1: Stub class with basic structure.
 * Full implementation will be added in Phase 3 for chat functionality.
 */
class MessageContextBuilder
{
    /**
     * The messages being built.
     *
     * @var array<int, array{role: string, content: string}>
     */
    protected array $messages = [];

    /**
     * Add a system message.
     *
     * @param  string  $content  The message content.
     */
    public function system(string $content): static
    {
        $this->messages[] = [
            'role' => 'system',
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Add a user message.
     *
     * @param  string  $content  The message content.
     */
    public function user(string $content): static
    {
        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Add an assistant message.
     *
     * @param  string  $content  The message content.
     */
    public function assistant(string $content): static
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Get all built messages.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Reset the builder.
     */
    public function reset(): static
    {
        $this->messages = [];

        return $this;
    }
}
