<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL foto profil dari CDN Meta jauh melampaui 255 karakter — satu contoh nyata
 * dari Instagram panjangnya ~700 karakter karena membawa tanda tangan, waktu
 * kedaluwarsa, dan parameter penelusuran. Akibatnya penyimpanan akun gagal
 * dengan galat 500 tepat setelah operator menyetujui izin di Instagram.
 *
 * Dijadikan `text` supaya panjangnya tidak lagi jadi soal; Postgres menyimpan
 * varchar dan text dengan cara yang sama, jadi tidak ada biaya tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->text('avatar_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->string('avatar_url')->nullable()->change();
        });
    }
};
