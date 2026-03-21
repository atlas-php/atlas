<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Model;

it('creates a valid record via factory', function () {
    $asset = Asset::factory()->create();

    expect($asset->exists)->toBeTrue()
        ->and($asset->type)->toBeInstanceOf(AssetType::class)
        ->and($asset->filename)->toBeString()
        ->and($asset->path)->toBeString()
        ->and($asset->disk)->toBe('local');
});

it('isMedia returns true for image, audio, and video', function () {
    $image = Asset::factory()->image()->create();
    $audio = Asset::factory()->audio()->create();
    $video = Asset::factory()->video()->create();
    $document = Asset::factory()->create(['type' => AssetType::Document]);
    $text = Asset::factory()->create(['type' => AssetType::Text]);

    expect($image->isMedia())->toBeTrue()
        ->and($audio->isMedia())->toBeTrue()
        ->and($video->isMedia())->toBeTrue()
        ->and($document->isMedia())->toBeFalse()
        ->and($text->isMedia())->toBeFalse();
});

it('isHumanAuthored returns true when author is set', function () {
    $humanAuthored = Asset::factory()->create([
        'author_type' => 'App\\Models\\User',
        'author_id' => 1,
    ]);

    $agentAuthored = Asset::factory()->byAgent('writer')->create();

    expect($humanAuthored->isHumanAuthored())->toBeTrue()
        ->and($agentAuthored->isHumanAuthored())->toBeFalse();
});

it('isAgentAuthored returns true when agent is set', function () {
    $agentAuthored = Asset::factory()->byAgent('writer')->create();
    $humanAuthored = Asset::factory()->create([
        'author_type' => 'App\\Models\\User',
        'author_id' => 1,
    ]);

    expect($agentAuthored->isAgentAuthored())->toBeTrue()
        ->and($humanAuthored->isAgentAuthored())->toBeFalse();
});

it('authorName returns agent name for agent-authored assets', function () {
    $asset = Asset::factory()->byAgent('writer')->create();

    expect($asset->authorName())->toBe('writer');
});

it('scopeByAgent filters correctly', function () {
    Asset::factory()->byAgent('writer')->create();
    Asset::factory()->byAgent('writer')->create();
    Asset::factory()->byAgent('coder')->create();

    expect(Asset::byAgent('writer')->count())->toBe(2)
        ->and(Asset::byAgent('coder')->count())->toBe(1);
});

it('scopeForExecution filters correctly', function () {
    $execution = Execution::factory()->create();

    Asset::factory()->create(['execution_id' => $execution->id]);
    Asset::factory()->create(['execution_id' => $execution->id]);
    Asset::factory()->create(); // no execution

    expect(Asset::forExecution($execution->id)->count())->toBe(2);
});

it('supports soft deletes', function () {
    $asset = Asset::factory()->create();

    $asset->delete();

    expect($asset->trashed())->toBeTrue()
        ->and(Asset::count())->toBe(0)
        ->and(Asset::withTrashed()->count())->toBe(1);

    $asset->restore();

    expect($asset->trashed())->toBeFalse()
        ->and(Asset::count())->toBe(1);
});

it('scopeByAuthor filters by polymorphic author', function () {
    // Create assets with specific author_type and author_id
    Asset::factory()->create([
        'author_type' => 'App\\Models\\User',
        'author_id' => 1,
    ]);
    Asset::factory()->create([
        'author_type' => 'App\\Models\\User',
        'author_id' => 1,
    ]);
    Asset::factory()->create([
        'author_type' => 'App\\Models\\User',
        'author_id' => 2,
    ]);
    Asset::factory()->create([
        'author_type' => 'App\\Models\\Team',
        'author_id' => 1,
    ]);

    // Use a Conversation model as a stand-in for the polymorphic query
    // since we need a Model instance. We'll use a mock approach instead.
    $author = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 1;
        }
    };

    expect(Asset::byAuthor($author)->count())->toBe(2);
});

it('execution relationship returns related execution', function () {
    $execution = Execution::factory()->create();
    $asset = Asset::factory()->create(['execution_id' => $execution->id]);

    expect($asset->execution)->toBeInstanceOf(Execution::class)
        ->and($asset->execution->id)->toBe($execution->id);
});

it('attachments relationship returns message attachments', function () {
    $asset = Asset::factory()->create();
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
    ]);
    MessageAttachment::factory()->create([
        'message_id' => $message->id,
        'asset_id' => $asset->id,
    ]);

    expect($asset->attachments)->toHaveCount(1)
        ->and($asset->attachments->first()->message_id)->toBe($message->id);
});
