<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConversationMessageAsset> */
class ConversationMessageAssetFactory extends Factory
{
    protected $model = ConversationMessageAsset::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'message_id' => ConversationMessage::factory(),
            'asset_id' => Asset::factory(),
            'metadata' => null,
        ];
    }
}
