<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Support\HasStructuredModeSupport;
use Prism\Prism\Enums\StructuredMode;

beforeEach(function () {
    $this->instance = new class
    {
        use HasStructuredModeSupport;

        public function getMode(): ?StructuredMode
        {
            return $this->getStructuredMode();
        }
    };
});

test('structured mode is null by default', function () {
    expect($this->instance->getMode())->toBeNull();
});

test('usingStructuredMode sets the mode', function () {
    $result = $this->instance->usingStructuredMode(StructuredMode::Json);

    expect($result->getMode())->toBe(StructuredMode::Json);
});

test('usingStructuredMode returns new instance (immutable)', function () {
    $result = $this->instance->usingStructuredMode(StructuredMode::Json);

    expect($result)->not->toBe($this->instance);
    expect($this->instance->getMode())->toBeNull();
});

test('usingJsonMode sets Json mode', function () {
    $result = $this->instance->usingJsonMode();

    expect($result->getMode())->toBe(StructuredMode::Json);
});

test('usingNativeMode sets Structured mode', function () {
    $result = $this->instance->usingNativeMode();

    expect($result->getMode())->toBe(StructuredMode::Structured);
});

test('usingAutoMode sets Auto mode', function () {
    $result = $this->instance->usingAutoMode();

    expect($result->getMode())->toBe(StructuredMode::Auto);
});

test('convenience methods return new instance (immutable)', function () {
    $jsonResult = $this->instance->usingJsonMode();
    $nativeResult = $this->instance->usingNativeMode();
    $autoResult = $this->instance->usingAutoMode();

    expect($jsonResult)->not->toBe($this->instance);
    expect($nativeResult)->not->toBe($this->instance);
    expect($autoResult)->not->toBe($this->instance);
});

test('modes can be chained and overridden', function () {
    $result = $this->instance
        ->usingJsonMode()
        ->usingNativeMode()
        ->usingAutoMode();

    expect($result->getMode())->toBe(StructuredMode::Auto);
});
