<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->string('feed_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('search_topics', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->text('description')->nullable();
            $table->enum('time_filter_type', ['all', 'last_24h', 'last_7d', 'last_30d', 'custom'])->default('last_7d');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scraped_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_topic_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('article_url')->unique();
            $table->text('summary')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->index(['media_source_id', 'search_topic_id']);
            $table->index('published_at');
        });

        Schema::create('scraping_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('frequency_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_schedules');
        Schema::dropIfExists('scraped_articles');
        Schema::dropIfExists('search_topics');
        Schema::dropIfExists('media_sources');
    }
};