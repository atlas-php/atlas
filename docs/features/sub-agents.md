# Sub-agents

A sub-agent is an [agent](/features/agents) used as a [tool](/features/tools) by another agent. When the parent decides to call it, the sub-agent runs on its own — its own instructions, model, and tools — and returns its answer to the parent as the tool result.

Use this to delegate specialised work: a support agent that hands billing questions to a billing agent, a coordinator that delegates research, and so on. Each sub-agent keeps its own focused instructions, so the parent's prompt stays small.

Because a sub-agent is just a tool, it behaves like any other tool everywhere — it fires the same events, streams to your UI in real time, and is recorded in persistence the same way.

> You could always hand-roll this — write a custom [tool](/features/tools) that runs another agent itself. The built-in sub-agent support does that wiring for you and adds context isolation, depth/cycle guards, and parent → child audit tracking automatically.

## Declaring a sub-agent

List an agent class in another agent's `tools()`. Atlas exposes it to the parent model as a delegation tool automatically:

```php
use Atlasphp\Atlas\Agent;

class SupportAgent extends Agent
{
    public function tools(): array
    {
        return [
            BillingAgent::class,   // delegated to as a sub-agent
            SearchKnowledgeBase::class, // a normal tool
        ];
    }
}
```

The tool's name and description come from the sub-agent's `key()` and `description()`.

## What the parent passes

The parent model sees a single string parameter, `task`, which it fills with a clear, self-contained instruction. That becomes the sub-agent's message, and the sub-agent's final text is returned as the tool result.

## Context isolation

Each sub-agent invocation runs in isolation: its own instructions, model, and tools, starting from a fresh history. The parent's conversation history is **not** shared — pass everything the sub-agent needs in the `task`. Context you set with `withMeta()` (auth, tenant) **is** forwarded, so authorization still works.

## Guards

Delegation is bounded to prevent runaway nesting:

- **Depth** — `atlas.agents.max_delegation_depth` (default `5`) caps how deep agents may delegate; exceeding it throws `MaxDelegationDepthException`.
- **Cycles** — delegating to an agent already in the chain (`A → B → A`) throws `DelegationCycleException`.

`atlas.agents.delegation_errors` controls sub-agent failures: `throw` (default) surfaces a failed tool call; `return` hands the error message back to the parent model so it can recover.

```php
// config/atlas.php
'agents' => [
    'max_delegation_depth' => 5,
    'delegation_errors' => 'throw',
],
```

## Auditing the delegation tree

With [persistence](/features/persistence) enabled, every delegation is recorded as an auditable tree. Each sub-agent run is its own `Execution`, linked to its parent by `parent_execution_id`, `parent_tool_call_id` (the delegating call, typed `agent`), and `depth` (`0` for the root, incremented per level):

```php
use Atlasphp\Atlas\Persistence\Models\Execution;

$run = Execution::find($id);
$run->parent;    // the execution that delegated to this one (null for a root)
$run->children;  // sub-agent executions it spawned

// Each agent's own token cost is stored on its own row.
$run->usage; // ['input_tokens' => …, 'output_tokens' => …]

// Total tokens for the whole chain (this run plus every sub-agent it called).
$run->totalUsage(); // ['input_tokens' => …, 'output_tokens' => …, 'total_tokens' => …]
```

So you can report what each individual agent cost, as well as the cost of an entire delegation chain.
