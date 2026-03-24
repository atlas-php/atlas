<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\WebSocketConnection;
use Atlasphp\Atlas\Responses\VoiceEvent;
use WebSocket\Client;
use WebSocket\TimeoutException;

function createMockConnection(?Client $client = null): WebSocketConnection
{
    $client ??= Mockery::mock(Client::class);

    return new WebSocketConnection($client, 'test-session-123');
}

it('stores session ID', function () {
    $conn = createMockConnection();

    expect($conn->sessionId)->toBe('test-session-123');
});

it('sends event as JSON over WebSocket', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('text')
        ->once()
        ->withArgs(function (string $json) {
            $data = json_decode($json, true);

            return $data['type'] === 'session.update'
                && $data['event_id'] === 'evt_1'
                && $data['voice'] === 'alloy';
        });

    $conn = createMockConnection($client);
    $conn->send(new VoiceEvent(
        type: 'session.update',
        eventId: 'evt_1',
        data: ['voice' => 'alloy'],
    ));
});

it('sends event without event_id when null', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('text')
        ->once()
        ->withArgs(function (string $json) {
            $data = json_decode($json, true);

            return $data['type'] === 'input_audio_buffer.append'
                && ! isset($data['event_id'])
                && $data['audio'] === 'base64data';
        });

    $conn = createMockConnection($client);
    $conn->send(new VoiceEvent(
        type: 'input_audio_buffer.append',
        data: ['audio' => 'base64data'],
    ));
});

it('receives and parses a provider event', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')
        ->once()
        ->andReturn('{"type":"response.done","event_id":"evt_99","usage":{"tokens":10}}');

    $conn = createMockConnection($client);
    $event = $conn->receive();

    expect($event)->toBeInstanceOf(VoiceEvent::class);
    expect($event->type)->toBe('response.done');
    expect($event->eventId)->toBe('evt_99');
    expect($event->data)->toBe(['usage' => ['tokens' => 10]]);
});

it('returns null on empty message', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')->once()->andReturn('');

    $conn = createMockConnection($client);

    expect($conn->receive())->toBeNull();
});

it('returns null on timeout exception', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')
        ->once()
        ->andThrow(new TimeoutException('timeout'));

    $conn = createMockConnection($client);

    expect($conn->receive())->toBeNull();
});

it('returns null on malformed JSON', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')
        ->once()
        ->andReturn('not valid json {{{');

    $conn = createMockConnection($client);

    expect($conn->receive())->toBeNull();
});

it('delegates close to client', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('close')->once();

    $conn = createMockConnection($client);
    $conn->close();
});

it('delegates isConnected to client', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('isConnected')->once()->andReturn(true);

    $conn = createMockConnection($client);

    expect($conn->isConnected())->toBeTrue();
});

it('handles event with no event_id in response', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')
        ->once()
        ->andReturn('{"type":"ping","timestamp":12345}');

    $conn = createMockConnection($client);
    $event = $conn->receive();

    expect($event->type)->toBe('ping');
    expect($event->eventId)->toBeNull();
    expect($event->data)->toBe(['timestamp' => 12345]);
});

it('defaults to unknown type when type is missing', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')
        ->once()
        ->andReturn('{"data":"something"}');

    $conn = createMockConnection($client);
    $event = $conn->receive();

    expect($event->type)->toBe('unknown');
});
