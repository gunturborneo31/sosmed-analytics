<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraping_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_steps')->default(0);
            $table->unsignedInteger('processed_steps')->default(0);
            $table->unsignedInteger('new_articles')->default(0);
            $table->boolean('stop_requested')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_runs');
    }
};