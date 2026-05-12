<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;

it('returns the framework default connection when atlas.persistence.connection is not set', function () {
    config()->set('atlas.persistence.connection', null);
    AtlasConfig::refresh();

    expect((new Conversation)->getConnectionName())->toBeNull();
    expect((new ConversationMessage)->getConnectionName())->toBeNull();
    expect((new Execution)->getConnectionName())->toBeNull();
});

it('returns the configured connection when atlas.persistence.connection is set', function () {
    config()->set('atlas.persistence.connection', 'analytics');
    AtlasConfig::refresh();

    expect((new Conversation)->getConnectionName())->toBe('analytics');
    expect((new ConversationMessage)->getConnectionName())->toBe('analytics');
    expect((new Execution)->getConnectionName())->toBe('analytics');
});

it('applies the table prefix on top of the configured connection', function () {
    config()->set('atlas.persistence.connection', 'analytics');
    config()->set('atlas.persistence.table_prefix', 'custom_');
    AtlasConfig::refresh();

    $conversation = new Conversation;

    expect($conversation->getConnectionName())->toBe('analytics');
    expect($conversation->getTable())->toBe('custom_conversations');
});
