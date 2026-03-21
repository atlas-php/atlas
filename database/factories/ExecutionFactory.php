<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Execution> */
class ExecutionFactory extends Factory
{
    protected $model = Execution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => null,
            'message_id' => null,
            'asset_id' => null,
            'agent' => null,
            'type' => ExecutionType::Text,
            'provider' => 'openai',
            'model' => 'gpt-5',
            'status' => ExecutionStatus::Pending,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'error' => null,
            'metadata' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => ExecutionStatus::Queued]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function completed(int $inputTokens = 100, int $outputTokens = 50): static
    {
        return $this->state([
            'status' => ExecutionStatus::Completed,
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
            'duration_ms' => 5000,
            'total_input_tokens' => $inputTokens,
            'total_output_tokens' => $outputTokens,
        ]);
    }

    public function failed(string $error = 'Test error'): static
    {
        return $this->state([
            'status' => ExecutionStatus::Failed,
            'started_at' => now()->subSeconds(2),
            'completed_at' => now(),
            'duration_ms' => 2000,
            'error' => $error,
        ]);
    }

    public function withAgent(string $agent): static
    {
        return $this->state(['agent' => $agent]);
    }

    public function ofType(ExecutionType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
