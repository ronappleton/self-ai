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
        Schema::create('legacy_preview_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('persona_name')->nullable();
            $table->string('tone')->default('gentle');
            $table->json('redactions')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('window_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('tone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_preview_sessions');
    }
};
