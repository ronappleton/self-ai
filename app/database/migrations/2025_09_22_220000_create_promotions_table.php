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
        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('build_id');
            $table->string('status');
            $table->string('status_reason')->nullable();
            $table->string('verifier_id');
            $table->string('nonce')->unique();
            $table->string('signature', 255);
            $table->json('request_payload');
            $table->string('canary_status')->default('pending');
            $table->json('canary_report')->nullable();
            $table->boolean('rollback_triggered')->default(false);
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('build_id')->references('id')->on('builds')->cascadeOnDelete();
            $table->index('build_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
