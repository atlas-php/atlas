<?php

declare(strict_types=1);

use Atlasphp\Atlas\Tools\Enums\ToolChoice;

test('it has Auto case', function () {
    expect(ToolChoice::Auto->value)->toBe('auto');
});

test('it has Any case', function () {
    expect(ToolChoice::Any->value)->toBe('any');
});

test('it has None case', function () {
    expect(ToolChoice::None->value)->toBe('none');
});

test('all cases are available', function () {
    $cases = ToolChoice::cases();

    expect($cases)->toHaveCount(3);
    expect($cases[0])->toBe(ToolChoice::Auto);
    expect($cases[1])->toBe(ToolChoice::Any);
    expect($cases[2])->toBe(ToolChoice::None);
});
