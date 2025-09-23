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
        Schema::create('builds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfc_id')->constrained('rfc_proposals')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('status_reason')->nullable();
            $table->string('diff_disk')->default('minio');
            $table->string('diff_path')->nullable();
            $table->string('test_report_disk')->default('minio');
            $table->string('test_report_path')->nullable();
            $table->string('artefacts_disk')->default('minio');
            $table->string('artefacts_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['rfc_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
