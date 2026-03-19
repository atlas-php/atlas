<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pending\AgentRequest;

it('accepts fluent calls without throwing', function () {
    $agent = new AgentRequest('support');

    expect($agent->message('hello'))->toBe($agent);
    expect($agent->instructions('be helpful'))->toBe($agent);
    expect($agent->withTools(['search']))->toBe($agent);
});

it('throws on asText', function () {
    (new AgentRequest('support'))->asText();
})->throws(RuntimeException::class, 'Agent execution requires Phase 7.');

it('throws on asStream', function () {
    (new AgentRequest('support'))->asStream();
})->throws(RuntimeException::class, 'Agent execution requires Phase 7.');

it('throws on asStructured', function () {
    (new AgentRequest('support'))->asStructured();
})->throws(RuntimeException::class, 'Agent execution requires Phase 7.');
