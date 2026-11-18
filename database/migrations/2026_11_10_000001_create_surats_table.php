<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('no_urut');
            $table->unsignedSmallInteger('tahun');
            $table->string('nomor_agenda')->unique();
            $table->string('jenis_surat', 10);
            $table->string('nomor_surat');
            $table->date('tanggal_surat');
            $table->date('tanggal_terima');
            $table->string('asal_surat');
            $table->text('perihal');
            $table->string('file_pdf')->nullable();
            $table->string('status')->default('baru');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['tahun', 'no_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surats');
    }
};