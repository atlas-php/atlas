<?php

declare(strict_types=1);

use Atlasphp\Atlas\Support\TokenCounter;

it('returns zero for empty input', function () {
    expect(TokenCounter::count(''))->toBe(0);
});

it('uses chars-over-four heuristic', function () {
    expect(TokenCounter::count('1234'))->toBe(1);
    expect(TokenCounter::count('12345678'))->toBe(2);
});

it('rounds up for non-multiples of four', function () {
    expect(TokenCounter::count('12345'))->toBe(2);
});

it('counts multibyte characters by character not byte', function () {
    expect(TokenCounter::count('héllo'))->toBe(2);
});
