<?php

declare(strict_types=1);

/**
 * Live proof that `media_replay_limit` gates whether a history image reaches the
 * model — exercised against real provider APIs, one process per limit.
 *
 * Each conversation is: a context line, the IMAGE, then the question (image is
 * the 2nd-from-last message). The limit is set at boot from
 * ATLAS_MEDIA_REPLAY_LIMIT (no mid-run config refresh, so it matches production):
 *
 *   ATLAS_MEDIA_REPLAY_LIMIT=1 php test-media-config.php   → image OUTSIDE window
 *   ATLAS_MEDIA_REPLAY_LIMIT=2 php test-media-config.php   → image INSIDE window
 *
 * Prints the image blocks actually sent to each provider AND the model's reply.
 */

use App\Models\User;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Support\Facades\Storage;

$app = require __DIR__.'/bootstrap.php';

$limit = AtlasConfig::fromConfig()->mediaReplayLimit;
echo "media_replay_limit = ".var_export($limit, true)." (image is the 2nd-from-last message)\n";

$makeAgent = fn (): Agent => new class extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'media-config-test';
    }

    public function instructions(): ?string
    {
        return 'You can see images shared earlier in the conversation. If no image is visible to you, reply exactly NONE. Otherwise answer concisely.';
    }
};

$user = User::query()->firstOrCreate(['email' => 'sandbox@atlas.test'], ['name' => 'Sandbox User']);
$cs = app(ConversationService::class);

function makeImage(): string
{
    $small = imagecreatetruecolor(80, 40);
    imagefill($small, 0, 0, imagecolorallocate($small, 20, 60, 200));
    imagestring($small, 5, 28, 12, '42', imagecolorallocate($small, 255, 255, 255));
    $big = imagescale($small, 320, 160);
    ob_start();
    imagepng($big);
    $png = (string) ob_get_clean();
    imagedestroy($small);
    imagedestroy($big);

    return $png;
}

$cases = [
    ['Anthropic', Provider::Anthropic, 'claude-sonnet-4-5-20250929'],
    ['OpenAI', Provider::OpenAI, 'gpt-4o-mini'],
    ['Google', Provider::Google, 'gemini-2.5-flash'],
    ['xAI', Provider::xAI, 'grok-4.3'],
];

foreach ($cases as [$name, $provider, $model]) {
    try {
        $conversation = $cs->findOrCreate($user, 'mediacfg-'.strtolower($name));

        $png = makeImage();
        $path = "atlas/media-config/{$conversation->id}.png";
        Storage::disk('local')->put($path, $png);
        $asset = Asset::create([
            'type' => AssetType::Image, 'mime_type' => 'image/png', 'filename' => 'n.png',
            'path' => $path, 'disk' => 'local', 'size_bytes' => strlen($png),
            'owner_type' => $user->getMorphClass(), 'owner_id' => $user->getKey(),
        ]);

        $cs->addMessage($conversation, new UserMessage(content: 'Here is some context.'), owner: $user);
        $imageMsg = $cs->addMessage($conversation, new UserMessage(content: ''), owner: $user);
        ConversationMessageAsset::create(['message_id' => $imageMsg->id, 'asset_id' => $asset->id]);
        $cs->addMessage($conversation, new UserMessage(content: 'What number is written in the image I just sent? Reply with only the number, or NONE if you cannot see it.'), owner: $user);

        $blocks = 0;
        foreach ($cs->loadMessages($conversation, 50, 'media-config-test') as $m) {
            if ($m instanceof UserMessage) {
                $blocks += count($m->media);
            }
        }

        $r = Atlas::agent('media-config-test')
            ->forInstance($makeAgent())
            ->withProvider($provider, $model)
            ->for($user)
            ->forConversation($conversation->id)
            ->respond()
            ->asText();

        $text = trim($r->text);
        echo sprintf("   %-10s imageBlocks=%d  sees42=%s  reply=\"%s\"\n", $name, $blocks, str_contains($text, '42') ? 'YES' : 'no', $text);

        $conversation->messages()->forceDelete();
        $conversation->forceDelete();
        $asset->delete();
        Storage::disk('local')->delete($path);
    } catch (\Throwable $e) {
        echo "   {$name} ERROR: ".$e->getMessage()."\n";
    }
}

echo "\n";
