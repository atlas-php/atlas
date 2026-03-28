<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;

it('creates a valid record via factory', function () {
    $attachment = ConversationMessageAsset::factory()->create();

    expect($attachment->exists)->toBeTrue()
        ->and($attachment->message_id)->not->toBeNull()
        ->and($attachment->asset_id)->not->toBeNull();
});

it('message relationship returns the parent message', function () {
    $message = ConversationMessage::factory()->create();
    $asset = Asset::factory()->create();

    $attachment = ConversationMessageAsset::factory()->create([
        'message_id' => $message->id,
        'asset_id' => $asset->id,
    ]);

    expect($attachment->message)->toBeInstanceOf(ConversationMessage::class)
        ->and($attachment->message->id)->toBe($message->id);
});

it('asset relationship returns the related asset', function () {
    $message = ConversationMessage::factory()->create();
    $asset = Asset::factory()->create();

    $attachment = ConversationMessageAsset::factory()->create([
        'message_id' => $message->id,
        'asset_id' => $asset->id,
    ]);

    expect($attachment->asset)->toBeInstanceOf(Asset::class)
        ->and($attachment->asset->id)->toBe($asset->id);
});
