<?php

declare(strict_types=1);

/**
 * Live end-to-end proof that a user's attached image is replayed from
 * conversation history and the model can actually SEE it.
 *
 * For each vision-capable provider it: persists an image-only user message plus
 * a text question as EXISTING history, then runs respond() — which rebuilds the
 * turn entirely from stored history (loadMessages → media rehydration → group
 * remap → media-replay window). The model must read a number drawn in the image,
 * which it can only do if the image truly reached the provider through replay.
 */

use App\Models\User;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\ConversationMessageAsset;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Support\Facades\Storage;

$app = require __DIR__.'/bootstrap.php';

// Minimal, tool-free agent so we isolate vision-through-replay (no web search
// etc.). A fresh instance is built per provider — HasConversations caches the
// resolved conversation on the instance, so reuse would leak state.
$makeAgent = fn (): Agent => new class extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'vision-replay-test';
    }

    public function instructions(): ?string
    {
        return 'You can see images shared earlier in the conversation. Answer concisely.';
    }
};

$user = User::query()->firstOrCreate(
    ['email' => 'sandbox@atlas.test'],
    ['name' => 'Sandbox User'],
);
$cs = app(ConversationService::class);

// Draw a big white "42" on a solid blue background — unguessable without vision.
function makeImage(): string
{
    $small = imagecreatetruecolor(80, 40);
    imagefill($small, 0, 0, imagecolorallocate($small, 20, 60, 200)); // blue
    $white = imagecolorallocate($small, 255, 255, 255);
    imagestring($small, 5, 28, 12, '42', $white);
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
    echo "\n── {$name} ({$model})\n";

    try {
        $conversation = $cs->findOrCreate($user, 'assistant');

        // Store the image as an owned asset on disk.
        $png = makeImage();
        $path = "atlas/vision-test/{$conversation->id}.png";
        Storage::disk('local')->put($path, $png);
        $asset = Asset::create([
            'type' => AssetType::Image,
            'mime_type' => 'image/png',
            'filename' => 'card.png',
            'path' => $path,
            'disk' => 'local',
            'size_bytes' => strlen($png),
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
        ]);

        // Persist EXISTING history: image bubble, then the question.
        $imageMsg = $cs->addMessage($conversation, new UserMessage(content: ''), owner: $user);
        ConversationMessageAsset::create(['message_id' => $imageMsg->id, 'asset_id' => $asset->id]);
        $cs->addMessage(
            $conversation,
            new UserMessage(content: 'Look at the image I sent. What number is written on it, and what is the background color? Answer in one short sentence.'),
            owner: $user,
        );

        // respond() rebuilds the turn from stored history only.
        $r = Atlas::agent('vision-replay-test')
            ->forInstance($makeAgent())
            ->withProvider($provider, $model)
            ->for($user)
            ->forConversation($conversation->id)
            ->respond()
            ->asText();

        $text = trim($r->text);
        $seen = str_contains($text, '42');
        echo "   reply: {$text}\n";
        echo '   '.($seen ? '✓ model SAW the image (read "42")' : '✗ model did NOT see the image')."\n";

        // cleanup
        $conversation->messages()->forceDelete();
        $conversation->forceDelete();
        $asset->delete();
        Storage::disk('local')->delete($path);
    } catch (Throwable $e) {
        echo '   ERROR: '.$e->getMessage()."\n";
    }
}

echo "\n";
