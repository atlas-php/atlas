<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Requests\Reasoning;

it('maps each effort to a thinking-token budget', function () {
    expect(ReasoningEffort::Minimal->toBudgetTokens())->toBe(1024)
        ->and(ReasoningEffort::Low->toBudgetTokens())->toBe(4096)
        ->and(ReasoningEffort::Medium->toBudgetTokens())->toBe(8192)
        ->and(ReasoningEffort::High->toBudgetTokens())->toBe(16000);
});

it('derives the budget from the effort when none is given', function () {
    expect((new Reasoning(ReasoningEffort::Medium))->budgetTokens())->toBe(8192);
});

it('prefers an explicit budget over the effort default', function () {
    expect((new Reasoning(ReasoningEffort::Low, budgetTokens: 20000))->budgetTokens())->toBe(20000);
});

it('round-trips through toArray/fromArray', function () {
    $reasoning = new Reasoning(ReasoningEffort::High, budgetTokens: 12000, includeSummary: true);

    $restored = Reasoning::fromArray($reasoning->toArray());

    expect($restored->effort)->toBe(ReasoningEffort::High)
        ->and($restored->budgetTokens)->toBe(12000)
        ->and($restored->includeSummary)->toBeTrue();
});

it('restores a null budget from array', function () {
    $restored = Reasoning::fromArray((new Reasoning(ReasoningEffort::Low))->toArray());

    expect($restored->budgetTokens)->toBeNull()
        ->and($restored->includeSummary)->toBeFalse();
});
