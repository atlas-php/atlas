<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->context = new MemoryContext;
    $this->owner = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 42;
        }
    };
});

it('starts unconfigured with null values', function () {
    expect($this->context->isConfigured())->toBeFalse()
        ->and($this->context->owner())->toBeNull()
        ->and($this->context->agentKey())->toBeNull();
});

it('configure sets owner and agent key', function () {
    $this->context->configure($this->owner, 'support');

    expect($this->context->isConfigured())->toBeTrue()
        ->and($this->context->owner())->toBe($this->owner)
        ->and($this->context->agentKey())->toBe('support');
});

it('isConfigured returns true with only owner', function () {
    $this->context->configure($this->owner, null);

    expect($this->context->isConfigured())->toBeTrue();
});

it('isConfigured returns true with only agent key', function () {
    $this->context->configure(null, 'support');

    expect($this->context->isConfigured())->toBeTrue();
});

it('isConfigured returns true even when both values are null', function () {
    $this->context->configure(null, null);

    expect($this->context->isConfigured())->toBeTrue()
        ->and($this->context->owner())->toBeNull()
        ->and($this->context->agentKey())->toBeNull();
});

it('reset clears all state', function () {
    $this->context->configure($this->owner, 'support');
    $this->context->reset();

    expect($this->context->isConfigured())->toBeFalse()
        ->and($this->context->owner())->toBeNull()
        ->and($this->context->agentKey())->toBeNull();
});
