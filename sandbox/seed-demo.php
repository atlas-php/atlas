<?php

declare(strict_types=1);

/**
 * Seeds polished demo conversations for promotional screenshots.
 *
 * Run after `php artisan sandbox:fresh`:
 *   php seed-demo.php
 *
 * Produces four threads, one per showcase: a fun text chat (Atlas), an
 * image-edit-from-upload (Iris), a scenic image (Iris), and a cat video (Reel).
 */

use App\Models\User;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Illuminate\Support\Facades\Storage;

$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);

function startThread(User $user, string $agent): int
{
    return Conversation::create([
        'owner_type' => $user->getMorphClass(),
        'owner_id' => $user->getKey(),
        'agent' => $agent,
    ])->id;
}

/**
 * @param  array<int, Image>  $media
 */
function turn(User $user, string $agent, int $cid, string $text, array $media = []): void
{
    echo "  >>> {$text}\n";
    $resp = Atlas::agent($agent)
        ->for($user)
        ->forConversation($cid)
        ->message($text, $media)
        ->asText();
    echo '  <<< '.mb_substr($resp->text ?? '', 0, 140)."\n\n";
}

// ─── 1. Fun text conversation (Atlas) ────────────────────────────────────────
echo "[1/4] Atlas — fun text conversation\n";
$cid = startThread($user, 'atlas');
turn($user, 'atlas', $cid, 'Settle a heated debate for me: is a hotdog a sandwich? Argue your case with full confidence. 🌭');
turn($user, 'atlas', $cid, 'Bold take. Now flip sides and defend the exact opposite just as passionately.');

// ─── 2. Image edit from a user upload (Iris) ─────────────────────────────────
echo "[2/4] Iris — edit an uploaded image\n";
echo "  (generating a base subject to 'upload')\n";
$base = Atlas::image()
    ->instructions('A happy corgi sitting in a sunny park, professional pet photography, soft natural light')
    ->asImage();

$baseBytes = null;
if ($base->asset !== null) {
    $baseBytes = Storage::disk($base->asset->disk)->get($base->asset->path);
    $baseMime = $base->asset->mime_type;
} elseif (is_string($base->base64) && $base->base64 !== '') {
    $baseBytes = base64_decode($base->base64);
    $baseMime = 'image/'.($base->format ?? 'png');
}

if ($baseBytes === null) {
    echo "  !! could not obtain base image bytes — skipping edit demo\n\n";
} else {
    $upload = Image::fromBase64(base64_encode($baseBytes), $baseMime ?? 'image/png');
    $cid = startThread($user, 'iris');
    turn($user, 'iris', $cid, "Here's a photo of my corgi! Can you give him a tiny wizard hat and a sparkly magical background? ✨", [$upload]);
}

// ─── 3. Generate a scenic image (Iris) ───────────────────────────────────────
echo "[3/4] Iris — scenic image\n";
$cid = startThread($user, 'iris');
turn($user, 'iris', $cid, 'Generate a scenic image of a misty mountain lake at sunrise, with pine trees and a lone wooden canoe on the water.');

// ─── 4. Funny cat video (Reel) ───────────────────────────────────────────────
echo "[4/4] Reel — funny cat video\n";
$cid = startThread($user, 'reel');
turn($user, 'reel', $cid, 'Make a funny short video of a cat dramatically knocking a cup off a table in slow motion.');

echo "Done. Conversations:\n";
foreach (Conversation::orderBy('id')->get() as $c) {
    $count = $c->messages()->count();
    echo "  #{$c->id}  agent={$c->agent}  messages={$count}\n";
}
