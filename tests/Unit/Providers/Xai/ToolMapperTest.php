<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Xai\ToolMapper;

it('falls back to the id field when call_id is a bare numeric index', function () {
    // Some xAI grok models return a sequential index as call_id; the real id
    // lives in the `id` field.
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        [
            'call_id' => '0',
            'name' => 'generate_image',
            'arguments' => '{"prompt":"test"}',
            'id' => 'fc_abc123-def456_0',
        ],
    ]);

    expect($result[0]->id)->toBe('fc_abc123-def456_0');
    expect($result[0]->name)->toBe('generate_image');
});

it('uses call_id when it is a proper identifier', function () {
    $mapper = new ToolMapper;

    $result = $mapper->parseToolCalls([
        ['call_id' => 'call_abc123', 'name' => 'search', 'arguments' => '{}', 'id' => 'fc_x'],
    ]);

    expect($result[0]->id)->toBe('call_abc123');
});
