<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->unsignedBigInteger('vector_id')->nullable()->unique();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('chunk_offset')->default(0);
            $table->unsignedInteger('chunk_length');
            $table->text('chunk_text');
            $table->string('source', 255);
            $table->string('embedding_model', 100)->default('hashed-self-1');
            $table->string('embedding_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
