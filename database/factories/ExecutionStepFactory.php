<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExecutionStep> */
class ExecutionStepFactory extends Factory
{
    protected $model = ExecutionStep::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'execution_id' => Execution::factory(),
            'sequence' => 0,
            'status' => ExecutionStatus::Pending,
            'content' => null,
            'reasoning' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'finish_reason' => null,
            'error' => null,
            'metadata' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
        ];
    }

    public function completed(string $content = 'Test response', int $inputTokens = 50, int $outputTokens = 25): static
    {
        return $this->state([
            'status' => ExecutionStatus::Completed,
            'content' => $content,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'finish_reason' => 'stop',
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
            'duration_ms' => 2000,
        ]);
    }

    public function withToolCalls(string $content = 'Let me look that up'): static
    {
        return $this->state([
            'status' => ExecutionStatus::Completed,
            'content' => $content,
            'finish_reason' => 'tool_calls',
            'input_tokens' => 30,
            'output_tokens' => 15,
            'started_at' => now()->subSeconds(1),
            'completed_at' => now(),
            'duration_ms' => 1000,
        ]);
    }

    public function failed(string $error = 'Provider error'): static
    {
        return $this->state([
            'status' => ExecutionStatus::Failed,
            'error' => $error,
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'duration_ms' => 1000,
        ]);
    }
}
