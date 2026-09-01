<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraped_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('scraped_articles', 'visitor_count')) {
                $table->unsignedBigInteger('visitor_count')->nullable()->after('view_count');
                $table->index('visitor_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraped_articles', function (Blueprint $table) {
            if (Schema::hasColumn('scraped_articles', 'visitor_count')) {
                $table->dropIndex(['visitor_count']);
                $table->dropColumn('visitor_count');
            }
        });
    }
};
