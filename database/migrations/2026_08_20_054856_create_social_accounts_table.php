<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizational_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform');            // instagram | facebook
            $table->string('platform_account_id'); // IG User ID / Page ID
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('access_token');          // cast: encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status')->default('connected'); // connected | expired | revoked | error
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'platform_account_id']);
            $table->index(['organizational_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
