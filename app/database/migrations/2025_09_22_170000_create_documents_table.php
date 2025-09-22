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
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('sha256');
            $table->string('storage_disk')->default('minio');
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->string('retention_class')->nullable();
            $table->string('consent_scope');
            $table->boolean('pii_scrubbed')->default(true);
            $table->longText('sanitized_content')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
