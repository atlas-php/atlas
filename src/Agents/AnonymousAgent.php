<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Enums\AgentType;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Tool;

/**
 * Concrete agent for inline configuration without a dedicated class.
 *
 * Accepts all configuration via constructor parameters, enabling
 * quick one-off agents via Atlas::make() or direct instantiation.
 */
class AnonymousAgent implements AgentContract
{
    /**
     * @param  string  $agentKey  Unique key for this agent.
     * @param  string|null  $systemPromptText  The system prompt template.
     * @param  string|null  $agentProvider  AI provider name.
     * @param  string|null  $agentModel  Model identifier.
     * @param  array<int, class-string>  $agentTools  Tool class names.
     * @param  string|null  $agentName  Display name.
     * @param  string|null  $agentDescription  Agent description.
     * @param  float|null  $agentTemperature  Sampling temperature.
     * @param  int|null  $agentMaxTokens  Maximum response tokens.
     * @param  int|null  $agentMaxSteps  Maximum tool use iterations.
     * @param  Schema|null  $agentSchema  Schema for structured output.
     * @param  array<int, string|array{type: string, ...}>  $agentProviderTools  Provider-specific tools.
     * @param  array<int, Tool>  $agentMcpTools  MCP tools from external servers.
     * @param  array<string, mixed>  $agentClientOptions  HTTP client options.
     * @param  array<string, mixed>  $agentProviderOptions  Provider-specific options.
     */
    public function __construct(
        private string $agentKey = 'anonymous',
        private ?string $systemPromptText = null,
        private ?string $agentProvider = null,
        private ?string $agentModel = null,
        private array $agentTools = [],
        private ?string $agentName = null,
        private ?string $agentDescription = null,
        private ?float $agentTemperature = null,
        private ?int $agentMaxTokens = null,
        private ?int $agentMaxSteps = null,
        private ?Schema $agentSchema = null,
        private array $agentProviderTools = [],
        private array $agentMcpTools = [],
        private array $agentClientOptions = [],
        private array $agentProviderOptions = [],
    ) {}

    public function key(): string
    {
        return $this->agentKey;
    }

    public function name(): string
    {
        return $this->agentName ?? 'Anonymous Agent';
    }

    public function type(): AgentType
    {
        return AgentType::Api;
    }

    public function provider(): ?string
    {
        return $this->agentProvider;
    }

    public function model(): ?string
    {
        return $this->agentModel;
    }

    public function systemPrompt(): ?string
    {
        return $this->systemPromptText;
    }

    public function description(): ?string
    {
        return $this->agentDescription;
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return $this->agentTools;
    }

    /**
     * @return array<int, string|array{type: string, ...}>
     */
    public function providerTools(): array
    {
        return $this->agentProviderTools;
    }

    /**
     * @return array<int, Tool>
     */
    public function mcpTools(): array
    {
        return $this->agentMcpTools;
    }

    public function temperature(): ?float
    {
        return $this->agentTemperature;
    }

    public function maxTokens(): ?int
    {
        return $this->agentMaxTokens;
    }

    public function maxSteps(): ?int
    {
        return $this->agentMaxSteps;
    }

    /**
     * @return array<string, mixed>
     */
    public function clientOptions(): array
    {
        return $this->agentClientOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        return $this->agentProviderOptions;
    }

    public function schema(): ?Schema
    {
        return $this->agentSchema;
    }
}
