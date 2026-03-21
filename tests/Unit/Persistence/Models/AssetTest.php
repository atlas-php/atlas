<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;

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

it('execution relationship returns related execution', function () {
    $execution = Execution::factory()->create();
    $asset = Asset::factory()->create(['execution_id' => $execution->id]);

    expect($asset->execution)->toBeInstanceOf(Execution::class)
        ->and($asset->execution->id)->toBe($execution->id);
});
