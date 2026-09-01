<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('media_sources', 'can_read_view_count')) {
                $table->boolean('can_read_view_count')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('media_sources', 'can_read_visitor_count')) {
                $table->boolean('can_read_visitor_count')->nullable()->after('can_read_view_count');
            }

            if (! Schema::hasColumn('media_sources', 'metrics_checked_at')) {
                $table->dateTime('metrics_checked_at')->nullable()->after('can_read_visitor_count');
            }

            if (! Schema::hasColumn('media_sources', 'metrics_check_message')) {
                $table->string('metrics_check_message')->nullable()->after('metrics_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_sources', function (Blueprint $table) {
            foreach (['metrics_check_message', 'metrics_checked_at', 'can_read_visitor_count', 'can_read_view_count'] as $column) {
                if (Schema::hasColumn('media_sources', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
