# Atlas Enhancement Proposal: Observability & Error Handling

> **Purpose:** Enhance Atlas's pipeline system to provide complete observability over agent and tool execution, enabling consumers to implement comprehensive tracking, logging, and error handling.

---

## Executive Summary

Atlas's pipeline system provides excellent extension points for agent and tool execution. However, there are gaps that prevent consumers from implementing complete observability solutions:

1. **Error handling bypasses pipelines** — Exceptions skip `after_execute`, leaving consumers unable to track failures
2. **Request context is incomplete** — The system prompt and message history aren't available in `after_execute`
3. **Tool call correlation is inconsistent** — Tool call IDs from providers aren't always exposed

These enhancements benefit any consumer building:
- Audit logging and compliance systems
- Cost tracking and usage analytics
- Debugging and troubleshooting tools
- Process orchestration layers (like Nexus)

---

## Change 1: Add Error Pipelines

### Problem

When agent or tool execution fails, exceptions are thrown and the `after_execute` pipeline never runs. Consumers have no way to:
- Log failures through the pipeline system
- Clean up resources created in `before_execute`
- Update tracking records with error details
- Trigger alerts or notifications

### Current Behavior

```php
// AgentExecutor::execute()
try {
    $beforeData = $this->pipelineRunner->runIfActive('agent.before_execute', $beforeData);
    
    // ... execution ...
    
    $afterData = $this->pipelineRunner->runIfActive('agent.after_execute', $afterData);
    return $afterData['response'];
} catch (Throwable $e) {
    // Pipeline system is bypassed entirely
    throw AgentException::executionFailed($agent->key(), $e->getMessage());
}
```

### Solution

Add `agent.on_error` and `tool.on_error` pipelines that run when execution fails.

#### Pipeline Definitions

```php
// AtlasServiceProvider::defineCorePipelines()

$registry->define(
    'agent.on_error',
    'Pipeline executed when agent execution fails',
);

$registry->define(
    'tool.on_error',
    'Pipeline executed when tool execution fails',
);
```

#### AgentExecutor Changes

```php
public function execute(
    AgentContract $agent,
    string $input,
    ?ExecutionContext $context = null,
    ?Schema $schema = null,
): AgentResponse {
    $context = $context ?? new ExecutionContext;
    $systemPrompt = null;

    try {
        // Run before_execute pipeline
        $beforeData = [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
            'schema' => $schema,
        ];

        $beforeData = $this->pipelineRunner->runIfActive(
            'agent.before_execute',
            $beforeData,
        );

        $agent = $beforeData['agent'];
        $input = $beforeData['input'];
        $context = $beforeData['context'];
        $schema = $beforeData['schema'];

        // Build system prompt (capture for pipelines)
        $systemPrompt = $this->systemPromptBuilder->build($agent, $context);

        // Execute the request
        $response = $this->executeRequest($agent, $input, $context, $systemPrompt, $schema);

        // Run after_execute pipeline
        $afterData = [
            'agent' => $agent,
            'input' => $input,
            'context' => $context,
            'response' => $response,
            'system_prompt' => $systemPrompt,
        ];

        $afterData = $this->pipelineRunner->runIfActive(
            'agent.after_execute',
            $afterData,
        );

        return $afterData['response'];
    } catch (AgentException $e) {
        $this->handleExecutionError($agent, $input, $context, $systemPrompt, $schema, $e);
        throw $e;
    } catch (Throwable $e) {
        $this->handleExecutionError($agent, $input, $context, $systemPrompt, $schema, $e);
        throw AgentException::executionFailed($agent->key(), $e->getMessage(), $e);
    }
}

/**
 * Handle execution errors by running the error pipeline.
 */
protected function handleExecutionError(
    AgentContract $agent,
    string $input,
    ExecutionContext $context,
    ?string $systemPrompt,
    ?Schema $schema,
    Throwable $exception,
): void {
    $errorData = [
        'agent' => $agent,
        'input' => $input,
        'context' => $context,
        'system_prompt' => $systemPrompt,
        'schema' => $schema,
        'exception' => $exception,
    ];

    $this->pipelineRunner->runIfActive('agent.on_error', $errorData);
}
```

#### ToolExecutor Changes

```php
public function execute(ToolContract $tool, array $args, ToolContext $context): ToolResult
{
    try {
        // Run before_execute pipeline
        $pipelineData = [
            'tool' => $tool,
            'args' => $args,
            'context' => $context,
        ];

        $pipelineData = $this->pipelineRunner->runIfActive(
            'tool.before_execute',
            $pipelineData,
        );

        // Execute the tool
        $result = $tool->handle($pipelineData['args'], $pipelineData['context']);

        // Run after_execute pipeline
        $afterData = [
            'tool' => $tool,
            'args' => $pipelineData['args'],
            'context' => $pipelineData['context'],
            'result' => $result,
        ];

        $afterData = $this->pipelineRunner->runIfActive(
            'tool.after_execute',
            $afterData,
        );

        return $afterData['result'];
    } catch (ToolException $e) {
        $this->handleToolError($tool, $args, $context, $e);
        return ToolResult::error($e->getMessage());
    } catch (Throwable $e) {
        $this->handleToolError($tool, $args, $context, $e);
        return ToolResult::error("Tool '{$tool->name()}' failed: {$e->getMessage()}");
    }
}

/**
 * Handle tool errors by running the error pipeline.
 */
protected function handleToolError(
    ToolContract $tool,
    array $args,
    ToolContext $context,
    Throwable $exception,
): void {
    $errorData = [
        'tool' => $tool,
        'args' => $args,
        'context' => $context,
        'exception' => $exception,
    ];

    $this->pipelineRunner->runIfActive('tool.on_error', $errorData);
}
```

### Pipeline Data Contracts

#### agent.on_error

| Key | Type | Description |
|-----|------|-------------|
| `agent` | `AgentContract` | The agent that failed |
| `input` | `string` | The user input |
| `context` | `ExecutionContext` | The execution context |
| `system_prompt` | `?string` | The built system prompt (null if failed before building) |
| `schema` | `?Schema` | The structured output schema |
| `exception` | `Throwable` | The caught exception |

#### tool.on_error

| Key | Type | Description |
|-----|------|-------------|
| `tool` | `ToolContract` | The tool that failed |
| `args` | `array` | The arguments passed to the tool |
| `context` | `ToolContext` | The tool context |
| `exception` | `Throwable` | The caught exception |

### Consumer Usage Example

```php
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;

// Register error handlers
$registry = app(PipelineRegistry::class);

$registry->register('agent.on_error', function (array $data, Closure $next) {
    Log::error('Agent execution failed', [
        'agent' => $data['agent']->key(),
        'error' => $data['exception']->getMessage(),
        'input_length' => strlen($data['input']),
    ]);
    
    // Alert on critical failures
    if ($data['exception'] instanceof RateLimitException) {
        Alert::critical('Rate limit hit for agent: ' . $data['agent']->key());
    }
    
    return $next($data);
}, priority: 100);

$registry->register('tool.on_error', function (array $data, Closure $next) {
    Log::warning('Tool execution failed', [
        'tool' => $data['tool']->name(),
        'error' => $data['exception']->getMessage(),
    ]);
    
    return $next($data);
}, priority: 100);
```

---

## Change 2: Include System Prompt in After Execute

### Problem

The `agent.after_execute` pipeline doesn't include the system prompt that was sent to the provider. Consumers building audit logs or debugging tools cannot see what instructions the agent received.

### Current Behavior

```php
// agent.after_execute receives:
[
    'agent' => $agent,
    'input' => $input,
    'context' => $context,
    'response' => $response,
    // system_prompt is NOT included
]
```

### Solution

Include `system_prompt` in the `after_execute` pipeline data.

```php
// Build system prompt before execution
$systemPrompt = $this->systemPromptBuilder->build($agent, $context);

// Execute...

// Include in after_execute
$afterData = [
    'agent' => $agent,
    'input' => $input,
    'context' => $context,
    'response' => $response,
    'system_prompt' => $systemPrompt,  // ADD THIS
];
```

### Updated Pipeline Data Contract

#### agent.after_execute

| Key | Type | Description |
|-----|------|-------------|
| `agent` | `AgentContract` | The executed agent |
| `input` | `string` | The user input |
| `context` | `ExecutionContext` | The execution context with messages/variables/metadata |
| `response` | `AgentResponse` | The agent response with text, usage, tool calls |
| `system_prompt` | `string` | The interpolated system prompt sent to the provider |

### Consumer Usage Example

```php
$registry->register('agent.after_execute', function (array $data, Closure $next) {
    // Log full request/response for audit
    AuditLog::create([
        'type' => 'agent_execution',
        'agent_key' => $data['agent']->key(),
        'system_prompt' => $data['system_prompt'],
        'user_input' => $data['input'],
        'response_text' => $data['response']->text,
        'tokens_used' => $data['response']->totalTokens(),
    ]);
    
    return $next($data);
}, priority: 50);
```

---

## Change 3: Consistent Tool Call ID Extraction

### Problem

When Prism executes tools in a multi-step conversation, each tool call has a unique ID from the provider. Atlas extracts tool calls but inconsistently includes the ID, making it difficult for consumers to correlate tool invocations.

### Current Behavior

```php
protected function extractToolCalls(mixed $prismResponse): array
{
    // ...
    $toolCalls[] = [
        'name' => $call->name,
        'arguments' => $call->arguments(),
        'result' => $result,
        // 'id' is sometimes missing
    ];
}
```

### Solution

Always include the tool call ID when available.

```php
protected function extractToolCalls(mixed $prismResponse): array
{
    $toolCalls = [];

    if (property_exists($prismResponse, 'steps') && $prismResponse->steps) {
        foreach ($prismResponse->steps as $step) {
            if (isset($step->toolCalls) && ! empty($step->toolCalls)) {
                foreach ($step->toolCalls as $i => $call) {
                    $result = null;
                    if (isset($step->toolResults[$i])) {
                        $result = $step->toolResults[$i]->result ?? null;
                    }
                    $toolCalls[] = [
                        'id' => $call->id ?? null,        // ALWAYS INCLUDE
                        'name' => $call->name,
                        'arguments' => $call->arguments(),
                        'result' => $result,
                    ];
                }
            }
        }
    }

    if (empty($toolCalls) && property_exists($prismResponse, 'toolCalls') && $prismResponse->toolCalls) {
        foreach ($prismResponse->toolCalls as $call) {
            $toolCalls[] = [
                'id' => $call->id ?? null,            // ALWAYS INCLUDE
                'name' => $call->name,
                'arguments' => $call->arguments(),
                'result' => null,
            ];
        }
    }

    return $toolCalls;
}
```

### Updated AgentResponse Tool Call Structure

```php
// Each tool call in AgentResponse::$toolCalls
[
    'id' => 'call_abc123',           // Provider's tool call ID (nullable)
    'name' => 'search_web',          // Tool name
    'arguments' => ['query' => '…'], // Arguments passed
    'result' => '…',                 // Result returned (nullable)
]
```

### Consumer Usage Example

```php
// Correlate tool calls with execution logs
foreach ($response->toolCalls as $toolCall) {
    ToolExecutionLog::where('provider_call_id', $toolCall['id'])
        ->update(['result' => $toolCall['result']]);
}
```

---

## Change 4: AgentException Enhancement

### Problem

The `AgentException::executionFailed()` factory method doesn't preserve the original exception, losing valuable debugging information.

### Current Behavior

```php
public static function executionFailed(string $key, string $message): self
{
    return new self("Agent '{$key}' execution failed: {$message}");
}
```

### Solution

Add the original exception as the `previous` parameter.

```php
public static function executionFailed(string $key, string $message, ?Throwable $previous = null): self
{
    return new self(
        "Agent '{$key}' execution failed: {$message}",
        0,
        $previous
    );
}
```

Update the call site:

```php
} catch (Throwable $e) {
    $this->handleExecutionError($agent, $input, $context, $systemPrompt, $schema, $e);
    throw AgentException::executionFailed($agent->key(), $e->getMessage(), $e);  // Pass $e
}
```

### Benefit

Consumers can access the full exception chain for debugging:

```php
try {
    Atlas::chat('agent', 'input');
} catch (AgentException $e) {
    $original = $e->getPrevious();  // Access the original exception
    Log::error('Agent failed', [
        'message' => $e->getMessage(),
        'original_class' => $original ? get_class($original) : null,
        'original_message' => $original?->getMessage(),
    ]);
}
```

---

## Summary of Changes

### New Pipeline Definitions

| Pipeline | Trigger | Purpose |
|----------|---------|---------|
| `agent.on_error` | Agent execution throws exception | Error handling, logging, cleanup |
| `tool.on_error` | Tool execution throws exception | Error handling, logging |

### Modified Pipeline Data

| Pipeline | Added Fields |
|----------|--------------|
| `agent.after_execute` | `system_prompt` |
| `agent.on_error` | New pipeline with full context |
| `tool.on_error` | New pipeline with full context |

### Code Changes

| File | Change |
|------|--------|
| `AtlasServiceProvider` | Add `agent.on_error` and `tool.on_error` pipeline definitions |
| `AgentExecutor` | Add `handleExecutionError()` method, include `system_prompt` in `after_execute` |
| `ToolExecutor` | Add `handleToolError()` method |
| `AgentException` | Add `$previous` parameter to `executionFailed()` |

### Estimated Effort

| Task | Effort |
|------|--------|
| Pipeline definitions | 10 minutes |
| AgentExecutor changes | 30 minutes |
| ToolExecutor changes | 20 minutes |
| AgentException change | 5 minutes |
| Tests | 1-2 hours |
| **Total** | **2-3 hours** |

---

## Complete Pipeline Data Reference

After these changes, here's the complete data contract for all pipelines:

### agent.before_execute

```php
[
    'agent' => AgentContract,      // The agent to execute
    'input' => string,             // User input message
    'context' => ExecutionContext, // Messages, variables, metadata
    'schema' => ?Schema,           // Structured output schema
]
```

### agent.after_execute

```php
[
    'agent' => AgentContract,      // The executed agent
    'input' => string,             // User input message
    'context' => ExecutionContext, // Messages, variables, metadata
    'response' => AgentResponse,   // Response with text, usage, toolCalls
    'system_prompt' => string,     // The interpolated system prompt (NEW)
]
```

### agent.on_error (NEW)

```php
[
    'agent' => AgentContract,      // The agent that failed
    'input' => string,             // User input message
    'context' => ExecutionContext, // Messages, variables, metadata
    'system_prompt' => ?string,    // System prompt (null if failed before building)
    'schema' => ?Schema,           // Structured output schema
    'exception' => Throwable,      // The caught exception
]
```

### tool.before_execute

```php
[
    'tool' => ToolContract,        // The tool to execute
    'args' => array,               // Arguments to pass
    'context' => ToolContext,      // Tool context with metadata
]
```

### tool.after_execute

```php
[
    'tool' => ToolContract,        // The executed tool
    'args' => array,               // Arguments passed
    'context' => ToolContext,      // Tool context
    'result' => ToolResult,        // Result with text and error flag
]
```

### tool.on_error (NEW)

```php
[
    'tool' => ToolContract,        // The tool that failed
    'args' => array,               // Arguments passed
    'context' => ToolContext,      // Tool context
    'exception' => Throwable,      // The caught exception
]
```

---

## Backward Compatibility

All changes are **fully backward compatible**:

1. **New pipelines** — Consumers not using them are unaffected
2. **Additional data in existing pipelines** — Extra keys don't affect existing handlers
3. **AgentException change** — The `$previous` parameter is optional with a default of `null`

No breaking changes to the public API.