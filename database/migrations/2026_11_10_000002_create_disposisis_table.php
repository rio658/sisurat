<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposisis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_id')->constrained('surats')->cascadeOnDelete();
            $table->foreignId('dari_user_id')->constrained('users');
            $table->foreignId('ke_user_id')->constrained('users');
            $table->text('instruksi');
            $table->text('catatan')->nullable();
            $table->timestamp('dibaca_at')->nullable();
            $table->timestamp('tindak_lanjut_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposisis');
    }
};