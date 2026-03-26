<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Moderate;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Illuminate\Support\Facades\Http;

it('sends moderation request to /v1/moderations', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                [
                    'flagged' => true,
                    'categories' => ['harassment' => true, 'hate' => false],
                    'category_scores' => ['harassment' => 0.95, 'hate' => 0.01],
                ],
            ],
        ]),
    ]);

    $handler = new Moderate(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $request = new ModerateRequest(model: 'omni-moderation-latest', input: 'offensive content');

    $response = $handler->moderate($request);

    expect($response)->toBeInstanceOf(ModerationResponse::class);
    expect($response->flagged)->toBeTrue();
    expect($response->categories)->toBe(['harassment' => true, 'hate' => false]);
    expect($response->meta['category_scores'])->toBe(['harassment' => 0.95, 'hate' => 0.01]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/moderations'
            && $request['model'] === 'omni-moderation-latest';
    });
});

it('handles safe content', function () {
    Http::fake([
        'api.openai.com/v1/moderations' => Http::response([
            'results' => [
                ['flagged' => false, 'categories' => [], 'category_scores' => []],
            ],
        ]),
    ]);

    $handler = new Moderate(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $request = new ModerateRequest(model: 'omni-moderation-latest', input: 'hello');
    $response = $handler->moderate($request);

    expect($response->flagged)->toBeFalse();
});
