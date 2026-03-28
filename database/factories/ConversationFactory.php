<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'agent' => $this->faker->word(),
            'title' => $this->faker->sentence(),
            'metadata' => null,
        ];
    }

    public function withAgent(string $agent): static
    {
        return $this->state(['agent' => $agent]);
    }
}
