<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Exceptions\DelegationCycleException;
use Atlasphp\Atlas\Exceptions\MaxDelegationDepthException;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Fields\Field;
use Atlasphp\Atlas\Schema\Fields\StringField;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Wraps an Agent so it can be used as a tool by another agent (sub-agent delegation).
 *
 * The parent model sees a single tool taking one string argument, `task`. When
 * called, the sub-agent runs in isolation — its own instructions, model, tools,
 * and (empty) history — and its final text is returned to the parent as the tool
 * result. Depth and cycle guards prevent runaway nesting; the delegation chain is
 * threaded through tool-call meta so it survives across levels.
 */
class AgentTool extends Tool
{
    /** Meta key carrying the current delegation depth. */
    public const DEPTH_META_KEY = '_atlas_delegation_depth';

    /** Meta key carrying the chain of agent keys delegated through so far. */
    public const CHAIN_META_KEY = '_atlas_delegation_chain';

    /** Meta key carrying the consumer's variables to forward to sub-agents. */
    public const VARIABLES_META_KEY = '_atlas_variables';

    public function __construct(
        protected readonly Agent $agent,
        protected readonly ?string $nameOverride = null,
        protected readonly ?string $descriptionOverride = null,
    ) {}

    /**
     * Wrap an agent for use as a delegation tool.
     */
    public static function for(Agent $agent, ?string $name = null, ?string $description = null): self
    {
        return new self($agent, $name, $description);
    }

    public function name(): string
    {
        return $this->nameOverride ?? $this->agent->key();
    }

    public function description(): string
    {
        return $this->descriptionOverride
            ?? $this->agent->description()
            ?? sprintf(
                'Delegates a task to the "%s" sub-agent and returns its response. It runs in '
                .'isolation with no access to this conversation, so pass a clear, self-contained task.',
                $this->name(),
            );
    }

    /**
     * @return array<int, Field>
     */
    public function parameters(): array
    {
        return [
            new StringField('task', 'The self-contained task to delegate to this sub-agent.'),
        ];
    }

    public function isDelegation(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): string
    {
        $key = $this->agent->key();
        $depth = (int) ($context[self::DEPTH_META_KEY] ?? 0);
        /** @var array<int, string> $chain */
        $chain = (array) ($context[self::CHAIN_META_KEY] ?? []);

        // Guards run unconditionally — structural misuse should always surface.
        if (in_array($key, $chain, true)) {
            throw new DelegationCycleException($key, $chain);
        }

        $maxDepth = (int) config('atlas.agents.max_delegation_depth', 5);

        if ($depth >= $maxDepth) {
            throw new MaxDelegationDepthException($maxDepth, $key);
        }

        $task = isset($args['task']) && is_string($args['task']) ? $args['task'] : '';

        // The parent's own variables, forwarded so the sub-agent's prompt gets
        // the same dynamic injection (e.g. {user_name}) as if called directly.
        /** @var array<string, mixed> $parentVariables */
        $parentVariables = is_array($context[self::VARIABLES_META_KEY] ?? null)
            ? $context[self::VARIABLES_META_KEY]
            : [];

        // Inherit caller context (auth/tenant/conversation) but never the parent's
        // own persistence routing keys or the raw variables bag (applied below via
        // withVariables), so the sub-agent gets its own execution + clean meta.
        $childMeta = array_merge(
            Arr::except($context, ['execution_id', 'trigger_message_id', 'execution_type', self::VARIABLES_META_KEY]),
            [
                self::DEPTH_META_KEY => $depth + 1,
                self::CHAIN_META_KEY => [...$chain, $key],
            ],
        );

        try {
            // Run the sub-agent instance through the normal pipeline (forInstance
            // bypasses the registry key lookup) so it gets full execution tracking.
            $request = Atlas::agent($this->agent->key())
                ->forInstance($this->agent)
                ->withMeta($childMeta);

            if ($parentVariables !== []) {
                $request = $request->withVariables($parentVariables);
            }

            $response = $request->message($task)->asText();

            return $response instanceof TextResponse ? $response->text : '';
        } catch (Throwable $e) {
            if (config('atlas.agents.delegation_errors', 'throw') === 'return') {
                return "Sub-agent '{$key}' failed: ".$e->getMessage();
            }

            throw $e;
        }
    }
}
