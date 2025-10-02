<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('collected_at');
            $table->json('metrics');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
