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
        if (!Schema::hasTable('reportes_laboratorios_destinatarios')) {
            Schema::create('reportes_laboratorios_destinatarios', function (Blueprint $table) {
                $table->id();
                $table->string('nit', 30)->index();
                $table->string('laboratorio', 200);
                $table->text('correos_para');
                $table->text('correos_cc')->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes_laboratorios_destinatarios');
    }
};
