<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;

/**
 * Contract for agent execution implementations.
 *
 * Defines the interface for executing agents with conversation context.
 * Supports both blocking and streaming execution modes.
 *
 * Returns AgentResponse which wraps Prism's response with agent context:
 * - response->text - Text response (via __get magic)
 * - response->usage - Full usage stats including cache tokens, thought tokens
 * - response->steps - Multi-step agentic loop history
 * - response->toolCalls - Tool calls as ToolCall objects
 * - response->finishReason - Typed FinishReason enum
 * - response->meta - Request metadata, rate limits
 * - response->agentKey() - The agent key
 * - response->systemPrompt - The system prompt used
 * - response->metadata() - Pipeline metadata
 *
 * When withSchema() is used, response->isStructured() returns true and
 * response->structured() provides the extracted data.
 *
 * All Prism-specific configuration (schema, retry, structuredMode, toolChoice, etc.)
 * is captured in the AgentContext's prismCalls and replayed on the request.
 */
interface AgentExecutorContract
{
    /**
     * Execute an agent with the given input.
     *
     * Returns AgentResponse wrapping Prism's response with agent context.
     * Backward compatible property access works via __get magic.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  AgentContext  $context  Execution context with messages, variables, and Prism calls.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentResponse;

    /**
     * Stream a response from an agent.
     *
     * Returns AgentStreamResponse which implements IteratorAggregate.
     * Agent context is available before iteration; events are collected
     * during iteration and available after via events().
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  AgentContext  $context  Execution context with messages, variables, and Prism calls.
     */
    public function stream(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentStreamResponse;
}
