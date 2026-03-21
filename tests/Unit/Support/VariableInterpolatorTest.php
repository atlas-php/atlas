<?php

declare(strict_types=1);

use Atlasphp\Atlas\Support\VariableInterpolator;

it('interpolates a flat key', function () {
    $result = VariableInterpolator::interpolate('Hello {NAME}', ['NAME' => 'Tim']);

    expect($result)->toBe('Hello Tim');
});

it('interpolates dot notation', function () {
    $result = VariableInterpolator::interpolate(
        'Welcome to {COMPANY.NAME}',
        ['COMPANY' => ['NAME' => 'Acme']],
    );

    expect($result)->toBe('Welcome to Acme');
});

it('interpolates multi-level dot notation', function () {
    $result = VariableInterpolator::interpolate(
        '{A.B.C.D}',
        ['A' => ['B' => ['C' => ['D' => 'deep']]]],
    );

    expect($result)->toBe('deep');
});

it('leaves unknown placeholders as-is', function () {
    $result = VariableInterpolator::interpolate('Hello {UNKNOWN}', ['NAME' => 'Tim']);

    expect($result)->toBe('Hello {UNKNOWN}');
});

it('flat key wins over dot traversal', function () {
    $result = VariableInterpolator::interpolate(
        '{COMPANY.NAME}',
        [
            'COMPANY.NAME' => 'Flat Wins',
            'COMPANY' => ['NAME' => 'Dot Loses'],
        ],
    );

    expect($result)->toBe('Flat Wins');
});

it('returns null for array values via flat key', function () {
    $result = VariableInterpolator::interpolate(
        '{COMPANY}',
        ['COMPANY' => ['NAME' => 'Acme']],
    );

    expect($result)->toBe('{COMPANY}');
});

it('returns null for array values via dot traversal', function () {
    $result = VariableInterpolator::interpolate(
        '{A.B}',
        ['A' => ['B' => ['C' => 'nested']]],
    );

    expect($result)->toBe('{A.B}');
});

it('detects placeholders with hasPlaceholders', function () {
    expect(VariableInterpolator::hasPlaceholders('Hello {NAME}'))->toBeTrue();
    expect(VariableInterpolator::hasPlaceholders('Hello world'))->toBeFalse();
    expect(VariableInterpolator::hasPlaceholders('{COMPANY.NAME}'))->toBeTrue();
});

it('does not match invalid patterns', function () {
    $vars = ['123' => 'num', '.NAME' => 'dot', '' => 'empty', ' NAME ' => 'space'];

    expect(VariableInterpolator::interpolate('{123}', $vars))->toBe('{123}');
    expect(VariableInterpolator::interpolate('{.NAME}', $vars))->toBe('{.NAME}');
    expect(VariableInterpolator::interpolate('{}', $vars))->toBe('{}');
    expect(VariableInterpolator::interpolate('{ NAME }', $vars))->toBe('{ NAME }');
});

it('handles empty string input', function () {
    expect(VariableInterpolator::interpolate('', ['NAME' => 'Tim']))->toBe('');
});

it('interpolates mixed case variables', function () {
    $result = VariableInterpolator::interpolate('{CompanyName}', ['CompanyName' => 'Acme']);

    expect($result)->toBe('Acme');
});

it('interpolates underscore variables', function () {
    $result = VariableInterpolator::interpolate('{SUPPORT_EMAIL}', ['SUPPORT_EMAIL' => 'help@acme.com']);

    expect($result)->toBe('help@acme.com');
});

it('interpolates dot and underscore combined', function () {
    $result = VariableInterpolator::interpolate(
        '{COMPANY.SUPPORT_EMAIL}',
        ['COMPANY' => ['SUPPORT_EMAIL' => 'help@acme.com']],
    );

    expect($result)->toBe('help@acme.com');
});

it('interpolates multiple placeholders in one string', function () {
    $result = VariableInterpolator::interpolate(
        'Hi {USER}, welcome to {COMPANY.NAME}!',
        ['USER' => 'Tim', 'COMPANY' => ['NAME' => 'Acme']],
    );

    expect($result)->toBe('Hi Tim, welcome to Acme!');
});

it('resolves returns null for missing flat key', function () {
    expect(VariableInterpolator::resolve('MISSING', ['OTHER' => 'val']))->toBeNull();
});

it('resolves returns null for missing dot key segment', function () {
    expect(VariableInterpolator::resolve('A.B.C', ['A' => ['X' => 'val']]))->toBeNull();
});
