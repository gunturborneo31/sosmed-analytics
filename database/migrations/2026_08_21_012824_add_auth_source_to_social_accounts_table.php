<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            /*
             | Dari jalur mana akun ini dihubungkan.
             |
             | Bukan sekadar catatan: keduanya memakai server API yang berbeda.
             | Akun lewat Facebook Login dipanggil di graph.facebook.com dengan
             | token Page, sedangkan lewat Instagram Login di graph.instagram.com
             | dengan token pengguna Instagram. Salah server berarti token
             | ditolak.
             |
             | Baris lama otomatis bernilai 'facebook' karena sebelum ini hanya
             | jalur itu yang ada.
             */
            $table->string('auth_source')->default('facebook')->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('auth_source');
        });
    }
};
