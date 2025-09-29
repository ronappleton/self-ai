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
        Schema::create('legacy_directive_unlocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('directive_id');
            $table->string('executor_name');
            $table->string('status');
            $table->string('reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('available_after')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('directive_id')->references('id')->on('legacy_directives')->cascadeOnDelete();
            $table->index(['directive_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_directive_unlocks');
    }
};
