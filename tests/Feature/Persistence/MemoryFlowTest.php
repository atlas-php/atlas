<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryService;
use Atlasphp\Atlas\Persistence\Models\Memory;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->service = app(MemoryService::class);
    $this->owner = new class extends Model
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
});

it('stores and recalls atomic memories', function () {
    $this->service->remember($this->owner, 'Likes dark mode', type: 'preference');
    $this->service->remember($this->owner, 'Works at Acme', type: 'fact');

    $result = $this->service->recall($this->owner, 'preference');

    expect($result)->not->toBeNull()
        ->and($result->content)->toBe('Likes dark mode');

    expect(Memory::count())->toBe(2);
});

it('document upsert preserves version history', function () {
    $v1 = $this->service->remember(
        $this->owner, 'Version 1', type: 'profile', key: 'main'
    );

    $v2 = $this->service->remember(
        $this->owner, 'Version 2', type: 'profile', key: 'main'
    );

    // Only latest is active
    expect(Memory::count())->toBe(1)
        ->and(Memory::first()->content)->toBe('Version 2');

    // History preserved via soft deletes
    $history = Memory::withTrashed()->orderBy('id')->get();
    expect($history)->toHaveCount(2)
        ->and($history[0]->content)->toBe('Version 1')
        ->and($history[0]->trashed())->toBeTrue()
        ->and($history[1]->content)->toBe('Version 2')
        ->and($history[1]->trashed())->toBeFalse();
});

it('forget removes memory and recall returns null', function () {
    $memory = $this->service->remember($this->owner, 'Temporary fact');

    $this->service->forget($memory->id);

    expect($this->service->recall($this->owner, 'atomic'))->toBeNull();
});

it('forgetFor removes matching memories', function () {
    $this->service->remember($this->owner, 'Note 1', type: 'note', namespace: 'scratch');
    $this->service->remember($this->owner, 'Note 2', type: 'note', namespace: 'scratch');
    $this->service->remember($this->owner, 'Important fact', type: 'fact');

    $deleted = $this->service->forgetFor($this->owner, type: 'note', namespace: 'scratch');

    expect($deleted)->toBe(2)
        ->and(Memory::count())->toBe(1)
        ->and(Memory::first()->type)->toBe('fact');
});

it('expiration lifecycle works end to end', function () {
    $this->service->remember(
        $this->owner, 'Temporary', type: 'temp',
        expiresAt: now()->subHour(),
    );
    $this->service->remember($this->owner, 'Permanent', type: 'perm');

    // Expired memory not returned by recall
    expect($this->service->recall($this->owner, 'temp'))->toBeNull();

    // forgetExpired force-deletes
    $deleted = $this->service->forgetExpired();
    expect($deleted)->toBe(1)
        ->and(Memory::withTrashed()->count())->toBe(1) // only permanent remains
        ->and(Memory::first()->type)->toBe('perm');
});

it('decay reduces importance of old memories', function () {
    $this->service->remember($this->owner, 'Old fact', type: 'fact', importance: 1.0);
    // Use query builder to bypass Eloquent timestamp auto-update
    Memory::where('id', Memory::first()->id)
        ->toBase()
        ->update(['updated_at' => now()->subMonths(4)]);

    $this->service->remember($this->owner, 'Recent fact', type: 'fact', importance: 1.0);

    $affected = $this->service->decay($this->owner, now()->subMonths(3), 0.5);

    expect($affected)->toBe(1);

    $memories = Memory::orderBy('id')->get();
    expect($memories[0]->importance)->toBe(0.5) // decayed
        ->and($memories[1]->importance)->toBe(1.0); // untouched
});

it('recall touches last_accessed_at', function () {
    $this->service->remember($this->owner, 'Test', type: 'fact');

    expect(Memory::first()->last_accessed_at)->toBeNull();

    $this->service->recall($this->owner, 'fact');

    expect(Memory::first()->last_accessed_at)->not->toBeNull();
});

it('recallMany returns multiple types and touches all', function () {
    $this->service->remember($this->owner, 'Profile data', type: 'profile');
    $this->service->remember($this->owner, 'Context data', type: 'context');
    $this->service->remember($this->owner, 'Unrelated', type: 'other');

    $results = $this->service->recallMany($this->owner, ['profile', 'context']);

    expect($results)->toHaveCount(2);

    // Only profile and context should have last_accessed_at updated
    $profile = Memory::where('type', 'profile')->first();
    $context = Memory::where('type', 'context')->first();
    $other = Memory::where('type', 'other')->first();

    expect($profile->last_accessed_at)->not->toBeNull()
        ->and($context->last_accessed_at)->not->toBeNull()
        ->and($other->last_accessed_at)->toBeNull();
});

it('query provides escape hatch for custom queries', function () {
    $this->service->remember($this->owner, 'Summary 1', type: 'summary', namespace: 'daily');
    $this->service->remember($this->owner, 'Summary 2', type: 'summary', namespace: 'daily');
    $this->service->remember($this->owner, 'Summary 3', type: 'summary', namespace: 'weekly');

    $results = $this->service->query($this->owner)
        ->where('type', 'summary')
        ->where('namespace', 'daily')
        ->get();

    expect($results)->toHaveCount(2);
});

it('agent-scoped memories are isolated', function () {
    $this->service->remember($this->owner, 'Support note', type: 'note', agent: 'support');
    $this->service->remember($this->owner, 'Sales note', type: 'note', agent: 'sales');

    $support = $this->service->recall($this->owner, 'note', agent: 'support');
    $sales = $this->service->recall($this->owner, 'note', agent: 'sales');

    expect($support->content)->toBe('Support note')
        ->and($sales->content)->toBe('Sales note');
});

it('metadata is stored and queryable via escape hatch', function () {
    $this->service->remember(
        $this->owner, 'Frustrated interaction',
        type: 'interaction',
        metadata: ['sentiment' => 'frustrated', 'resolved' => false],
    );

    $results = $this->service->query($this->owner)
        ->where('metadata->sentiment', 'frustrated')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->metadata['resolved'])->toBeFalse();
});
