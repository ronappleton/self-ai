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
        Schema::create('legacy_directives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('beneficiaries')->nullable();
            $table->json('topics_allow')->nullable();
            $table->json('topics_deny')->nullable();
            $table->json('duration')->nullable();
            $table->json('rate_limits')->nullable();
            $table->json('unlock_policy')->nullable();
            $table->string('passphrase_hash')->nullable();
            $table->timestamp('panic_disabled_at')->nullable();
            $table->string('panic_disabled_reason')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->string('erased_reason')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('panic_disabled_at');
            $table->index('erased_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_directives');
    }
};
