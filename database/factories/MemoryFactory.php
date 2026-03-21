<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Memory> */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'memoryable_type' => null,
            'memoryable_id' => null,
            'agent' => null,
            'type' => 'fact',
            'namespace' => null,
            'key' => null,
            'content' => $this->faker->sentence(),
            'importance' => 0.5,
            'source' => null,
            'last_accessed_at' => null,
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    public function forAgent(string $key): static
    {
        return $this->state(['agent' => $key]);
    }

    public function withNamespace(string $namespace): static
    {
        return $this->state(['namespace' => $namespace]);
    }

    public function document(string $key): static
    {
        return $this->state(['key' => $key]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function withImportance(float $value): static
    {
        return $this->state(['importance' => $value]);
    }

    public function withSource(string $source): static
    {
        return $this->state(['source' => $source]);
    }
}
