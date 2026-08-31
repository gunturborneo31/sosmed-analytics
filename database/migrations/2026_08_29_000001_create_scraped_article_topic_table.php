<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scraped_article_topic')) {
            Schema::create('scraped_article_topic', function (Blueprint $table) {
                $table->foreignId('scraped_article_id')->constrained()->cascadeOnDelete();
                $table->foreignId('search_topic_id')->constrained()->cascadeOnDelete();
                $table->primary(['scraped_article_id', 'search_topic_id']);
            });
        }

        DB::table('scraped_articles')->select(['id', 'search_topic_id'])->orderBy('id')->each(function (object $article): void {
            DB::table('scraped_article_topic')->insertOrIgnore([
                'scraped_article_id' => $article->id,
                'search_topic_id' => $article->search_topic_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraped_article_topic');
    }
};