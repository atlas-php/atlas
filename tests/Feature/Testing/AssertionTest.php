<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Testing\TextResponseFake;
use PHPUnit\Framework\AssertionFailedError;

// ─── assertSent ──────────────────────────────────────────────────────────────

it('assertSent passes after a call', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSent();
});

it('assertSent fails with no calls', function () {
    $fake = Atlas::fake();

    $fake->assertSent();
})->throws(AssertionFailedError::class);

// ─── assertNothingSent ───────────────────────────────────────────────────────

it('assertNothingSent passes with no calls', function () {
    $fake = Atlas::fake();

    $fake->assertNothingSent();
});

it('assertNothingSent fails after a call', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertNothingSent();
})->throws(AssertionFailedError::class);

// ─── assertSentCount ─────────────────────────────────────────────────────────

it('assertSentCount passes with exact count', function () {
    $fake = Atlas::fake([TextResponseFake::make(), TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('one')->asText();
    Atlas::text('openai', 'gpt-4o')->message('two')->asText();

    $fake->assertSentCount(2);
});

it('assertSentCount fails on mismatch', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('one')->asText();

    $fake->assertSentCount(5);
})->throws(AssertionFailedError::class);

// ─── assertSentTo ────────────────────────────────────────────────────────────

it('assertSentTo passes with correct provider and model', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSentTo('openai', 'gpt-4o');
});

it('assertSentTo passes with Provider enum', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text(Provider::OpenAI, 'gpt-4o')->message('hello')->asText();

    $fake->assertSentTo(Provider::OpenAI, 'gpt-4o');
});

it('assertSentTo fails with wrong provider', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSentTo('anthropic', 'gpt-4o');
})->throws(AssertionFailedError::class);

it('assertSentTo fails with wrong model', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSentTo('openai', 'gpt-3.5-turbo');
})->throws(AssertionFailedError::class);

// ─── assertMethodCalled ──────────────────────────────────────────────────────

it('assertMethodCalled passes for called method', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertMethodCalled('text');
});

it('assertMethodCalled fails for uncalled method', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertMethodCalled('image');
})->throws(AssertionFailedError::class);

// ─── assertInstructionsContain ───────────────────────────────────────────────

it('assertInstructionsContain passes with matching text', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->instructions('You are a helpful assistant')->message('hello')->asText();

    $fake->assertInstructionsContain('helpful');
});

it('assertInstructionsContain fails with non-matching text', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->instructions('You are a helpful assistant')->message('hello')->asText();

    $fake->assertInstructionsContain('missing');
})->throws(AssertionFailedError::class);

// ─── assertMessageContains ───────────────────────────────────────────────────

it('assertMessageContains passes with matching text', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('Hi there')->asText();

    $fake->assertMessageContains('Hi');
});

it('assertMessageContains fails with non-matching text', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('Hi there')->asText();

    $fake->assertMessageContains('missing');
})->throws(AssertionFailedError::class);

// ─── assertSentWith ──────────────────────────────────────────────────────────

it('assertSentWith passes with matching callback', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSentWith(fn ($request) => $request->method === 'text' && $request->model === 'gpt-4o');
});

it('assertSentWith fails with non-matching callback', function () {
    $fake = Atlas::fake([TextResponseFake::make()]);

    Atlas::text('openai', 'gpt-4o')->message('hello')->asText();

    $fake->assertSentWith(fn ($request) => $request->method === 'image');
})->throws(AssertionFailedError::class);
