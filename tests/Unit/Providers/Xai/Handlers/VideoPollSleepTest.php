<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers {
    // Intercept sleep() within the handler namespace so the `pollInterval > 0`
    // branch of pollForCompletion() is covered without an actual delay. Records
    // each requested interval so the test can assert it was called.
    function sleep(int $seconds): int
    {
        $GLOBALS['__atlas_xai_poll_sleeps'][] = $seconds;

        return 0;
    }
}

namespace {
    use Atlasphp\Atlas\Http\HttpClient;
    use Atlasphp\Atlas\Providers\ProviderConfig;
    use Atlasphp\Atlas\Providers\Xai\Handlers\Video;
    use Atlasphp\Atlas\Requests\VideoRequest;
    use Atlasphp\Atlas\Responses\VideoResponse;
    use Illuminate\Support\Facades\Http;

    it('sleeps for the poll interval between polls when pollInterval > 0', function () {
        $GLOBALS['__atlas_xai_poll_sleeps'] = [];

        Http::fake([
            'api.x.ai/v1/videos/generations' => Http::response(['request_id' => 'vid_sleep']),
            'api.x.ai/v1/videos/vid_sleep' => Http::response([
                'status' => 'done',
                'video' => ['url' => 'https://cdn.x.ai/videos/vid_sleep.mp4'],
            ]),
        ]);

        $handler = new Video(
            config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.x.ai/v1']),
            http: app(HttpClient::class),
            pollInterval: 7,
            maxAttempts: 1,
        );

        $response = $handler->video(new VideoRequest(
            model: 'grok-video',
            instructions: 'A cat playing piano',
            media: [],
            duration: null,
            ratio: null,
            format: null,
            providerOptions: [],
        ));

        expect($response)->toBeInstanceOf(VideoResponse::class)
            ->and($GLOBALS['__atlas_xai_poll_sleeps'])->toBe([7]);

        unset($GLOBALS['__atlas_xai_poll_sleeps']);
    });
}
