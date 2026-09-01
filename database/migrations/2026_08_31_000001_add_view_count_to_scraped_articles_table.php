<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraped_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('scraped_articles', 'view_count')) {
                $table->unsignedBigInteger('view_count')->nullable()->after('summary');
                $table->index('view_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scraped_articles', function (Blueprint $table) {
            if (Schema::hasColumn('scraped_articles', 'view_count')) {
                $table->dropIndex(['view_count']);
                $table->dropColumn('view_count');
            }
        });
    }
};
