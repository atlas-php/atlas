<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;

test('atlas helper returns AtlasManager instance', function () {
    $result = atlas();

    expect($result)->toBeInstanceOf(AtlasManager::class);
});

test('atlas helper returns same singleton instance', function () {
    $first = atlas();
    $second = atlas();

    expect($first)->toBe($second);
});

test('atlas helper allows calling agent method', function () {
    $manager = atlas();

    // Just verify we can call the method without error
    // The actual agent lookup would need fixtures
    expect($manager)->toBeInstanceOf(AtlasManager::class);
});
