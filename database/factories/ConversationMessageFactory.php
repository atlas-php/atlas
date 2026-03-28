<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConversationMessage> */
class ConversationMessageFactory extends Factory
{
    protected $model = ConversationMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'parent_id' => null,
            'step_id' => null,
            'role' => MessageRole::User,
            'status' => MessageStatus::Delivered,
            'owner_type' => null,
            'owner_id' => null,
            'agent' => null,
            'content' => $this->faker->paragraph(),
            'sequence' => 0,
            'is_active' => true,
            'read_at' => null,
            'metadata' => null,
        ];
    }

    public function fromUser(): static
    {
        return $this->state(['role' => MessageRole::User]);
    }

    public function fromAssistant(?string $agent = null): static
    {
        return $this->state([
            'role' => MessageRole::Assistant,
            'agent' => $agent ?? $this->faker->word(),
        ]);
    }

    public function system(): static
    {
        return $this->state(['role' => MessageRole::System]);
    }

    public function queued(): static
    {
        return $this->state(['status' => MessageStatus::Queued]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withSequence(int $sequence): static
    {
        return $this->state(['sequence' => $sequence]);
    }
}
