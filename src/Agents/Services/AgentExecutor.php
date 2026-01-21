<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Exceptions\AgentException;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\UsageExtractorRegistry;
use Atlasphp\Atlas\Tools\Services\ToolBuilder;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\ValueObjects\ProviderTool;
use Throwable;

/**
 * Executes agents using the Prism AI abstraction layer.
 *
 * Orchestrates system prompt building, tool preparation, and
 * Prism request execution with pipeline middleware support.
 */
class AgentExecutor implements AgentExecutorContract
{
    public function __construct(
        protected PrismBuilderContract $prismBuilder,
        protected ToolBuilder $toolBuilder,
        protected SystemPromptBuilder $systemPromptBuilder,
        protected PipelineRunner $pipelineRunner,
        protected UsageExtractorRegistry $usageExtractor,
    ) {}

    /**
     * Execute an agent with the given input.
     *
     * @param  AgentContract  $agent  The agent to execute.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext|null  $context  Optional execution context.
     * @param  Schema|null  $schema  Optional schema for structured output.
     */
    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
    ): AgentResponse {
        // Create default context if not provided
        $context = $context ?? new ExecutionContext;

        try {
            // Run before_execute pipeline
            $beforeData = [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'schema' => $schema,
            ];

            /** @var array{agent: AgentContract, input: string, context: ExecutionContext, schema: Schema|null} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'agent.before_execute',
                $beforeData,
            );

            $agent = $beforeData['agent'];
            $input = $beforeData['input'];
            $context = $beforeData['context'];
            $schema = $beforeData['schema'];

            // Build system prompt
            $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

            // Execute the request
            $response = $this->executeRequest($agent, $input, $context, $systemPrompt, $schema);

            // Run after_execute pipeline
            $afterData = [
                'agent' => $agent,
                'input' => $input,
                'context' => $context,
                'response' => $response,
            ];

            /** @var array{response: AgentResponse} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'agent.after_execute',
                $afterData,
            );

            return $afterData['response'];
        } catch (AgentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw AgentException::executionFailed($agent->key(), $e->getMessage());
        }
    }

    /**
     * Execute the Prism request based on whether we have a schema.
     */
    protected function executeRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        string $systemPrompt,
        ?Schema $schema,
    ): AgentResponse {
        if ($schema !== null) {
            return $this->executeStructuredRequest($agent, $input, $context, $systemPrompt, $schema);
        }

        return $this->executeTextRequest($agent, $input, $context, $systemPrompt);
    }

    /**
     * Execute a text (non-structured) request.
     */
    protected function executeTextRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        string $systemPrompt,
    ): AgentResponse {
        // Build tools
        $toolContext = new ToolContext($context->metadata);
        $tools = $this->toolBuilder->buildForAgent($agent, $toolContext);

        // Build Prism request
        $request = $context->hasMessages()
            ? $this->prismBuilder->forMessages(
                $agent->provider(),
                $agent->model(),
                $this->buildMessages($context, $input),
                $systemPrompt,
                $tools,
            )
            : $this->prismBuilder->forPrompt(
                $agent->provider(),
                $agent->model(),
                $input,
                $systemPrompt,
                $tools,
            );

        // Apply agent settings
        $request = $this->applyAgentSettings($request, $agent);

        // Execute
        $prismResponse = $request->asText();

        // Build response
        return $this->buildResponse($agent, $prismResponse);
    }

    /**
     * Execute a structured output request.
     */
    protected function executeStructuredRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        string $systemPrompt,
        Schema $schema,
    ): AgentResponse {
        // Build Prism request for structured output
        $request = $this->prismBuilder->forStructured(
            $agent->provider(),
            $agent->model(),
            $schema,
            $context->hasMessages() ? $this->combineMessagesWithInput($context, $input) : $input,
            $systemPrompt,
        );

        // Apply agent settings
        $request = $this->applyAgentSettings($request, $agent);

        // Execute
        $prismResponse = $request->asStructured();

        // Build response with structured data
        return $this->buildStructuredResponse($agent, $prismResponse);
    }

    /**
     * Build the messages array for multi-turn conversation.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(ExecutionContext $context, string $input): array
    {
        $messages = $context->messages;

        // Add the current input as the last user message
        $messages[] = [
            'role' => 'user',
            'content' => $input,
        ];

        return $messages;
    }

    /**
     * Combine existing messages with new input for structured requests.
     */
    protected function combineMessagesWithInput(ExecutionContext $context, string $input): string
    {
        $parts = [];

        foreach ($context->messages as $message) {
            $role = ucfirst($message['role']);
            $parts[] = "{$role}: {$message['content']}";
        }

        $parts[] = "User: {$input}";

        return implode("\n\n", $parts);
    }

    /**
     * Apply agent settings to the Prism request.
     *
     * @param  mixed  $request  The Prism pending request.
     * @return mixed The modified request.
     */
    protected function applyAgentSettings(mixed $request, AgentContract $agent): mixed
    {
        if ($agent->temperature() !== null) {
            $request = $request->withTemperature($agent->temperature());
        }

        if ($agent->maxTokens() !== null) {
            $request = $request->withMaxTokens($agent->maxTokens());
        }

        if ($agent->maxSteps() !== null) {
            $request = $request->withMaxSteps($agent->maxSteps());
        }

        // Apply provider tools if any
        $providerTools = $this->buildProviderTools($agent->providerTools());
        if ($providerTools !== []) {
            $request = $request->withProviderTools($providerTools);
        }

        return $request;
    }

    /**
     * Convert agent provider tool definitions to Prism ProviderTool objects.
     *
     * Accepts simple strings or arrays with options:
     *   - 'web_search' => ProviderTool(type: 'web_search')
     *   - ['type' => 'web_search', 'max_results' => 5] => ProviderTool with options
     *   - ProviderTool instance => passed through
     *
     * @param  array<int, string|array<string, mixed>|ProviderTool>  $tools
     * @return array<int, ProviderTool>
     */
    protected function buildProviderTools(array $tools): array
    {
        $providerTools = array_map(function ($tool) {
            // Already a ProviderTool instance
            if ($tool instanceof ProviderTool) {
                return $tool;
            }

            // Simple string type
            if (is_string($tool)) {
                return new ProviderTool(type: $tool);
            }

            // Array with type and options
            if (isset($tool['type'])) {
                $type = $tool['type'];
                $name = $tool['name'] ?? null;
                unset($tool['type'], $tool['name']);

                return new ProviderTool(
                    type: $type,
                    name: $name,
                    options: $tool,
                );
            }

            // Unknown format, skip
            return null;
        }, $tools);

        // Filter out nulls
        return array_values(array_filter($providerTools));
    }

    /**
     * Build an AgentResponse from the Prism response.
     */
    protected function buildResponse(AgentContract $agent, mixed $prismResponse): AgentResponse
    {
        $usage = $this->usageExtractor->extract($agent->provider(), $prismResponse);

        $toolCalls = $this->extractToolCalls($prismResponse);

        return new AgentResponse(
            text: $prismResponse->text ?? null,
            toolCalls: $toolCalls,
            usage: $usage,
            metadata: [
                'finish_reason' => $prismResponse->finishReason->value ?? null,
            ],
        );
    }

    /**
     * Extract tool calls from the Prism response.
     *
     * Prism stores tool calls in the steps array, with each step containing
     * both the tool calls and their results.
     *
     * @return array<int, array{name: string, arguments: array<string, mixed>, result: string|null}>
     */
    protected function extractToolCalls(mixed $prismResponse): array
    {
        $toolCalls = [];

        // Extract from steps (Prism's multi-step tool execution)
        if (property_exists($prismResponse, 'steps') && $prismResponse->steps) {
            foreach ($prismResponse->steps as $step) {
                if (isset($step->toolCalls) && ! empty($step->toolCalls)) {
                    foreach ($step->toolCalls as $i => $call) {
                        $result = null;
                        if (isset($step->toolResults[$i])) {
                            $result = $step->toolResults[$i]->result ?? null;
                        }
                        $toolCalls[] = [
                            'name' => $call->name,
                            'arguments' => $call->arguments(),
                            'result' => $result,
                        ];
                    }
                }
            }
        }

        // Fallback to direct toolCalls property if no steps
        if (empty($toolCalls) && property_exists($prismResponse, 'toolCalls') && $prismResponse->toolCalls) {
            foreach ($prismResponse->toolCalls as $call) {
                $toolCalls[] = [
                    'name' => $call->name,
                    'arguments' => $call->arguments(),
                    'result' => null,
                ];
            }
        }

        return $toolCalls;
    }

    /**
     * Build an AgentResponse for structured output.
     */
    protected function buildStructuredResponse(AgentContract $agent, mixed $prismResponse): AgentResponse
    {
        $usage = $this->usageExtractor->extract($agent->provider(), $prismResponse);

        return new AgentResponse(
            structured: $prismResponse->structured ?? null,
            usage: $usage,
            metadata: [
                'finish_reason' => $prismResponse->finishReason->value ?? null,
            ],
        );
    }
}
