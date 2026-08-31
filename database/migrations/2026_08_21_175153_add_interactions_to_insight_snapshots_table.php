<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement sebelumnya diturunkan dari jangkauan dibagi pengikut — itu
 * "reach rate", bukan engagement. Meta menyediakan angka interaksi sungguhan
 * (Instagram `total_interactions`, Facebook `page_post_engagements`), jadi
 * angkanya disimpan apa adanya di sini dan persentasenya diturunkan dari situ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insight_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('interactions')->default(0)->after('profile_views');
        });
    }

    public function down(): void
    {
        Schema::table('insight_snapshots', function (Blueprint $table): void {
            $table->dropColumn('interactions');
        });
    }
};
