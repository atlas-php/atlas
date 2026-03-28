<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ExecutionToolCall> */
class ExecutionToolCallFactory extends Factory
{
    protected $model = ExecutionToolCall::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'execution_id' => Execution::factory(),
            'step_id' => ExecutionStep::factory(),
            'tool_call_id' => 'call_'.Str::random(24),
            'name' => $this->faker->word(),
            'type' => ToolCallType::Local,
            'status' => ExecutionStatus::Pending,
            'arguments' => null,
            'result' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
            'metadata' => null,
        ];
    }

    public function completed(string $result = '{"status": "ok"}'): static
    {
        return $this->state([
            'status' => ExecutionStatus::Completed,
            'result' => $result,
            'started_at' => now()->subMilliseconds(500),
            'completed_at' => now(),
            'duration_ms' => 500,
        ]);
    }

    public function failed(string $error = 'Tool execution failed'): static
    {
        return $this->state([
            'status' => ExecutionStatus::Failed,
            'result' => $error,
            'started_at' => now()->subMilliseconds(200),
            'completed_at' => now(),
            'duration_ms' => 200,
        ]);
    }

    public function withName(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    /** @param  array<string, mixed>  $args */
    public function withArguments(array $args): static
    {
        return $this->state(['arguments' => $args]);
    }
}
