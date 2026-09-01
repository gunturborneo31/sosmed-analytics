<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_metric_check_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_sources')->default(0);
            $table->unsignedInteger('processed_sources')->default(0);
            $table->unsignedInteger('view_readable_sources')->default(0);
            $table->unsignedInteger('visitor_readable_sources')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_metric_check_runs');
    }
};
