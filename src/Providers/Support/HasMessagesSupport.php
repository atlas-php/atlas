<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Trait for services that support message configuration.
 *
 * Provides a fluent withMessages() method for passing conversation history.
 * Messages are used to maintain context across chat interactions. Uses the
 * clone pattern for immutability.
 */
trait HasMessagesSupport
{
    /**
     * Conversation history.
     *
     * @var array<int, array{role: string, content: string}>
     */
    private array $messages = [];

    /**
     * Set conversation history messages.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): static
    {
        $clone = clone $this;
        $clone->messages = $messages;

        return $clone;
    }

    /**
     * Get the configured messages.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function getMessages(): array
    {
        return $this->messages;
    }
}
