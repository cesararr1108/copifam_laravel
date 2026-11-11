<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('t_log_reader_xml', function (Blueprint $table) {
            $table->id();
            $table->longText('xml');
            $table->dateTime('fecha_hora');
            $table->text('message');
            $table->timestamps();
            
            // Índice para búsquedas por fecha
            $table->index('fecha_hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_log_reader_xml');
    }
};
