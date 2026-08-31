<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('profile_views')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0);
            $table->jsonb('raw_payload')->nullable(); // respons mentah dari Meta
            $table->timestamps();

            $table->unique(['social_account_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_snapshots_payload ON insight_snapshots USING GIN (raw_payload)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_snapshots');
    }
};
