<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_breakdowns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('dimension'); // age | gender | age_gender | city | country | locale
            $table->jsonb('data');       // {"25-34": 4820, "35-44": 2310, ...}
            $table->timestamps();

            $table->unique(['social_account_id', 'snapshot_date', 'dimension']);
            $table->index(['dimension', 'snapshot_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_breakdowns_data ON audience_breakdowns USING GIN (data)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_breakdowns');
    }
};
