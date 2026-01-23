<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\ModerationService;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Atlasphp\Atlas\Providers\Support\ModerationResponse;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Prism\Prism\Moderation\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ModerationResult as PrismModerationResult;

beforeEach(function () {
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'moderation' => [
                'provider' => 'openai',
                'model' => 'omni-moderation-latest',
            ],
        ],
    ]));
    $this->container = new Container;
    $this->registry = new PipelineRegistry;
    $this->pipelineRunner = new PipelineRunner($this->registry, $this->container);
    $this->service = new ModerationService($this->prismBuilder, $this->configService, $this->pipelineRunner);
});

function createMockModerationPrismResponse(bool $flagged = false): PrismResponse
{
    $prismResult = new PrismModerationResult(
        $flagged,
        ['violence' => $flagged, 'hate' => false],
        ['violence' => $flagged ? 0.95 : 0.01, 'hate' => 0.01],
    );

    return new PrismResponse(
        [$prismResult],
        new Meta('mod-123', 'omni-moderation-latest'),
    );
}

test('it moderates with defaults', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'omni-moderation-latest', 'Hello world', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->moderate('Hello world');

    expect($result)->toBeInstanceOf(ModerationResponse::class);
    expect($result->isFlagged())->toBeFalse();
});

test('it moderates with provider override', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('anthropic', 'omni-moderation-latest', 'Hello world', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->moderate('Hello world', ['provider' => 'anthropic']);

    expect($result)->toBeInstanceOf(ModerationResponse::class);
});

test('it moderates with model override', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'text-moderation-latest', 'Hello world', [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->moderate('Hello world', ['model' => 'text-moderation-latest']);

    expect($result)->toBeInstanceOf(ModerationResponse::class);
});

test('it moderates batch inputs', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $inputs = ['Hello world', 'Another text'];

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'omni-moderation-latest', $inputs, [], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $result = $this->service->moderate($inputs);

    expect($result)->toBeInstanceOf(ModerationResponse::class);
});

test('it passes provider options to PrismBuilder', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'omni-moderation-latest', 'Hello world', ['custom_option' => true], null)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $this->service->moderate('Hello world', ['provider_options' => ['custom_option' => true]]);
});

test('it passes retry to PrismBuilder', function () {
    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();
    $retryConfig = [3, 1000, null, true];

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'omni-moderation-latest', 'Hello world', [], $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $this->service->moderate('Hello world', [], $retryConfig);
});

test('it runs moderation.before_moderate pipeline', function () {
    $this->registry->define('moderation.before_moderate', 'Before moderate pipeline');
    ModerationBeforeModerateHandler::reset();
    $this->registry->register('moderation.before_moderate', ModerationBeforeModerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $this->service->moderate('Hello world');

    expect(ModerationBeforeModerateHandler::$called)->toBeTrue();
    expect(ModerationBeforeModerateHandler::$data)->not->toBeNull();
    expect(ModerationBeforeModerateHandler::$data['input'])->toBe('Hello world');
    expect(ModerationBeforeModerateHandler::$data['provider'])->toBe('openai');
    expect(ModerationBeforeModerateHandler::$data['model'])->toBe('omni-moderation-latest');
});

test('it runs moderation.after_moderate pipeline', function () {
    $this->registry->define('moderation.after_moderate', 'After moderate pipeline');
    ModerationAfterModerateHandler::reset();
    $this->registry->register('moderation.after_moderate', ModerationAfterModerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse(flagged: true);

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $this->service->moderate('Bad content');

    expect(ModerationAfterModerateHandler::$called)->toBeTrue();
    expect(ModerationAfterModerateHandler::$data)->not->toBeNull();
    expect(ModerationAfterModerateHandler::$data['input'])->toBe('Bad content');
    expect(ModerationAfterModerateHandler::$data['result'])->toBeInstanceOf(ModerationResponse::class);
    expect(ModerationAfterModerateHandler::$data['result']->isFlagged())->toBeTrue();
});

test('it runs moderation.on_error pipeline when moderate fails', function () {
    $this->registry->define('moderation.on_error', 'Error pipeline');
    ModerationErrorHandler::reset();
    $this->registry->register('moderation.on_error', ModerationErrorHandler::class);

    $mockRequest = Mockery::mock();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->andThrow(new \RuntimeException('API Error'));

    try {
        $this->service->moderate('Hello world');
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(ModerationErrorHandler::$called)->toBeTrue();
    expect(ModerationErrorHandler::$data)->not->toBeNull();
    expect(ModerationErrorHandler::$data['input'])->toBe('Hello world');
    expect(ModerationErrorHandler::$data['provider'])->toBe('openai');
    expect(ModerationErrorHandler::$data['model'])->toBe('omni-moderation-latest');
    expect(ModerationErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
});

test('it includes metadata in pipeline data', function () {
    $this->registry->define('moderation.before_moderate', 'Before moderate pipeline');
    ModerationBeforeModerateHandler::reset();
    $this->registry->register('moderation.before_moderate', ModerationBeforeModerateHandler::class);

    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $this->service->moderate('Hello world', ['metadata' => ['user_id' => 123]]);

    expect(ModerationBeforeModerateHandler::$data['metadata'])->toBe(['user_id' => 123]);
});

test('it uses config retry when explicit retry is null', function () {
    $configRetry = [2, 500, null, true];
    $configService = new ProviderConfigService(new Repository([
        'atlas' => [
            'moderation' => [
                'provider' => 'openai',
                'model' => 'omni-moderation-latest',
            ],
            'retry' => [
                'enabled' => true,
                'times' => 2,
                'delay_ms' => 500,
            ],
        ],
    ]));

    $service = new ModerationService($this->prismBuilder, $configService, $this->pipelineRunner);

    $mockRequest = Mockery::mock();
    $mockResponse = createMockModerationPrismResponse();

    $this->prismBuilder
        ->shouldReceive('forModeration')
        ->with('openai', 'omni-moderation-latest', 'Hello world', [], $configRetry)
        ->once()
        ->andReturn($mockRequest);

    $mockRequest
        ->shouldReceive('asModeration')
        ->once()
        ->andReturn($mockResponse);

    $service->moderate('Hello world');
});

// Pipeline Handler Classes for Tests

class ModerationBeforeModerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ModerationAfterModerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class ModerationErrorHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}
