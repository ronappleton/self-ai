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
        Schema::create('voices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('voice_id')->unique();
            $table->string('status')->default('pending');
            $table->string('storage_disk');
            $table->string('dataset_path');
            $table->string('dataset_sha256');
            $table->unsignedInteger('sample_count')->default(0);
            $table->string('script_version');
            $table->string('consent_scope');
            $table->json('metadata')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['voice_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voices');
    }
};
