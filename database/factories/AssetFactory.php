<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Database\Factories;

use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $type = $this->faker->randomElement(AssetType::cases());
        $extension = match ($type) {
            AssetType::Image => 'png',
            AssetType::Audio => 'mp3',
            AssetType::Video => 'mp4',
            AssetType::Document => 'pdf',
            default => 'bin',
        };
        $filename = Str::random(40).'.'.$extension;

        return [
            'type' => $type,
            'mime_type' => 'application/octet-stream',
            'filename' => $filename,
            'original_filename' => $this->faker->word().'.'.$extension,
            'path' => 'atlas/test/'.$filename,
            'disk' => 'local',
            'size_bytes' => $this->faker->numberBetween(1024, 10485760),
            'content_hash' => hash('sha256', Str::random()),
            'description' => null,
            'author_type' => null,
            'author_id' => null,
            'agent' => null,
            'execution_id' => null,
            'metadata' => null,
        ];
    }

    public function image(): static
    {
        return $this->state([
            'type' => AssetType::Image,
            'mime_type' => 'image/png',
        ]);
    }

    public function audio(): static
    {
        return $this->state([
            'type' => AssetType::Audio,
            'mime_type' => 'audio/mpeg',
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'type' => AssetType::Video,
            'mime_type' => 'video/mp4',
        ]);
    }

    public function byAgent(string $agent): static
    {
        return $this->state(['agent' => $agent]);
    }
}
