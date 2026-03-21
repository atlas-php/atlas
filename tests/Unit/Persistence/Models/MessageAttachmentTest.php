<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;

it('creates a valid record via factory', function () {
    $attachment = MessageAttachment::factory()->create();

    expect($attachment->exists)->toBeTrue()
        ->and($attachment->message_id)->not->toBeNull()
        ->and($attachment->asset_id)->not->toBeNull();
});

it('message relationship returns the parent message', function () {
    $message = Message::factory()->create();
    $asset = Asset::factory()->create();

    $attachment = MessageAttachment::factory()->create([
        'message_id' => $message->id,
        'asset_id' => $asset->id,
    ]);

    expect($attachment->message)->toBeInstanceOf(Message::class)
        ->and($attachment->message->id)->toBe($message->id);
});

it('asset relationship returns the related asset', function () {
    $message = Message::factory()->create();
    $asset = Asset::factory()->create();

    $attachment = MessageAttachment::factory()->create([
        'message_id' => $message->id,
        'asset_id' => $asset->id,
    ]);

    expect($attachment->asset)->toBeInstanceOf(Asset::class)
        ->and($attachment->asset->id)->toBe($asset->id);
});
