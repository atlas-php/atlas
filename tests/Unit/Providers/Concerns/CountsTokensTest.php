<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Concerns\CountsTokens;
use Atlasphp\Atlas\Responses\TokenCount;
use Atlasphp\Atlas\Support\TokenCounter;

function makeTokenEstimator(): object
{
    return new class
    {
        use CountsTokens;

        /**
         * @param  array<string, mixed>  $payload
         */
        public function estimate(string $provider, string $model, array $payload): TokenCount
        {
            return $this->estimateTokens($provider, $model, $payload);
        }
    };
}

it('flags the estimate and attributes provider/model', function () {
    $count = makeTokenEstimator()->estimate('ollama', 'llama3', ['input' => 'Hello']);

    expect($count->estimated)->toBeTrue()
        ->and($count->provider)->toBe('ollama')
        ->and($count->model)->toBe('llama3');
});

it('sums the chars/4 heuristic over every string leaf, recursively', function () {
    $payload = [
        'model' => 'ignored-but-counted',
        'messages' => [
            ['role' => 'user', 'content' => 'aaaaaaaa'],   // 8 chars -> 2
            ['role' => 'system', 'content' => 'bbbb'],      // 4 chars -> 1
        ],
        'max_tokens' => 256, // non-string, ignored
    ];

    $expected = TokenCounter::count('ignored-but-counted')
        + TokenCounter::count('user') + TokenCounter::count('aaaaaaaa')
        + TokenCounter::count('system') + TokenCounter::count('bbbb');

    expect(makeTokenEstimator()->estimate('x', 'y', $payload)->inputTokens)->toBe($expected);
});
