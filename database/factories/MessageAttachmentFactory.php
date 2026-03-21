<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageAttachment> */
class MessageAttachmentFactory extends Factory
{
    protected $model = MessageAttachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'asset_id' => Asset::factory(),
            'metadata' => null,
        ];
    }
}
