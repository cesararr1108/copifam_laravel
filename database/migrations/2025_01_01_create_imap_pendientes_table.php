<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imap_pendientes', function (Blueprint $table) {
            $table->id();
            $table->integer('email_id')->unique();
            $table->enum('estado', ['pendiente', 'procesado', 'error'])->default('pendiente');
            $table->integer('intentos')->default(0);
            $table->timestamps();
            
            $table->index('estado');
            $table->index('creado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imap_pendientes');
    }
};
