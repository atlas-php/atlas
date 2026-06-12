<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function tableName(string $name): string
    {
        return config('atlas.persistence.table_prefix', 'atlas_').$name;
    }

    public function up(): void
    {
        Schema::create($this->tableName('batch_jobs'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_group_id')->nullable()
                ->constrained($this->tableName('batch_groups'))->nullOnDelete();
            $table->string('provider', 50);
            $table->string('modality', 30);
            $table->string('batch_id', 191)->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('succeeded')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('processing')->default(0);
            $table->json('usage')->nullable();
            $table->string('input_file_id', 191)->nullable();
            $table->string('output_file_id', 191)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Polling: the open() scope filters by status; the composite serves
            // the --provider filter. Pruning sweeps by age, so created_at is
            // indexed to keep that delete fast as the table grows to millions.
            $table->index('batch_id');
            $table->index('status');
            $table->index(['provider', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('batch_jobs'));
    }
};
