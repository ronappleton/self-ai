<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audio_transcriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 40);
            $table->string('input_disk');
            $table->string('input_path');
            $table->string('transcript_disk')->nullable();
            $table->string('transcript_path')->nullable();
            $table->text('transcript_text')->nullable();
            $table->json('timings')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_transcriptions');
    }
};
