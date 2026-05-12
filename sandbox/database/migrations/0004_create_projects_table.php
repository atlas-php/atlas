<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Schema\ChunkedEmbeddingColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
            ChunkedEmbeddingColumns::add($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
