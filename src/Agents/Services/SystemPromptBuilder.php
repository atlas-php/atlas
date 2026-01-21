<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;

/**
 * Builds system prompts with variable interpolation.
 *
 * Interpolates {variable} placeholders in system prompts using
 * values from the execution context. Supports both snake_case
 * and camelCase variable names.
 *
 * **Important: Singleton State Behavior**
 *
 * This service is registered as a singleton and maintains mutable state
 * (global variables and sections). In long-running processes like Laravel
 * Octane or queue workers, registered variables and sections will persist
 * across requests unless explicitly cleared.
 *
 * For request-scoped variables, prefer passing them via ExecutionContext::$variables
 * rather than using registerVariable(). If you use registerVariable() in
 * long-running processes, ensure you call unregisterVariable() or create
 * a fresh instance when needed.
 */
class SystemPromptBuilder
{
    /**
     * Pattern for matching {variable_name} placeholders.
     */
    protected const VARIABLE_PATTERN = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';

    /**
     * Global variables available to all prompts.
     *
     * @var array<string, mixed>
     */
    protected array $variables = [];

    /**
     * Additional sections to append to prompts.
     *
     * @var array<string, string>
     */
    protected array $sections = [];

    public function __construct(
        protected PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Build the system prompt for an agent.
     *
     * @param  AgentContract  $agent  The agent to build the prompt for.
     * @param  ExecutionContext  $context  The execution context with variables.
     */
    public function build(AgentContract $agent, ExecutionContext $context): string
    {
        // Run before_build pipeline
        $data = [
            'agent' => $agent,
            'context' => $context,
            'variables' => $this->mergeVariables($context),
        ];

        /** @var array{agent: AgentContract, context: ExecutionContext, variables: array<string, mixed>} $data */
        $data = $this->pipelineRunner->runIfActive(
            'agent.system_prompt.before_build',
            $data,
        );

        // Get the base prompt and interpolate
        $prompt = $agent->systemPrompt();
        $prompt = $this->interpolate($prompt, $data['variables']);

        // Append sections
        if ($this->sections !== []) {
            $prompt .= "\n\n".implode("\n\n", $this->sections);
        }

        // Run after_build pipeline
        $afterData = [
            'agent' => $agent,
            'context' => $context,
            'prompt' => $prompt,
        ];

        /** @var array{prompt: string} $afterData */
        $afterData = $this->pipelineRunner->runIfActive(
            'agent.system_prompt.after_build',
            $afterData,
        );

        return $afterData['prompt'];
    }

    /**
     * Register a global variable.
     *
     * @param  string  $name  The variable name.
     * @param  mixed  $value  The variable value.
     */
    public function registerVariable(string $name, mixed $value): static
    {
        $this->variables[$name] = $value;

        return $this;
    }

    /**
     * Unregister a global variable.
     *
     * @param  string  $name  The variable name.
     */
    public function unregisterVariable(string $name): static
    {
        unset($this->variables[$name]);

        return $this;
    }

    /**
     * Add a section to append to prompts.
     *
     * @param  string  $key  The section key.
     * @param  string  $content  The section content.
     */
    public function addSection(string $key, string $content): static
    {
        $this->sections[$key] = $content;

        return $this;
    }

    /**
     * Remove a section.
     *
     * @param  string  $key  The section key.
     */
    public function removeSection(string $key): static
    {
        unset($this->sections[$key]);

        return $this;
    }

    /**
     * Clear all sections.
     */
    public function clearSections(): static
    {
        $this->sections = [];

        return $this;
    }

    /**
     * Interpolate variables into a prompt template.
     *
     * @param  string  $prompt  The prompt template.
     * @param  array<string, mixed>  $variables  The variables to interpolate.
     */
    protected function interpolate(string $prompt, array $variables): string
    {
        return (string) preg_replace_callback(
            self::VARIABLE_PATTERN,
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                if (array_key_exists($key, $variables)) {
                    return $this->valueToString($variables[$key]);
                }

                // Return the original placeholder if not found
                return $matches[0];
            },
            $prompt,
        );
    }

    /**
     * Convert a variable value to string.
     */
    protected function valueToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    /**
     * Merge global variables with context variables.
     *
     * Context variables take precedence over global variables.
     *
     * @return array<string, mixed>
     */
    protected function mergeVariables(ExecutionContext $context): array
    {
        return array_merge($this->variables, $context->variables);
    }
}
