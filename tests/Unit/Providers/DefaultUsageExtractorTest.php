<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\DefaultUsageExtractor;

beforeEach(function () {
    $this->extractor = new DefaultUsageExtractor;
});

test('it returns default provider name', function () {
    expect($this->extractor->provider())->toBe('default');
});

test('it extracts usage from array response', function () {
    $response = [
        'usage' => [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ],
    ];

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
    ]);
});

test('it extracts usage from object response with camelCase properties', function () {
    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public int $promptTokens = 200;

                public int $completionTokens = 75;

                public int $totalTokens = 275;
            };
        }
    };

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 200,
        'completion_tokens' => 75,
        'total_tokens' => 275,
    ]);
});

test('it extracts usage from object response with snake_case properties', function () {
    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public int $prompt_tokens = 300;

                public int $completion_tokens = 100;

                public int $total_tokens = 400;
            };
        }
    };

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 300,
        'completion_tokens' => 100,
        'total_tokens' => 400,
    ]);
});

test('it calculates total tokens when not provided', function () {
    $response = new class
    {
        public object $usage;

        public function __construct()
        {
            $this->usage = new class
            {
                public int $promptTokens = 150;

                public int $completionTokens = 50;
                // Note: no totalTokens property
            };
        }
    };

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 150,
        'completion_tokens' => 50,
        'total_tokens' => 200, // Calculated from prompt + completion
    ]);
});

test('it returns zeros when no usage data present', function () {
    $response = ['data' => 'something'];

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ]);
});

test('it handles null response', function () {
    $result = $this->extractor->extract(null);

    expect($result)->toBe([
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ]);
});

test('it handles string response', function () {
    $result = $this->extractor->extract('invalid response');

    expect($result)->toBe([
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ]);
});

test('it handles partial array usage data', function () {
    $response = [
        'usage' => [
            'prompt_tokens' => 100,
            // Missing completion_tokens and total_tokens
        ],
    ];

    $result = $this->extractor->extract($response);

    expect($result)->toBe([
        'prompt_tokens' => 100,
        'completion_tokens' => 0,
        'total_tokens' => 0,
    ]);
});
