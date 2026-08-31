<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan tingkat kabupaten yang boleh diubah admin tanpa menyentuh .env.
 *
 * Yang pertama memakainya: jumlah penduduk sebagai penyebut IKK. Angkanya
 * datang dari BPS/Dukcapil dan berubah tiap tahun, jadi tidak layak dikunci di
 * berkas konfigurasi yang hanya bisa disentuh pengembang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
