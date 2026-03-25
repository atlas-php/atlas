<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\XSearch;

it('extends ProviderTool', function () {
    expect(new XSearch)->toBeInstanceOf(ProviderTool::class);
});

it('has correct type', function () {
    expect((new XSearch)->type())->toBe('x_search');
});

it('returns minimal config by default', function () {
    expect((new XSearch)->toArray())->toBe(['type' => 'x_search']);
});

it('includes date filters when provided', function () {
    $tool = new XSearch(fromDate: '2025-01-01', toDate: '2025-12-31');

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'from_date' => '2025-01-01',
        'to_date' => '2025-12-31',
    ]);
});

it('includes allowed handles when provided', function () {
    $tool = new XSearch(allowedXHandles: ['@elonmusk', '@xai']);

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'allowed_x_handles' => ['@elonmusk', '@xai'],
    ]);
});

it('includes image understanding when enabled', function () {
    $tool = new XSearch(enableImageUnderstanding: true);

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'enable_image_understanding' => true,
    ]);
});

it('includes video understanding when enabled', function () {
    $tool = new XSearch(enableVideoUnderstanding: true);

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'enable_video_understanding' => true,
    ]);
});

it('excludes image understanding when explicitly false', function () {
    $tool = new XSearch(enableImageUnderstanding: false);

    expect($tool->toArray())->toBe(['type' => 'x_search']);
});

it('excludes video understanding when explicitly false', function () {
    $tool = new XSearch(enableVideoUnderstanding: false);

    expect($tool->toArray())->toBe(['type' => 'x_search']);
});

it('includes only enabled booleans when mixed true and false', function () {
    $tool = new XSearch(enableImageUnderstanding: true, enableVideoUnderstanding: false);

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'enable_image_understanding' => true,
    ]);
});

it('includes all config when fully configured', function () {
    $tool = new XSearch(
        fromDate: '2025-01-01',
        toDate: '2025-06-30',
        allowedXHandles: ['@xai'],
        enableImageUnderstanding: true,
        enableVideoUnderstanding: true,
    );

    expect($tool->toArray())->toBe([
        'type' => 'x_search',
        'from_date' => '2025-01-01',
        'to_date' => '2025-06-30',
        'allowed_x_handles' => ['@xai'],
        'enable_image_understanding' => true,
        'enable_video_understanding' => true,
    ]);
});
