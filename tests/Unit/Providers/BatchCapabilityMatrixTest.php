<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\ChatCompletions\ChatCompletionsDriver;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\ElevenLabs\ElevenLabsDriver;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;

function driverFor(string $class): object
{
    $config = new ProviderConfig(apiKey: 'test', baseUrl: 'https://api.test.com');

    return new $class($config, Mockery::mock(HttpClient::class));
}

it('declares the exact batch modality matrix per provider', function (string $class, array $expected) {
    $caps = driverFor($class)->capabilities();

    expect($caps->batchModalities)->toBe($expected);

    foreach ($expected as $modality) {
        expect($caps->canBatch($modality))->toBeTrue();
    }
})->with([
    'openai' => [OpenAiDriver::class, ['text', 'embed']],
    'anthropic' => [AnthropicDriver::class, ['text']],
    'google' => [GoogleDriver::class, ['text']],
]);

it('marks non-batch providers explicitly as not batchable', function (string $class) {
    $caps = driverFor($class)->capabilities();

    expect($caps->supports('batch'))->toBeFalse();
    expect($caps->batchModalities)->toBe([]);
    expect($caps->canBatch('text'))->toBeFalse();
})->with([
    // xAI has a batch API but its handler is a documented follow-up; not
    // advertised yet.
    'xai' => [XaiDriver::class],
    'elevenlabs' => [ElevenLabsDriver::class],
    'cohere' => [CohereDriver::class],
    'jina' => [JinaDriver::class],
    'chatcompletions' => [ChatCompletionsDriver::class],
]);

it('rejects modalities outside the provider allow-list', function () {
    $openai = driverFor(OpenAiDriver::class)->capabilities();

    // OpenAI batches text + embed only in v1
    expect($openai->canBatch('text'))->toBeTrue();
    expect($openai->canBatch('embed'))->toBeTrue();
    expect($openai->canBatch('voice'))->toBeFalse();
    expect($openai->canBatch('rerank'))->toBeFalse();
    expect($openai->canBatch('moderate'))->toBeFalse();
    expect($openai->canBatch('image'))->toBeFalse();
});
